{{--
    Visual Builder — frontend injection.

    Include this partial inside the host app's frontend view ONLY when in
    builder mode. A typical wrapper:

        @if(request()->attributes->get('vb_builder_mode'))
            @include('visual-builder::inject')
        @endif

    Responsibilities:
      - Inject CSS that outlines editable sections on hover and on select.
      - Listen for clicks inside the iframe, forward them to the parent
        via postMessage (section-clicked, field-focused, contextmenu).
      - Listen for live-update commands from the parent (field-update,
        image-update, style-update, element-style-update, visibility-update,
        highlight-section) and mutate the DOM in place — no page reload
        needed to see changes while editing.

    Security:
      - postMessage targetOrigin pinned to window.location.origin.
      - HTML field updates use DOMParser + replaceChildren (not innerHTML).
      - All DOM writes are scoped to [data-vb-editable] elements.
--}}
<style>
    body.vb-builder-active {
        cursor: default;
    }

    .vb-section-wrap {
        position: relative;
        outline: 2px dashed transparent;
        outline-offset: -2px;
        transition: outline-color 120ms ease, background-color 120ms ease;
    }

    .vb-section-wrap:hover {
        outline-color: rgba(37, 99, 235, 0.45);
        cursor: pointer;
    }

    .vb-section-wrap.vb-selected {
        outline: 3px solid #2563eb !important;
        outline-offset: -3px;
    }

    .vb-section-wrap.vb-draft {
        opacity: 0.7;
        background: repeating-linear-gradient(
            135deg,
            rgba(245, 158, 11, 0.04),
            rgba(245, 158, 11, 0.04) 10px,
            transparent 10px,
            transparent 20px
        );
    }

    .vb-section-wrap::before {
        content: attr(data-vb-section-label);
        position: absolute;
        top: 0;
        left: 0;
        padding: 4px 10px;
        background: #2563eb;
        color: #fff;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        z-index: 99999;
        opacity: 0;
        pointer-events: none;
        transition: opacity 120ms ease;
    }

    .vb-section-wrap:hover::before,
    .vb-section-wrap.vb-selected::before {
        opacity: 1;
    }

    body.vb-builder-active [data-vb-editable] {
        position: relative;
        cursor: text !important;
        outline: 1px dashed transparent;
        outline-offset: 2px;
        transition: outline-color 120ms ease, box-shadow 120ms ease;
    }

    body.vb-builder-active [data-vb-editable]:hover {
        outline-color: rgba(16, 185, 129, 0.55);
        box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.12);
    }

    body.vb-builder-active [data-vb-editable].vb-element-selected {
        outline: 2px solid #10b981 !important;
    }

    /* Inline contenteditable mode — clear visual cue that the element is
       being edited in place. Different color from hover/selected so the
       editor can see they're in "typing" mode, not just highlighted. */
    body.vb-builder-active [data-vb-editable].vb-element-editing {
        outline: 2px dashed #f59e0b !important;
        outline-offset: 2px;
        background-color: rgba(245, 158, 11, 0.06) !important;
        cursor: text !important;
        caret-color: #000;
    }

    body.vb-builder-active [data-vb-editable]::after {
        content: attr(data-vb-field);
        position: absolute;
        top: -22px;
        right: 0;
        padding: 2px 6px;
        background: #10b981;
        color: #fff;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        border-radius: 3px;
        opacity: 0;
        pointer-events: none;
        z-index: 99998;
        transition: opacity 120ms ease;
        white-space: nowrap;
    }

    body.vb-builder-active [data-vb-editable]:hover::after,
    body.vb-builder-active [data-vb-editable].vb-element-selected::after {
        opacity: 1;
    }

    body.vb-builder-active a,
    body.vb-builder-active button,
    body.vb-builder-active input[type="submit"] {
        pointer-events: none !important;
    }

    body.vb-builder-active .vb-section-wrap,
    body.vb-builder-active [data-vb-editable] {
        pointer-events: auto !important;
    }

    /* ================================================================
       Inline inserter — Elementor-style "+" button between sections.
       Injected by JS below; a collapsible strip shows a blue "+" on
       hover and broadcasts a postMessage to the parent to open the
       block picker modal. Last inserter sits AFTER every section so
       editors can still append.
       ================================================================ */
    .vb-inserter {
        position: relative;
        height: 16px;
        margin: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        pointer-events: auto !important;
        cursor: pointer;
        transition: height 150ms ease, background-color 150ms ease;
        background-color: transparent;
        z-index: 99990;
    }

    .vb-inserter::before {
        content: '';
        position: absolute;
        left: 5%;
        right: 5%;
        top: 50%;
        height: 2px;
        background-color: transparent;
        transition: background-color 150ms ease;
        transform: translateY(-50%);
    }

    .vb-inserter:hover {
        height: 44px;
        background-color: rgba(37, 99, 235, 0.05);
    }

    .vb-inserter:hover::before {
        background-color: rgba(37, 99, 235, 0.35);
    }

    .vb-inserter-btn {
        position: relative;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background-color: #2563eb;
        color: #fff;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        font-weight: 600;
        line-height: 1;
        opacity: 0;
        transform: scale(0.6);
        transition: opacity 150ms ease, transform 150ms ease;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
        pointer-events: auto !important;
    }

    .vb-inserter:hover .vb-inserter-btn {
        opacity: 1;
        transform: scale(1);
    }

    .vb-inserter-btn:hover {
        background-color: #1d4ed8;
    }

    /* ================================================================
       Hover toolbar — floats above selected section with quick
       actions (duplicate, move up/down, delete). Positioned via
       absolute + pointer-events:auto so clicks land reliably.
       ================================================================ */
    .vb-section-wrap .vb-toolbar {
        position: absolute;
        top: -1px;
        right: -1px;
        display: none;
        background-color: #2563eb;
        border-radius: 0 0 0 4px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.18);
        z-index: 99997;
        pointer-events: auto !important;
    }

    .vb-section-wrap:hover .vb-toolbar,
    .vb-section-wrap.vb-selected .vb-toolbar {
        display: flex;
    }

    .vb-toolbar-btn {
        width: 28px;
        height: 28px;
        border: none;
        background: transparent;
        color: #fff;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        pointer-events: auto !important;
        transition: background-color 120ms ease;
    }

    .vb-toolbar-btn:hover {
        background-color: rgba(0, 0, 0, 0.2);
    }

    .vb-toolbar-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    .vb-toolbar-btn.vb-toolbar-btn-danger:hover {
        background-color: #dc2626;
    }

    /* Inline SVG icons for toolbar + inserter (mask-based, inherit
       currentColor, no external dependency). */
    .vb-tb-icon {
        display: inline-block;
        width: 16px;
        height: 16px;
        background-color: currentColor;
        mask-repeat: no-repeat;
        mask-position: center;
        mask-size: contain;
        -webkit-mask-repeat: no-repeat;
        -webkit-mask-position: center;
        -webkit-mask-size: contain;
    }
    .vb-tb-icon-copy { mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='8' y='8' width='14' height='14' rx='2'/%3E%3Cpath d='M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2'/%3E%3C/svg%3E"); -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='8' y='8' width='14' height='14' rx='2'/%3E%3Cpath d='M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2'/%3E%3C/svg%3E"); }
    .vb-tb-icon-trash { mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cline x1='3' y1='6' x2='21' y2='6'/%3E%3Cpath d='M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6'/%3E%3Cpath d='M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2'/%3E%3C/svg%3E"); -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cline x1='3' y1='6' x2='21' y2='6'/%3E%3Cpath d='M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6'/%3E%3Cpath d='M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2'/%3E%3C/svg%3E"); }
    .vb-tb-icon-up { mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='18 15 12 9 6 15'/%3E%3C/svg%3E"); -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='18 15 12 9 6 15'/%3E%3C/svg%3E"); }
    .vb-tb-icon-down { mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); }
