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
        if (target) {
            target.classList.add('vb-selected');
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

    function updateSectionStyleInDom(sectionId, style) {
        const wrap = document.querySelector(
            '.vb-section-wrap[data-vb-section-id="' + CSS.escape(String(sectionId)) + '"]'
        );
        if (!wrap) return;

        const root = wrap.querySelector('section') || wrap;
        const apply = function (prop, value) {
            if (value == null || value === '') root.style.removeProperty(prop);
            else root.style.setProperty(prop, value, 'important');
        };

        apply('background-color', style && style.bg_color);
        apply('color', style && style.text_color);
        apply('text-align', style && style.alignment);

        if (style && style.padding_y) {
            apply('padding-top', style.padding_y);
            apply('padding-bottom', style.padding_y);
        } else {
            apply('padding-top', null);
            apply('padding-bottom', null);
        }

        ['top', 'right', 'bottom', 'left'].forEach(function (side) {
            apply('padding-' + side, style && style['padding_' + side]);
            apply('margin-' + side, style && style['margin_' + side]);
        });
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
