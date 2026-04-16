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

    // Signal readiness to the parent — loading overlay can now dismiss.
    window.parent.postMessage({
        source: 'vb-iframe',
        type: 'loaded',
        sectionCount: document.querySelectorAll('.vb-section-wrap').length,
    }, allowedOrigin);
})();
</script>