</style>

<script>
(function () {
    'use strict';

    const allowedOrigin = window.location.origin;
    document.body.classList.add('vb-builder-active');

    // Breakpoint thresholds shared with the server-side
    // BreakpointStyleResolver so live-preview @@media queries match the
    // rules @@vbSectionStyles emits on the production render. The
    // thresholds come from config/visual-builder.php via the
    // resolver's thresholds() bootstrap on iframe render.
    const vbBreakpoints = @json(app(\Umutsevimcann\VisualBuilder\Domain\Services\BreakpointStyleResolver::class)->thresholds());

    /**
     * Forward section wrapper + editable element clicks to the parent window.
     * A single capturing listener handles both cases — finer-grained targets
     * win (editable before wrapper).
     */
    document.addEventListener('click', function (e) {
        const editable = e.target.closest('[data-vb-editable]');
        if (editable) {
            e.preventDefault();
            e.stopPropagation();

            document.querySelectorAll('[data-vb-editable].vb-element-selected').forEach(function (el) {
                el.classList.remove('vb-element-selected');
            });
            editable.classList.add('vb-element-selected');

            const parentWrap = editable.closest('.vb-section-wrap');
            if (parentWrap) {
                document.querySelectorAll('.vb-section-wrap.vb-selected').forEach(function (el) {
                    el.classList.remove('vb-selected');
                });
                parentWrap.classList.add('vb-selected');
            }

            window.parent.postMessage({
                source: 'vb-iframe',
                type: 'field-focused',
                sectionId: parseInt(editable.getAttribute('data-vb-section-id'), 10),
                fieldKey: editable.getAttribute('data-vb-field'),
                locale: editable.getAttribute('data-vb-locale'),
            }, allowedOrigin);
            return;
        }

        const wrap = e.target.closest('.vb-section-wrap');
        if (!wrap) return;
        e.preventDefault();
        e.stopPropagation();

        document.querySelectorAll('.vb-section-wrap.vb-selected').forEach(function (el) {
            el.classList.remove('vb-selected');
        });
        wrap.classList.add('vb-selected');

        window.parent.postMessage({
            source: 'vb-iframe',
            type: 'section-clicked',
            sectionId: parseInt(wrap.getAttribute('data-vb-section-id'), 10),
            sectionType: wrap.getAttribute('data-vb-section-type'),
        }, allowedOrigin);
    }, true);

    /**
     * Inline contenteditable on double-click. Elementor-style "edit text
     * in place": dblclick a [data-vb-editable] → it becomes editable,
     * on blur the new text is posted to the parent which queues a save.
     *
     * Plain-text branch: strips all markup on commit (target.textContent).
     * Use for headlines, labels, short strings. Rich-text fields are
     * handled by the separate vb-html dblclick handler further down
     * (bubble menu for bold / italic / link / heading / list) — the
     * attribute selector here excludes them so the two handlers never
     * race for the same event.
     */
    document.addEventListener('dblclick', function (e) {
        const target = e.target.closest('[data-vb-editable]');
        if (!target || target.hasAttribute('data-vb-html')) return;

        e.preventDefault();
        e.stopPropagation();

        if (target.isContentEditable) return;

        const originalText = target.textContent;
        target.setAttribute('contenteditable', 'true');
        target.classList.add('vb-element-editing');
        target.focus();

        const range = document.createRange();
        range.selectNodeContents(target);
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);

        const commit = function () {
            target.removeAttribute('contenteditable');
            target.classList.remove('vb-element-editing');
            target.removeEventListener('blur', commit);
            target.removeEventListener('keydown', keyHandler);

            const newText = target.textContent;
            if (newText === originalText) return;

            window.parent.postMessage({
                source: 'vb-iframe',
                type: 'inline-edit',
                sectionId: parseInt(target.getAttribute('data-vb-section-id'), 10),
                fieldKey: target.getAttribute('data-vb-field'),
                locale: target.getAttribute('data-vb-locale'),
                value: newText,
            }, allowedOrigin);
        };

        const keyHandler = function (ev) {
            if (ev.key === 'Escape') {
                target.textContent = originalText;
                target.blur();
            } else if (ev.key === 'Enter' && !ev.shiftKey) {
                ev.preventDefault();
                target.blur();
            }
        };

        target.addEventListener('blur', commit);
        target.addEventListener('keydown', keyHandler);
    });

    /**
     * Rich-text inline editing for [data-vb-editable][data-vb-html] elements.
     *
     * Elementor-parity: double-click enters edit mode, a floating bubble
     * menu appears above text selection with Bold / Italic / Underline /
     * Link / H2 / H3 / UL / OL / Clear-formatting buttons. Commit on
     * blur posts innerHTML (not textContent) to the parent via the
     * same inline-edit postMessage the plain-text path uses — the
     * field-update handler on the parent side knows to dispatch into
     * DOMParser when the target element carries data-vb-html, so HTML
     * tags survive the round-trip.
     *
     * Persistence: server-side HtmlField runs every value through the
     * configured SanitizerInterface (HTMLPurifier by default) before
     * storage, so the only untrusted surface here is the bubble menu's
     * Link dialog — we gate the href through a URL constructor check
     * and reject javascript: / data: schemes explicitly.
     *
     * Keyboard shortcuts (Ctrl+B, Ctrl+I, Ctrl+U) work out of the box
     * because browsers map them to document.execCommand('bold') etc.
     * inside contenteditable regions; the bubble menu is additive UI.
     */
    const VB_RTE_TOOLBAR_HTML = '<button type="button" data-vb-rte="bold" title="Bold (Ctrl+B)"><b>B</b></button>'
        + '<button type="button" data-vb-rte="italic" title="Italic (Ctrl+I)"><i>I</i></button>'
        + '<button type="button" data-vb-rte="underline" title="Underline (Ctrl+U)"><u>U</u></button>'
        + '<span class="vb-rte-sep"></span>'
        + '<button type="button" data-vb-rte="h2" title="Heading 2">H2</button>'
        + '<button type="button" data-vb-rte="h3" title="Heading 3">H3</button>'
        + '<button type="button" data-vb-rte="p" title="Paragraph">P</button>'
        + '<span class="vb-rte-sep"></span>'
        + '<button type="button" data-vb-rte="ul" title="Bullet list">•</button>'
        + '<button type="button" data-vb-rte="ol" title="Numbered list">1.</button>'
        + '<span class="vb-rte-sep"></span>'
        + '<button type="button" data-vb-rte="link" title="Insert / edit link">🔗</button>'
        + '<button type="button" data-vb-rte="unlink" title="Remove link">⊘</button>'
        + '<span class="vb-rte-sep"></span>'
        + '<button type="button" data-vb-rte="clear" title="Clear formatting">⎚</button>';

    let vbRteBubble = null;
    let vbRteActiveEl = null;

    function vbRteEnsureBubble() {
        if (vbRteBubble) return vbRteBubble;
        const div = document.createElement('div');
        div.className = 'vb-rte-bubble';
        div.setAttribute('role', 'toolbar');
        div.setAttribute('aria-label', 'Rich text formatting');
        div.innerHTML = VB_RTE_TOOLBAR_HTML;
        div.style.cssText = 'position:absolute;display:none;z-index:100000;background:#1f2937;color:#fff;'
            + 'border-radius:6px;padding:4px;box-shadow:0 8px 24px rgba(0,0,0,0.25);font:12px/1 -apple-system,sans-serif;';
        // Stop the editable from losing focus when a button is mouse-pressed.
        div.addEventListener('mousedown', function (e) { e.preventDefault(); });
        div.addEventListener('click', vbRteHandleToolbar);
        document.body.appendChild(div);

        // Style every button: dense flat chips with hover.
        const buttons = div.querySelectorAll('button');
        buttons.forEach(function (b) {
            b.style.cssText = 'background:transparent;color:#fff;border:0;padding:6px 8px;margin:0 1px;'
                + 'border-radius:4px;cursor:pointer;font:inherit;min-width:28px';
            b.addEventListener('mouseover', function () { b.style.background = 'rgba(255,255,255,0.12)'; });
            b.addEventListener('mouseout', function () { b.style.background = 'transparent'; });
        });
        div.querySelectorAll('.vb-rte-sep').forEach(function (s) {
            s.style.cssText = 'display:inline-block;width:1px;height:16px;background:rgba(255,255,255,0.2);vertical-align:middle;margin:0 4px';
        });

        vbRteBubble = div;
        return div;
    }

    /** Position the bubble above the current selection, clamped to viewport. */
    function vbRtePositionBubble() {
        const sel = window.getSelection();
        if (!sel || sel.rangeCount === 0 || sel.isCollapsed) {
            if (vbRteBubble) vbRteBubble.style.display = 'none';
            return;
        }
        const rect = sel.getRangeAt(0).getBoundingClientRect();
        if (rect.width === 0 && rect.height === 0) {
            if (vbRteBubble) vbRteBubble.style.display = 'none';
            return;
        }
        const bubble = vbRteEnsureBubble();
        bubble.style.display = 'block';
        const bw = bubble.offsetWidth;
        const bh = bubble.offsetHeight;
        const scrollX = window.scrollX;
        const scrollY = window.scrollY;
        let top = rect.top + scrollY - bh - 8;
        if (top < scrollY + 4) top = rect.bottom + scrollY + 8;
        let left = rect.left + scrollX + (rect.width - bw) / 2;
        const maxLeft = scrollX + document.documentElement.clientWidth - bw - 4;
        if (left < scrollX + 4) left = scrollX + 4;
        if (left > maxLeft) left = maxLeft;
        bubble.style.top = top + 'px';
        bubble.style.left = left + 'px';
    }

    /**
     * Whether the selection lives inside an element currently marked as
     * editing. Prevents the bubble from appearing when the user selects
     * text anywhere else in the iframe (e.g. a read-only paragraph).
     */
    function vbRteSelectionInsideEditable() {
        const sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return false;
        let node = sel.getRangeAt(0).commonAncestorContainer;
        if (node.nodeType === Node.TEXT_NODE) node = node.parentElement;
        if (!node) return false;
        return node.closest('[data-vb-editable][data-vb-html][contenteditable="true"]') !== null;
    }

    /**
     * Reject obviously dangerous URLs on the Link button. Package server
     * still runs the full field through HTMLPurifier; this is a UX guard
     * that stops `javascript:` entering the DOM at all, so broken pastes
     * are never "fixed" by the purifier removing the whole anchor tag.
     */
    function vbRteSafeUrl(input) {
        if (typeof input !== 'string') return '';
        const trimmed = input.trim();
        if (trimmed === '') return '';
        // Allow protocol-relative, absolute http/https/mailto/tel, and
        // relative paths (starts with / or #). Everything else is dropped.
        if (/^(https?:|mailto:|tel:|\/\/|\/|#)/i.test(trimmed)) return trimmed;
        if (/^[a-z0-9._~-]+(\.[a-z0-9._~-]+)+/i.test(trimmed)) {
            // Bare "example.com/foo" → prepend https.
            return 'https://'+trimmed;
        }
        return '';
    }

    function vbRteHandleToolbar(e) {
        const btn = e.target.closest('button[data-vb-rte]');
        if (!btn) return;
        const action = btn.getAttribute('data-vb-rte');
        e.preventDefault();

        // document.execCommand is deprecated but universally supported and
        // still the most portable way to format a selection inside a
        // contenteditable region. Modern alternatives (Selection API +
        // manual DOM surgery) require a lot of code for feature parity.
        switch (action) {
            case 'bold':
            case 'italic':
            case 'underline':
                document.execCommand(action);
                break;
            case 'h2':
            case 'h3':
            case 'p':
                document.execCommand('formatBlock', false, action);
                break;
            case 'ul':
                document.execCommand('insertUnorderedList');
                break;
            case 'ol':
                document.execCommand('insertOrderedList');
                break;
            case 'link': {
                const existing = document.queryCommandValue('createLink') || '';
                const url = vbRteSafeUrl(window.prompt('URL:', existing) || '');
                if (url !== '') document.execCommand('createLink', false, url);
                break;
            }
            case 'unlink':
                document.execCommand('unlink');
                break;
            case 'clear':
                document.execCommand('removeFormat');
                break;
        }
        // Reposition — the selection geometry may have shifted.
        vbRtePositionBubble();
    }

    document.addEventListener('selectionchange', function () {
        if (!vbRteSelectionInsideEditable()) {
            if (vbRteBubble) vbRteBubble.style.display = 'none';
            return;
        }
        vbRtePositionBubble();
    });

    document.addEventListener('dblclick', function (e) {
        const target = e.target.closest('[data-vb-editable][data-vb-html]');
        if (!target) return;
        if (target.isContentEditable) return;

        e.preventDefault();
        e.stopPropagation();

        const originalHtml = target.innerHTML;
        target.setAttribute('contenteditable', 'true');
        target.classList.add('vb-element-editing');
        target.focus();

        // Selection placed at the click point — DO NOT select-all like the
        // plain-text path. Rich-text callers usually want to start typing
        // at the click position, not overwrite the whole paragraph.
        vbRteActiveEl = target;

        const commit = function () {
            target.removeAttribute('contenteditable');
            target.classList.remove('vb-element-editing');
            target.removeEventListener('blur', commit);
            target.removeEventListener('keydown', keyHandler);
            if (vbRteBubble) vbRteBubble.style.display = 'none';
            vbRteActiveEl = null;

            const newHtml = target.innerHTML;
            if (newHtml === originalHtml) return;

            window.parent.postMessage({
                source: 'vb-iframe',
                type: 'inline-edit',
                sectionId: parseInt(target.getAttribute('data-vb-section-id'), 10),
                fieldKey: target.getAttribute('data-vb-field'),
                locale: target.getAttribute('data-vb-locale'),
                value: newHtml,
            }, allowedOrigin);
        };

        // Escape reverts; Enter on its own lets users break paragraphs
        // (Shift+Enter is the usual "line break"). Commit is blur-driven.
        const keyHandler = function (ev) {
            if (ev.key === 'Escape') {
                target.innerHTML = originalHtml;
                target.blur();
            }
        };

        target.addEventListener('blur', commit);
        target.addEventListener('keydown', keyHandler);
    });

    /**
     * Right-click context menu — forwarded to parent, which renders its
     * own styled menu (native context menu would break out of the iframe).
     */
    document.addEventListener('contextmenu', function (e) {
        const wrap = e.target.closest('.vb-section-wrap');
        if (!wrap) return;
        e.preventDefault();

        window.parent.postMessage({
            source: 'vb-iframe',
            type: 'contextmenu',
            sectionId: parseInt(wrap.getAttribute('data-vb-section-id'), 10),
            x: e.clientX,
            y: e.clientY,
        }, allowedOrigin);
    });

    /**
     * Listen for parent → iframe commands.
     */
    window.addEventListener('message', function (e) {
        if (e.origin !== allowedOrigin) return;
        const msg = e.data;
        if (!msg || msg.source !== 'vb-parent') return;

        switch (msg.type) {
            case 'highlight-section':
                highlightSection(msg.sectionId);
                break;
            case 'field-update':
                updateFieldInDom(msg.sectionId, msg.fieldKey, msg.locale, msg.value);
                break;
            case 'image-update':
                updateImageInDom(msg.sectionId, msg.fieldKey, msg.url);
                break;
            case 'style-update':
                updateSectionStyleInDom(msg.sectionId, msg.style);
                break;
            case 'element-style-update':
                updateElementStyleInDom(msg.sectionId, msg.fieldKey, msg.styles);
                break;
            case 'visibility-update':
                updateVisibilityInDom(msg.sectionId, msg.fieldKey, msg.visible);
                break;
        }
    });

    function highlightSection(sectionId) {
        document.querySelectorAll('.vb-section-wrap.vb-selected').forEach(function (el) {
            el.classList.remove('vb-selected');
        });
        const target = document.querySelector(
            '.vb-section-wrap[data-vb-section-id="' + CSS.escape(String(sectionId)) + '"]'
        );
        if (!target) return;
        target.classList.add('vb-selected');

        // Only scroll when the section is entirely outside the iframe's
        // viewport. Typing in an inline-editable field echoes a highlight
        // back to the iframe on every keystroke; without this guard the
        // preview would yank to-center on every character, dragging the
        // text out from under the user's cursor.
        const rect = target.getBoundingClientRect();
        const outOfView = rect.bottom < 0 || rect.top > window.innerHeight;
        if (outOfView) {
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    function updateFieldInDom(sectionId, fieldKey, locale, value) {
        const selector = '[data-vb-editable]' +
            '[data-vb-section-id="' + CSS.escape(String(sectionId)) + '"]' +
            '[data-vb-field="' + CSS.escape(String(fieldKey)) + '"]' +
            '[data-vb-locale="' + CSS.escape(String(locale)) + '"]';
        const safeValue = value == null ? '' : String(value);

        document.querySelectorAll(selector).forEach(function (el) {
            if (el.hasAttribute('data-vb-html')) {
                // Safe HTML replacement via DOMParser (no innerHTML)
                const doc = new DOMParser().parseFromString(safeValue, 'text/html');
                el.replaceChildren();
                Array.from(doc.body.childNodes).forEach(function (node) {
                    el.appendChild(document.importNode(node, true));
                });
            } else {
                el.textContent = safeValue;
            }
        });
    }

    function updateImageInDom(sectionId, fieldKey, url) {
        const wrap = document.querySelector(
            '.vb-section-wrap[data-vb-section-id="' + CSS.escape(String(sectionId)) + '"]'
        );
        if (!wrap) return;

        if (fieldKey === 'video_poster' || fieldKey === 'video_url') {
            const video = wrap.querySelector('video');
            if (!video) return;
            if (fieldKey === 'video_poster') {
                video.setAttribute('poster', url || '');
            } else {
                const source = video.querySelector('source');
                if (source) source.setAttribute('src', url || '');
            }
            video.load();
            return;
        }

        const img = wrap.querySelector('img');
        if (img) img.setAttribute('src', url || '');
    }

    // Mirrors BreakpointStyleResolver::INHERITANCE on the server side.
    // Order of lookup when a breakpoint slot is empty on an object value.
    const VB_BP_INHERIT = {
        desktop: ['desktop', 'tablet', 'mobile'],
        tablet: ['tablet', 'desktop', 'mobile'],
        mobile: ['mobile', 'tablet', 'desktop'],
    };

    // Mirrors BreakpointStyleResolver::COMPOUND_KEYS — internal style
    // keys that expand to multiple CSS properties.
    const VB_COMPOUND = {
        padding_y: ['padding-top', 'padding-bottom'],
        padding_x: ['padding-left', 'padding-right'],
        margin_y: ['margin-top', 'margin-bottom'],
        margin_x: ['margin-left', 'margin-right'],
    };

    /** snake_case style keys whose CSS name differs from str_replace(_, -, key). */
    const VB_KEY_ALIASES = {
        alignment: 'text-align',
        bg_color: 'background-color',
        text_color: 'color',
    };

    /** Keys NOT emitted as CSS — handled by host templates via class names. */
    const VB_CSS_SKIPPED_KEYS = ['animation', 'animation_delay'];

    /** Spacing keys where a bare numeric value gets `px` appended. */
    const VB_UNITLESS_PX_KEYS = [
        'padding_y', 'padding_x',
        'padding_top', 'padding_right', 'padding_bottom', 'padding_left',
        'margin_y', 'margin_x',
        'margin_top', 'margin_right', 'margin_bottom', 'margin_left',
    ];

    /**
     * Resolve one style value for a specific breakpoint. Scalars return
     * as-is; object values walk VB_BP_INHERIT until a non-empty leaf is
     * found, matching the server-side resolver's output exactly.
     */
    function vbResolveValue(value, breakpoint) {
        if (value == null) return null;
        if (typeof value !== 'object' || Array.isArray(value)) {
            return value === '' ? null : String(value);
        }
        const chain = VB_BP_INHERIT[breakpoint] || VB_BP_INHERIT.desktop;
        for (let i = 0; i < chain.length; i++) {
            const leaf = value[chain[i]];
            if (leaf != null && leaf !== '') return String(leaf);
        }
        return null;
    }

    /** Return the flat key → string map for one breakpoint. */
    function vbResolveAll(style, breakpoint) {
        const out = {};
        if (!style || typeof style !== 'object') return out;
        for (const k of Object.keys(style)) {
            const v = vbResolveValue(style[k], breakpoint);
            if (v != null && v !== '') out[k] = v;
        }
        return out;
    }

    /** List of CSS property names one internal style key expands to. */
    function vbCssPropsFor(key) {
        if (VB_COMPOUND[key]) return VB_COMPOUND[key];
        return [VB_KEY_ALIASES[key] || key.replace(/_/g, '-')];
    }

    /** `prop: value !important; ...` from a flat resolved map. */
    function vbDeclsFor(resolved) {
        const parts = [];
        for (const k of Object.keys(resolved)) {
            if (VB_CSS_SKIPPED_KEYS.indexOf(k) !== -1) continue;
            const value = vbCssValueFor(k, resolved[k]);
            for (const prop of vbCssPropsFor(k)) {
                parts.push(prop + ': ' + value + ' !important');
            }
        }
        return parts.join('; ');
    }

    /** Append `px` to bare numeric spacing values; other keys verbatim. */
    function vbCssValueFor(key, value) {
        if (VB_UNITLESS_PX_KEYS.indexOf(key) !== -1
            && value !== ''
            && !isNaN(value)
            && !isNaN(parseFloat(value))) {
            return value + 'px';
        }
        return value;
    }

    /** Entries of `over` that differ from `base` — drives @media blocks. */
    function vbDiff(base, over) {
        const out = {};
        for (const k of Object.keys(over)) {
            const prev = Object.prototype.hasOwnProperty.call(base, k) ? base[k] : null;
            if (prev !== over[k]) out[k] = over[k];
        }
        return out;
    }

    /**
     * Build the CSS text for one section — desktop rule + optional
     * @media blocks for tablet/mobile overrides. Mirrors the output of
     * BreakpointStyleResolver::toCss so live preview and production
     * stay cascade-equivalent when the user is editing.
     */
    function vbBuildSectionCss(sectionId, style) {
        // :not([data-vb-editable]) — keeps the cascade from escaping into
        // the editable descendants (h1, p, img) that carry the same
        // data-vb-section-id attribute for click routing. Matches the
        // server-side VisualBuilder::sectionStylesTag selector exactly.
        const selector = '[data-vb-section-id="' + String(sectionId) + '"]:not([data-vb-editable])';
        const desktop = vbResolveAll(style, 'desktop');
        const tablet = vbResolveAll(style, 'tablet');
        const mobile = vbResolveAll(style, 'mobile');

        const blocks = [];
        const dDecls = vbDeclsFor(desktop);
        if (dDecls) blocks.push(selector + ' { ' + dDecls + ' }');

        const tDiff = vbDiff(desktop, tablet);
        if (Object.keys(tDiff).length) {
            blocks.push(
                '@media (max-width: ' + vbBreakpoints.tablet_max + 'px) { ' +
                selector + ' { ' + vbDeclsFor(tDiff) + ' } }'
            );
        }
        const mBase = Object.keys(tablet).length ? tablet : desktop;
        const mDiff = vbDiff(mBase, mobile);
        if (Object.keys(mDiff).length) {
            blocks.push(
                '@media (max-width: ' + vbBreakpoints.mobile_max + 'px) { ' +
                selector + ' { ' + vbDeclsFor(mDiff) + ' } }'
            );
        }
        return blocks.join(' ');
    }

    /**
     * Update the section's scoped <style> block in place. If the block
     * does not exist yet (e.g. the section was rendered from cached HTML
     * that predates the @@vbSectionStyles directive), create one just
     * before the wrap so cascade order is stable.
     *
     * Uses CSS media queries rather than inline styles resolved against
     * window.innerWidth so the browser handles breakpoint switching when
     * the editor resizes the iframe — no JS resize listener needed, and
     * production render emits the same shape.
     */
    function updateSectionStyleInDom(sectionId, style) {
        const wrap = document.querySelector(
            '.vb-section-wrap[data-vb-section-id="' + CSS.escape(String(sectionId)) + '"]'
        );
        if (!wrap) return;

        const tagSelector = 'style[data-vb-section-styles="' + CSS.escape(String(sectionId)) + '"]';
        let tag = document.querySelector(tagSelector);
        if (!tag) {
            tag = document.createElement('style');
            tag.setAttribute('data-vb-section-styles', String(sectionId));
            wrap.parentNode.insertBefore(tag, wrap);
        }
        tag.textContent = vbBuildSectionCss(sectionId, style || {});
    }

    function updateElementStyleInDom(sectionId, fieldKey, styles) {
        const selector = '[data-vb-editable]' +
            '[data-vb-section-id="' + CSS.escape(String(sectionId)) + '"]' +
            '[data-vb-field="' + CSS.escape(String(fieldKey)) + '"]';
        const knownProps = [
            'color', 'background-color', 'font-family', 'font-size', 'font-weight',
            'letter-spacing', 'line-height', 'text-align', 'text-transform',
            'padding', 'margin', 'border-radius', 'opacity', 'box-shadow',
        ];

        document.querySelectorAll(selector).forEach(function (el) {
            knownProps.forEach(function (prop) { el.style.removeProperty(prop); });
            if (!styles || typeof styles !== 'object') return;
            Object.keys(styles).forEach(function (prop) {
                const value = styles[prop];
                if (value == null || value === '') return;
                el.style.setProperty(prop.replace(/_/g, '-'), String(value), 'important');
            });
        });
    }

    function updateVisibilityInDom(sectionId, fieldKey, visible) {
        if (!fieldKey) {
            const wrap = document.querySelector(
                '.vb-section-wrap[data-vb-section-id="' + CSS.escape(String(sectionId)) + '"]'
            );
            if (wrap) wrap.classList.toggle('vb-draft', !visible);
            return;
        }
        document.querySelectorAll(
            '[data-vb-editable]' +
            '[data-vb-section-id="' + CSS.escape(String(sectionId)) + '"]' +
            '[data-vb-field="' + CSS.escape(String(fieldKey)) + '"]'
        ).forEach(function (el) {
            el.style.display = visible ? '' : 'none';
        });
    }

    /**
     * Elementor-style inline UI scaffolding:
     *   1. Insert a `.vb-inserter` (hover-reveal "+" button) BEFORE every
     *      section wrapper and one at the very end. Clicking it tells the
     *      parent to open the block palette at that insertion point.
     *   2. Inject a `.vb-toolbar` inside every section wrapper with
     *      duplicate / move-up / move-down / delete buttons. Buttons
     *      postMessage the parent; parent calls the package's REST
     *      endpoints and reloads the iframe when done.
     *
     * Runs once on load. If the iframe reloads after a mutation (edit,
     * insert, delete), this IIFE re-runs and rebuilds the inserters +
     * toolbars for the fresh DOM — no state kept across reloads.
     */
    injectInlineUi();

    function injectInlineUi() {
        const wraps = Array.from(document.querySelectorAll('.vb-section-wrap'));

        wraps.forEach(function (wrap, index) {
            const prevSectionId = index === 0
                ? null
                : parseInt(wraps[index - 1].getAttribute('data-vb-section-id'), 10);
            wrap.parentNode.insertBefore(buildInserter(prevSectionId), wrap);

            wrap.appendChild(buildToolbar(
                parseInt(wrap.getAttribute('data-vb-section-id'), 10),
                index === 0,
                index === wraps.length - 1,
            ));
        });

        // Trailing inserter — appends a new section at the end
        if (wraps.length > 0) {
            const last = wraps[wraps.length - 1];
            const lastId = parseInt(last.getAttribute('data-vb-section-id'), 10);
            last.parentNode.insertBefore(buildInserter(lastId), last.nextSibling);
        } else {
            // Empty target: still offer one inserter so the editor can add the first section
            document.body.appendChild(buildInserter(null));
        }
    }

    /**
     * Build a `.vb-inserter` DIV with a "+" button. `afterSectionId` tells
     * the parent where to inject the new section (null = prepend to first).
     */
    function buildInserter(afterSectionId) {
        const strip = document.createElement('div');
        strip.className = 'vb-inserter';
        strip.setAttribute('data-vb-insert-after', afterSectionId == null ? '' : String(afterSectionId));

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'vb-inserter-btn';
        btn.setAttribute('aria-label', 'Insert section');
        btn.textContent = '+';
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            window.parent.postMessage({
                source: 'vb-iframe',
                type: 'insert-requested',
                afterSectionId: afterSectionId,
            }, allowedOrigin);
        });

        strip.appendChild(btn);
        return strip;
    }

    /**
     * Build the hover toolbar for a single section. isFirst / isLast
     * flags disable the up/down arrows at the extremes so editors can't
     * fire no-op reorders.
     */
    function buildToolbar(sectionId, isFirst, isLast) {
        const toolbar = document.createElement('div');
        toolbar.className = 'vb-toolbar';

        const makeBtn = function (iconClass, label, type, extraClass) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'vb-toolbar-btn' + (extraClass ? ' ' + extraClass : '');
            b.setAttribute('aria-label', label);
            const icon = document.createElement('span');
            icon.className = 'vb-tb-icon ' + iconClass;
            b.appendChild(icon);
            b.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                window.parent.postMessage({
                    source: 'vb-iframe',
                    type: type,
                    sectionId: sectionId,
                }, allowedOrigin);
            });
            return b;
        };

        const upBtn = makeBtn('vb-tb-icon-up', 'Move section up', 'move-up', '');
        upBtn.disabled = isFirst;
        toolbar.appendChild(upBtn);

        const downBtn = makeBtn('vb-tb-icon-down', 'Move section down', 'move-down', '');
        downBtn.disabled = isLast;
        toolbar.appendChild(downBtn);

        toolbar.appendChild(makeBtn('vb-tb-icon-copy', 'Duplicate section', 'duplicate-section', ''));
        toolbar.appendChild(makeBtn('vb-tb-icon-trash', 'Delete section', 'delete-section', 'vb-toolbar-btn-danger'));

        return toolbar;
    }

    // Signal readiness to the parent — loading overlay can now dismiss.
    window.parent.postMessage({
        source: 'vb-iframe',
        type: 'loaded',
        sectionCount: document.querySelectorAll('.vb-section-wrap').length,
    }, allowedOrigin);
})();
</script>
