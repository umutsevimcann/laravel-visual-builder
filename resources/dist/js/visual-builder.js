/**
 * Visual Builder — admin editor client.
 *
 * Vanilla JS (no framework), IIFE-scoped to avoid global leakage. Exposes
 * window.VBuilder only for browser-console debugging.
 *
 * Architecture overview:
 *   1. readBootstrap()          — pick up the JSON payload rendered by
 *                                 the Editor Blade component.
 *   2. initIframeMessaging()    — listen for postMessage events from the
 *                                 preview iframe; targetOrigin-pinned.
 *   3. renderBlockPalette()     — left panel: one card per section type.
 *   4. renderTraits(sectionId)  — right panel: 3-tab form (Content/Style/
 *                                 Advanced) built from the registered
 *                                 field schema.
 *   5. save()                   — POST pending state to the save endpoint.
 *
 * Persistence boundary:
 *   Nothing touches the database until the editor clicks Save. Until
 *   then every change lives in state.pending keyed by section id, and
 *   the client postMessages the preview iframe so the user sees live
 *   updates without a server round-trip.
 *
 * Security:
 *   - All string interpolation passes through escapeHtml().
 *   - Image upload and save fetch() calls include X-CSRF-TOKEN.
 *   - postMessage uses targetOrigin = window.location.origin.
 */
(function () {
    'use strict';

    // ─────────────────────────────────────────────────────────────
    // Utilities
    // ─────────────────────────────────────────────────────────────

    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function setHtml(element, html) {
        if (!element) return;
        element.replaceChildren();
        element.insertAdjacentHTML('afterbegin', html);
    }

    function ensurePath(obj, keys) {
        let ref = obj;
        for (const key of keys) {
            if (ref[key] === undefined || ref[key] === null) ref[key] = {};
            ref = ref[key];
        }
        return ref;
    }

    function deepClone(value) {
        return value === undefined ? undefined : JSON.parse(JSON.stringify(value));
    }

    // ─────────────────────────────────────────────────────────────
    // State
    // ─────────────────────────────────────────────────────────────

    const state = {
        config: null,
        iframe: null,
        iframeReady: false,
        dirty: false,
        activeTab: 'content',
        selectedSectionId: null,
        // Inline insert flow (iframe `+` → parent palette → createSection):
        // while non-null, the next block-palette click creates a section
        // right after this sibling id (0 = prepend before first section).
        pendingInsertAfter: null,
        pending: {
            sections: {},
            orderedIds: null,
        },
        // Undo/redo history — snapshots of state.config.sections captured
        // when an edit cluster settles (400ms idle). Structural ops
        // (add/delete/duplicate) reset the history to a new baseline
        // because the server persists them immediately and the bulk
        // save endpoint cannot recreate deleted rows.
        history: {
            entries: [],       // { sections: JSON string, label: string }
            current: -1,       // index of the active snapshot (−1 = empty)
            max: 50,
            clusterTimer: null,
            clusterMs: 400,
        },
        // Currently-edited breakpoint — drives which slice of object-shape
        // style values the traits panel reads and writes. Synced with the
        // top toolbar's device-preview buttons: clicking tablet resizes
        // the iframe AND switches the traits panel to tablet values.
        activeBreakpoint: 'desktop', // 'desktop' | 'tablet' | 'mobile'
        // Currently-selected category tab in the v0.5 block palette.
        // Persists across re-renders so a structural op does not reset
        // the user to the first tab. null → first-available category
        // auto-picked on first render.
        activePaletteCategory: null,
    };

    // ─────────────────────────────────────────────────────────────
    // Responsive breakpoint helpers (mirror BreakpointStyleResolver)
    // ─────────────────────────────────────────────────────────────

    /** Keys whose values may be breakpoint objects; drives UI affordances. */
    const VB_RESPONSIVE_KEYS = [
        'padding_y', 'padding_x',
        'padding_top', 'padding_right', 'padding_bottom', 'padding_left',
        'margin_top', 'margin_right', 'margin_bottom', 'margin_left',
        'alignment',
    ];

    /** Inheritance chain — first filled breakpoint in the chain wins. */
    const VB_BP_INHERIT = {
        desktop: ['desktop', 'tablet', 'mobile'],
        tablet: ['tablet', 'desktop', 'mobile'],
        mobile: ['mobile', 'tablet', 'desktop'],
    };

    /**
     * Resolve a stored style value for the given breakpoint. Scalars
     * return unchanged; object values walk the inheritance chain. Used
     * by the traits panel when rendering inputs so each device preview
     * shows its effective value.
     */
    function vbResolveStyleValue(value, breakpoint) {
        if (value == null) return null;
        if (typeof value !== 'object' || Array.isArray(value)) {
            return value === '' ? null : value;
        }
        const chain = VB_BP_INHERIT[breakpoint] || VB_BP_INHERIT.desktop;
        for (let i = 0; i < chain.length; i++) {
            const leaf = value[chain[i]];
            if (leaf != null && leaf !== '') return leaf;
        }
        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // Bootstrap
    // ─────────────────────────────────────────────────────────────

    function readBootstrap() {
        const node = document.querySelector('[data-vb-bootstrap]');
        if (!node) {
            console.error('[VBuilder] Bootstrap payload element missing');
            return null;
        }
        try {
            return JSON.parse(node.textContent || '{}');
        } catch (err) {
            console.error('[VBuilder] Bootstrap JSON parse failed:', err);
            return null;
        }
    }

    function findSection(id) {
        return (state.config.sections || []).find(s => s.id === id) || null;
    }

    // ─────────────────────────────────────────────────────────────
    // Block palette (left)
    // ─────────────────────────────────────────────────────────────

    /** Human-readable labels for the canonical category keys. Unknown
     *  categories fall back to their raw key (capitalized). */
    const VB_CATEGORY_LABELS = {
        basic: 'Basic',
        media: 'Media',
        layout: 'Layout',
        general: 'General',
    };

    /** Preferred ordering of the canonical category tabs. Categories not
     *  in the list come last, in insertion order. */
    const VB_CATEGORY_ORDER = ['basic', 'media', 'layout', 'general'];

    /**
     * Group section types by category key, preserving the registry's
     * insertion order inside each group. Pure function — drives the
     * palette render without touching DOM.
     *
     * @returns {Array<{key: string, label: string, types: Array<{key: string, type: object}>}>}
     */
    function buildPaletteCategories() {
        const types = state.config.types || {};
        const groups = {};
        Object.keys(types).forEach(function (typeKey) {
            const cat = String(types[typeKey].category || 'general');
            if (!groups[cat]) groups[cat] = [];
            groups[cat].push({ key: typeKey, type: types[typeKey] });
        });

        const keys = Object.keys(groups);
        keys.sort(function (a, b) {
            const ai = VB_CATEGORY_ORDER.indexOf(a);
            const bi = VB_CATEGORY_ORDER.indexOf(b);
            if (ai === -1 && bi === -1) return a.localeCompare(b);
            if (ai === -1) return 1;
            if (bi === -1) return -1;
            return ai - bi;
        });

        return keys.map(function (k) {
            const label = VB_CATEGORY_LABELS[k]
                || (k.charAt(0).toUpperCase() + k.slice(1).replace(/_/g, ' '));
            return { key: k, label: label, types: groups[k] };
        });
    }

    /**
     * Render the block palette as Elementor-style category tabs plus a
     * grid of widget cards underneath. Active tab persists across
     * re-renders via `state.activePaletteCategory` so a structural
     * mutation does not reset the user to the first tab.
     *
     * Tab markup + card grid live inside the same `[data-vb-blocks]`
     * container the legacy flat render used — host apps that published
     * a customized editor.blade.php with `data-vb-blocks` on a
     * different element still work unchanged.
     */
    function renderBlockPalette() {
        const container = document.querySelector('[data-vb-blocks]');
        if (!container) return;

        const categories = buildPaletteCategories();
        if (categories.length === 0) {
            setHtml(container, '<div class="vb-empty vb-palette-empty">'
                + '<p class="vb-empty-text">No section types registered.</p></div>');
            return;
        }

        if (!state.activePaletteCategory || !categories.some(c => c.key === state.activePaletteCategory)) {
            state.activePaletteCategory = categories[0].key;
        }

        const existingTypes = new Set((state.config.sections || []).map(s => s.type));

        const tabsHtml = '<div class="vb-palette-tabs" role="tablist">'
            + categories.map(function (cat) {
                const active = cat.key === state.activePaletteCategory;
                return '<button type="button" class="vb-palette-tab'
                    + (active ? ' vb-palette-tab-active' : '') + '"'
                    + ' role="tab" aria-selected="' + (active ? 'true' : 'false') + '"'
                    + ' data-vb-palette-tab="' + escapeHtml(cat.key) + '"'
                    + ' title="' + escapeHtml(cat.label) + '">'
                    + '<span class="vb-palette-tab-label">' + escapeHtml(cat.label) + '</span>'
                    + '<span class="vb-palette-tab-count">' + cat.types.length + '</span>'
                    + '</button>';
            }).join('')
            + '</div>';

        const active = categories.find(c => c.key === state.activePaletteCategory);
        const gridHtml = '<div class="vb-palette-grid" role="tabpanel">'
            + active.types.map(function (entry) {
                const disabled = !entry.type.allows_multiple && existingTypes.has(entry.key);
                const label = entry.type.label || entry.key;
                const desc = entry.type.description || '';
                const iconClass = (entry.type.icon || '').toString();
                return '<button type="button" class="vb-palette-card'
                    + (disabled ? ' vb-palette-card-disabled' : '') + '"'
                    + ' draggable="' + (disabled ? 'false' : 'true') + '"'
                    + (disabled ? ' disabled' : '')
                    + ' data-vb-block="' + escapeHtml(entry.key) + '"'
                    + ' title="' + escapeHtml(desc) + '">'
                    + '<span class="vb-palette-card-icon" aria-hidden="true">'
                    + (iconClass ? '<i class="' + escapeHtml(iconClass) + '"></i>' : '')
                    + '</span>'
                    + '<span class="vb-palette-card-label">' + escapeHtml(label) + '</span>'
                    + '</button>';
            }).join('')
            + '</div>';

        setHtml(container, tabsHtml + gridHtml);

        // Tab click switches active category; re-renders to redraw grid.
        container.querySelectorAll('[data-vb-palette-tab]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                const next = tab.getAttribute('data-vb-palette-tab');
                if (next === state.activePaletteCategory) return;
                state.activePaletteCategory = next;
                renderBlockPalette();
            });
        });

        // Card click — create a section of that type.
        container.querySelectorAll('[data-vb-block]:not([disabled])').forEach(function (btn) {
            btn.addEventListener('click', function () {
                createSection(btn.getAttribute('data-vb-block'));
            });
        });
    }

    async function createSection(typeKey) {
        if (state.dirty && !confirm('Discard unsaved changes and add a new section?')) {
            return;
        }

        // When the iframe `+` inserter was clicked, `state.pendingInsertAfter`
        // holds the sibling section id — pass it along so the new section
        // lands right after that sibling instead of appending to the end.
        const afterId = state.pendingInsertAfter;
        state.pendingInsertAfter = null;
        clearInsertMode();

        const params = new URLSearchParams();
        params.append('type', typeKey);
        params.append('_token', state.config.csrf_token);
        if (afterId != null) params.append('after_section_id', String(afterId));

        try {
            const response = await fetch(state.config.routes.store, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': state.config.csrf_token,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: params.toString(),
            });
            if (response.ok) {
                const data = await response.json();
                applyMutationResponse(data);
                return;
            }
            console.error('[VBuilder] Create section failed:', response.status);
        } catch (err) {
            console.error('[VBuilder] Create section error:', err);
        }
    }

    /**
     * Shared post-mutation refresh: replace local state.config.sections
     * with the server's fresh list and reload the iframe preview.
     * Called after create/duplicate/delete/move — eliminates full
     * `window.location.reload()` calls that were destroying the editor's
     * scroll position, selected tab, and undo history on every edit.
     *
     * @param {{sections?: Array}} data  Server response body
     */
    function applyMutationResponse(data) {
        if (data && Array.isArray(data.sections)) {
            state.config.sections = data.sections;
        }
        // Structural changes persist immediately server-side; the bulk
        // save endpoint cannot recreate a deleted row, so rather than
        // let undo desync client and server, clear the history and seed
        // a new baseline at this structural anchor.
        resetHistoryBaseline('structural');
        // Re-render block palette — singleton constraints (one-per-target)
        // depend on which section types currently exist on the target.
        renderBlockPalette();
        reloadIframe();
    }

    /**
     * Enter "insert mode": highlight the left palette so the editor knows
     * the next block click will insert at the chosen position. Called by
     * the iframe's `+` inserter via postMessage.
     */
    function enterInsertMode(afterSectionId) {
        state.pendingInsertAfter = afterSectionId;
        const panel = document.querySelector('.vb-panel-left');
        if (panel) panel.classList.add('vb-insert-mode');
    }

    function clearInsertMode() {
        const panel = document.querySelector('.vb-panel-left');
        if (panel) panel.classList.remove('vb-insert-mode');
    }

    /**
     * Delete a section: confirm, DELETE /sections/{id}, reload iframe.
     * The template route uses `0` as a placeholder for the section id —
     * swap it in at call time. This keeps the template URL opaque to the
     * client while still letting us build final URLs safely.
     */
    async function deleteSection(sectionId) {
        if (state.dirty) {
            if (!confirm('Discard unsaved changes and delete this section?')) return;
        } else if (!confirm('Delete this section? This cannot be undone.')) {
            return;
        }

        const url = state.config.routes.destroy_template.replace(/\/0(\?|$)/, '/' + sectionId + '$1');
        try {
            const response = await fetch(url, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': state.config.csrf_token,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            clearDirty();
            if (state.selectedSectionId === sectionId) {
                state.selectedSectionId = null;
                const traits = document.querySelector('[data-vb-traits]');
                if (traits) setHtml(traits, '<div class="vb-empty"><i class="vb-icon vb-icon-pointer vb-empty-icon"></i><p class="vb-empty-text">Click a section in the preview to edit.</p></div>');
            }
            const data = await response.json();
            applyMutationResponse(data);
        } catch (err) {
            console.error('[VBuilder] Delete section failed:', err);
            alert('Delete failed: ' + err.message);
        }
    }

    async function duplicateSection(sectionId) {
        const url = state.config.routes.duplicate_template.replace(/\/0(\/duplicate|$)/, '/' + sectionId + '$1');
        try {
            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': state.config.csrf_token,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: '_token=' + encodeURIComponent(state.config.csrf_token),
            });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const data = await response.json();
            applyMutationResponse(data);
        } catch (err) {
            console.error('[VBuilder] Duplicate section failed:', err);
            alert('Duplicate failed: ' + err.message);
        }
    }

    /**
     * Move a section up/down by one position. Computes the new
     * ordered_ids array from the current bootstrap state and POSTs it
     * to the save endpoint. Iframe reloads with the fresh order.
     */
    async function moveSection(sectionId, direction) {
        const sections = (state.config.sections || [])
            .slice()
            .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
        const idx = sections.findIndex(s => s.id === sectionId);
        if (idx < 0) return;

        const targetIdx = direction === 'up' ? idx - 1 : idx + 1;
        if (targetIdx < 0 || targetIdx >= sections.length) return;

        [sections[idx], sections[targetIdx]] = [sections[targetIdx], sections[idx]];
        const orderedIds = sections.map(s => s.id);

        try {
            const response = await fetch(state.config.routes.save, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': state.config.csrf_token,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ ordered_ids: orderedIds }),
            });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const data = await response.json();
            applyMutationResponse(data);
        } catch (err) {
            console.error('[VBuilder] Move section failed:', err);
            alert('Move failed: ' + err.message);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Traits panel (right) — 3 tabs
    // ─────────────────────────────────────────────────────────────

    /**
     * @param {number} sectionId
     * @param {string|null} focusFieldKey  When provided (e.g. iframe
     *   click on a [data-vb-editable] element), the panel auto-switches
     *   to the Content tab, scrolls the matching field into view, and
     *   focuses its first input. Elementor-style "click text, get that
     *   field's editor" feedback.
     */
    function renderTraits(sectionId, focusFieldKey = null) {
        state.selectedSectionId = sectionId;
        const section = findSection(sectionId);
        const container = document.querySelector('[data-vb-traits]');
        if (!section || !container) return;

        const type = state.config.types[section.type];
        if (!type) {
            setHtml(container, '<div class="vb-empty"><p class="vb-empty-text">Unknown section type.</p></div>');
            return;
        }

        // If the user clicked a specific field in the preview, force the
        // Content tab — no point showing Style controls when they wanted
        // to edit text.
        if (focusFieldKey) state.activeTab = 'content';
        const activeTab = state.activeTab;

        const html = [
            '<div class="vb-section-header">',
            '<div class="vb-section-header-icon" aria-hidden="true"></div>',
            '<div>',
            '<div class="vb-section-header-label">', escapeHtml(type.label), '</div>',
            '<div class="vb-section-header-meta">#', escapeHtml(String(section.id)), ' · ', escapeHtml(section.type), '</div>',
            '</div>',
            '</div>',
            '<div class="vb-tabs" role="tablist">',
            renderTabButton('content', 'Content', activeTab),
            renderTabButton('style', 'Style', activeTab),
            renderTabButton('advanced', 'Advanced', activeTab),
            '</div>',
            '<div class="vb-tab-panel', activeTab === 'content' ? ' vb-tab-panel-active' : '', '" data-vb-tab="content">',
            renderContentTab(section, type),
            '</div>',
            '<div class="vb-tab-panel', activeTab === 'style' ? ' vb-tab-panel-active' : '', '" data-vb-tab="style">',
            renderStyleTab(section, type),
            '</div>',
            '<div class="vb-tab-panel', activeTab === 'advanced' ? ' vb-tab-panel-active' : '', '" data-vb-tab="advanced">',
            renderAdvancedTab(section, type),
            '</div>',
        ].join('');

        setHtml(container, html);
        bindTabSwitches(container);
        bindFieldEditors(container, section);
        bindStyleEditors(container, section);
        bindSectionActions(container, section);

        postToIframe({ type: 'highlight-section', sectionId: section.id });

        // Scroll + focus requested field. Deferred until after layout so
        // scrollIntoView/focus target has final geometry.
        if (focusFieldKey) {
            requestAnimationFrame(() => focusTraitsField(container, focusFieldKey));
        }
    }

    function focusTraitsField(container, fieldKey) {
        const group = container.querySelector('[data-vb-field="' + CSS.escape(fieldKey) + '"]');
        if (!group) return;
        // scrollIntoView() bubbles up through every scrollable ancestor —
        // including document.scrollingElement — which caused the admin page
        // itself to jump when the user clicked an inline-editable element
        // in the iframe. Walk up to the nearest scrollable container only
        // and scroll that, leaving the admin page alone.
        scrollWithinNearestScrollable(group);
        group.classList.add('vb-field-focused');
        setTimeout(() => group.classList.remove('vb-field-focused'), 1500);
        const input = group.querySelector('input, textarea, select');
        // preventScroll: true — HTMLElement.focus() otherwise triggers its
        // own scrollIntoView which re-introduces the same page-jump bug.
        if (input) input.focus({ preventScroll: true });
    }

    /**
     * Scroll a single nearest scrollable ancestor of `element` so the
     * element lands vertically centered within it. Does NOT scroll any
     * ancestor above that (including window/document), which is exactly
     * what scrollIntoView() would do. Stops at document.body.
     */
    function scrollWithinNearestScrollable(element) {
        let parent = element.parentElement;
        while (parent && parent !== document.body) {
            const style = getComputedStyle(parent);
            if (/(auto|scroll)/.test(style.overflowY)) {
                const pr = parent.getBoundingClientRect();
                const er = element.getBoundingClientRect();
                const target = parent.scrollTop + (er.top - pr.top) - (pr.height / 2) + (er.height / 2);
                parent.scrollTo({ top: Math.max(0, target), behavior: 'smooth' });
                return;
            }
            parent = parent.parentElement;
        }
    }

    function renderTabButton(key, label, activeTab) {
        const active = activeTab === key;
        return [
            '<button type="button" class="vb-tab', active ? ' vb-tab-active' : '', '"',
            ' role="tab" aria-selected="', active ? 'true' : 'false', '"',
            ' data-vb-tab-btn="', escapeHtml(key), '">',
            escapeHtml(label),
            '</button>',
        ].join('');
    }

    function bindTabSwitches(container) {
        container.querySelectorAll('[data-vb-tab-btn]').forEach(btn => {
            btn.addEventListener('click', () => {
                state.activeTab = btn.getAttribute('data-vb-tab-btn');
                renderTraits(state.selectedSectionId);
            });
        });
    }

    // ─────────────────────────────────────────────────────────────
    // Content tab — field editors per type
    // ─────────────────────────────────────────────────────────────

    function renderContentTab(section, type) {
        const parts = [
            '<div class="vb-group">',
            '<div class="vb-group-title">Visibility</div>',
            '<div class="vb-field">',
            '<label class="vb-field-label">',
            '<span>Published</span>',
            '<input type="checkbox" data-vb-published', section.is_published ? ' checked' : '', '>',
            '</label>',
            '<div class="vb-field-help">Hide the whole section without deleting it.</div>',
            '</div>',
            '</div>',
        ];

        parts.push('<div class="vb-group"><div class="vb-group-title">Fields</div>');
        (type.fields || []).forEach(field => {
            parts.push(renderField(section, field));
        });
        parts.push('</div>');

        parts.push([
            '<div class="vb-group">',
            '<div class="vb-group-title">Actions</div>',
            '<div style="display:flex;gap:6px">',
            type.allows_multiple
                ? '<button type="button" class="vb-btn vb-btn-ghost" data-vb-action-section="duplicate">Duplicate</button>'
                : '',
            type.is_deletable
                ? '<button type="button" class="vb-btn vb-btn-ghost" style="color:#dc2626" data-vb-action-section="delete">Delete</button>'
                : '',
            '</div>',
            '</div>',
        ].join(''));

        return parts.join('');
    }

    function renderField(section, field) {
        const value = currentContentValue(section, field.key);
        const visibility = section.content && section.content._visibility ? section.content._visibility : {};
        const isVisible = visibility[field.key] !== false;
        const wrapperCls = 'vb-field' + (isVisible ? '' : ' vb-field-hidden');
        const parts = [
            '<div class="', wrapperCls, '" data-vb-field="', escapeHtml(field.key), '">',
            '<label class="vb-field-label">',
            '<span>', escapeHtml(field.label), field.required ? ' <em style="color:#dc2626">*</em>' : '', '</span>',
            field.toggleable
                ? '<button type="button" style="background:none;border:0;cursor:pointer;font-size:14px" data-vb-toggle-field="' + escapeHtml(field.key) + '" title="Show/hide on site">' + (isVisible ? '👁' : '🙈') + '</button>'
                : '',
            '</label>',
        ];

        switch (field.input_type) {
            case 'text':
                parts.push(renderTextInput(section, field, value));
                break;
            case 'html':
                parts.push(renderTextarea(section, field, value));
                break;
            case 'toggle':
                parts.push(renderToggleControl(section, field, value));
                break;
            case 'select':
                parts.push(renderSelect(section, field, value));
                break;
            case 'link':
                parts.push(renderLink(section, field));
                break;
            case 'image':
                parts.push(renderImage(section, field, value));
                break;
            case 'reference':
                parts.push(renderReference(section, field, value));
                break;
            case 'repeater':
                parts.push('<div class="vb-field-help" style="color:#6b7280">Repeater widget planned for v0.2.</div>');
                break;
            default:
                parts.push('<div class="vb-field-help">Unsupported field type: ' + escapeHtml(field.input_type) + '</div>');
        }

        if (field.help) {
            parts.push('<div class="vb-field-help">', escapeHtml(field.help), '</div>');
        }
        parts.push('</div>');
        return parts.join('');
    }

    function currentContentValue(section, fieldKey) {
        return section.content && section.content[fieldKey] !== undefined ? section.content[fieldKey] : null;
    }

    function renderTextInput(section, field, value) {
        if (field.translatable) return renderLocalizedInput(section, field, value, 'input');
        const v = typeof value === 'string' ? value : '';
        return '<input type="text" class="vb-field-input" data-vb-input="' + escapeHtml(field.key)
            + '" placeholder="' + escapeHtml(field.placeholder || '') + '"'
            + ' value="' + escapeHtml(v) + '">';
    }

    function renderTextarea(section, field, value) {
        return renderLocalizedInput(section, field, value, 'textarea');
    }

    function renderLocalizedInput(section, field, value, inputTag) {
        const locales = state.config.locales || ['en'];
        const localeMap = (value && typeof value === 'object' && !Array.isArray(value)) ? value : {};
        const activeLocale = field._activeLocale || locales[0];

        const tabs = locales.map(loc => {
            const active = loc === activeLocale;
            return '<button type="button" style="padding:2px 8px;font-size:10px;border:1px solid ' + (active ? '#2563eb' : '#d1d5db') + ';background:' + (active ? '#eef2ff' : '#fff') + ';color:' + (active ? '#2563eb' : '#6b7280') + ';border-radius:3px;margin-right:2px;cursor:pointer" data-vb-locale="' + escapeHtml(loc) + '" data-vb-field-key="' + escapeHtml(field.key) + '">'
                + escapeHtml(loc.toUpperCase()) + '</button>';
        }).join('');

        const inputs = locales.map(loc => {
            const active = loc === activeLocale;
            const text = localeMap[loc] !== undefined && localeMap[loc] !== null ? String(localeMap[loc]) : '';
            const hidden = active ? '' : ' style="display:none"';
            if (inputTag === 'textarea') {
                return '<textarea class="vb-field-textarea"' + hidden + ' data-vb-input="' + escapeHtml(field.key) + '" data-vb-locale="' + escapeHtml(loc) + '" placeholder="' + escapeHtml(field.placeholder || '') + '">' + escapeHtml(text) + '</textarea>';
            }
            return '<input type="text" class="vb-field-input"' + hidden + ' data-vb-input="' + escapeHtml(field.key) + '" data-vb-locale="' + escapeHtml(loc) + '" placeholder="' + escapeHtml(field.placeholder || '') + '" value="' + escapeHtml(text) + '">';
        }).join('');

        return '<div style="margin-bottom:4px">' + tabs + '</div>' + inputs;
    }

    function renderToggleControl(section, field, value) {
        const checked = value === true || value === 'true' || value === 1 || value === '1';
        return '<label style="display:inline-flex;align-items:center;gap:6px"><input type="checkbox" data-vb-input="' + escapeHtml(field.key) + '"' + (checked ? ' checked' : '') + '><span style="font-size:12px;color:#6b7280">' + (checked ? 'Enabled' : 'Disabled') + '</span></label>';
    }

    function renderSelect(section, field, value) {
        const options = (field.options || (field.meta && field.meta.options)) || {};
        const opts = Object.entries(options).map(([k, label]) =>
            '<option value="' + escapeHtml(k) + '"' + (k === value ? ' selected' : '') + '>' + escapeHtml(label) + '</option>',
        ).join('');
        return '<select class="vb-field-select" data-vb-input="' + escapeHtml(field.key) + '"><option value="">—</option>' + opts + '</select>';
    }

    function renderLink(section, field) {
        const urlKey = field.key + '_url';
        const labelKey = field.key + '_label';
        const url = currentContentValue(section, urlKey) || '';
        const parts = [
            '<input type="url" class="vb-field-input" data-vb-input="' + escapeHtml(urlKey) + '" placeholder="https://..." value="' + escapeHtml(url) + '">',
        ];
        if (field.with_label !== false) {
            const labelValue = currentContentValue(section, labelKey);
            parts.push('<div class="vb-field-help" style="margin-top:4px">Button label per locale:</div>');
            const labelField = Object.assign({}, field, { key: labelKey, translatable: true });
            parts.push(renderLocalizedInput(section, labelField, labelValue, 'input'));
        }
        return parts.join('');
    }

    function renderImage(section, field, value) {
        const path = typeof value === 'string' ? value : '';
        const hasImage = path !== '';
        const preview = hasImage
            ? '<img src="' + escapeHtml(imagePathToUrl(path)) + '" alt="" style="max-width:100%;max-height:120px;object-fit:cover;border-radius:4px;display:block">'
            : '<div style="padding:20px;background:#f3f4f6;border-radius:4px;color:#9ca3af;font-size:12px;text-align:center">No image</div>';
        return [
            '<div>',
            preview,
            '<div style="display:flex;gap:4px;margin-top:6px">',
            '<button type="button" class="vb-btn vb-btn-ghost" data-vb-image-upload="' + escapeHtml(field.key) + '" style="flex:1">Upload</button>',
            hasImage ? '<button type="button" class="vb-btn vb-btn-ghost" style="color:#dc2626" data-vb-image-clear="' + escapeHtml(field.key) + '">×</button>' : '',
            '</div>',
            hasImage ? '<div class="vb-field-help" style="font-family:monospace;font-size:10px;word-break:break-all">' + escapeHtml(path) + '</div>' : '',
            '</div>',
        ].join('');
    }

    function imagePathToUrl(path) {
        if (!path) return '';
        if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('//')) return path;
        if (path.startsWith('assets/')) return '/' + path;
        return '/storage/' + path;
    }

    function renderReference(section, field, value) {
        const selected = Array.isArray(value) ? value.map(Number) : [];
        const options = field.options_preview || [];
        if (options.length === 0) {
            return '<div class="vb-field-help" style="color:#b45309;font-style:italic">No options available. Register an options resolver on this ReferenceField.</div>';
        }
        const checkboxes = options.map(o => {
            const checked = selected.includes(Number(o.id));
            return '<label style="display:flex;align-items:center;gap:6px;padding:4px 0;font-size:12px"><input type="checkbox" data-vb-reference="' + escapeHtml(field.key) + '" value="' + escapeHtml(String(o.id)) + '"' + (checked ? ' checked' : '') + '> ' + escapeHtml(o.label || '#' + o.id) + '</label>';
        }).join('');
        return '<div style="max-height:200px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:4px;padding:6px">' + checkboxes + '</div>';
    }

    // ─────────────────────────────────────────────────────────────
    // Style tab
    // ─────────────────────────────────────────────────────────────

    function renderStyleTab(section) {
        const style = section.style || {};
        const bp = state.activeBreakpoint;
        // Resolver shim so selectRow/colorPickerRow can be called with
        // EITHER a scalar stored value (legacy) or an object-shape
        // responsive value. The displayed widget always reflects the
        // currently-active breakpoint's effective value via inheritance.
        const v = function (key) { return vbResolveStyleValue(style[key], bp); };

        const parts = [
            // Active-breakpoint indicator — makes it clear which device
            // preview the user is editing when viewing tablet/mobile.
            // Clicking the device icons in the top toolbar updates this.
            '<div class="vb-field-help" style="margin-bottom:8px;padding:6px 10px;background:#eff6ff;border-left:3px solid #2563eb;border-radius:3px;font-size:11px;color:#1e40af">',
            'Editing for: <strong>', escapeHtml(bp.charAt(0).toUpperCase() + bp.slice(1)), '</strong>',
            bp !== 'desktop' ? ' <span style="color:#6b7280">(inherits from desktop unless overridden)</span>' : '',
            '</div>',

            '<div class="vb-group">',
            '<div class="vb-group-title">Background</div>',
            colorPickerRow('Background color', 'bg_color', v('bg_color')),
            colorPickerRow('Text color', 'text_color', v('text_color')),
            '</div>',

            '<div class="vb-group">',
            '<div class="vb-group-title">Spacing</div>',
            selectRow('Vertical padding', 'padding_y', v('padding_y'), {
                '': 'Default',
                '40px': 'Compact (40px)',
                '80px': 'Normal (80px)',
                '120px': 'Spacious (120px)',
                '160px': 'Extra (160px)',
            }),
            selectRow('Alignment', 'alignment', style.alignment, {
                '': 'Default',
                'left': 'Left',
                'center': 'Center',
                'right': 'Right',
            }),
            '</div>',

            '<div class="vb-group">',
            '<div class="vb-group-title">Motion</div>',
            selectRow('Entrance animation', 'animation', style.animation, {
                '': 'None',
                'fadeIn': 'Fade In',
                'fadeInUp': 'Fade In Up',
                'fadeInDown': 'Fade In Down',
                'fadeInLeft': 'Fade In Left',
                'fadeInRight': 'Fade In Right',
                'zoomIn': 'Zoom In',
                'slideInUp': 'Slide In Up',
                'slideInDown': 'Slide In Down',
                'bounceIn': 'Bounce In',
                'flipInX': 'Flip In',
            }),
            '</div>',
        ];
        return parts.join('');
    }

    /**
     * Responsive badge used next to a field label when the style key is
     * breakpoint-aware. Signals to the user that this field stores a
     * per-device value and the currently-active breakpoint controls
     * what they see / edit.
     */
    function responsiveBadge(key) {
        if (VB_RESPONSIVE_KEYS.indexOf(key) === -1) return '';
        const bp = state.activeBreakpoint;
        const icon = bp === 'mobile' ? '📱' : bp === 'tablet' ? '📲' : '🖥️';
        return ' <span class="vb-responsive-badge" title="Responsive — per-breakpoint value"'
            + ' style="display:inline-flex;align-items:center;gap:2px;font-size:10px;color:#2563eb;'
            + 'background:#eff6ff;padding:1px 6px;border-radius:10px;margin-left:4px">'
            + icon + ' ' + escapeHtml(bp) + '</span>';
    }

    function colorPickerRow(label, key, value) {
        const v = value || '#ffffff';
        return [
            '<div class="vb-field">',
            '<label class="vb-field-label"><span>', escapeHtml(label), responsiveBadge(key), '</span></label>',
            '<div style="display:flex;gap:6px;align-items:center">',
            '<input type="color" data-vb-style="', escapeHtml(key), '" value="', escapeHtml(v), '" style="width:44px;height:32px;padding:2px;border:1px solid #d1d5db;border-radius:4px">',
            '<input type="text" class="vb-field-input" data-vb-style-text="', escapeHtml(key), '" value="', escapeHtml(value || ''), '" placeholder="inherit" style="flex:1;font-family:monospace">',
            '</div>',
            '</div>',
        ].join('');
    }

    function selectRow(label, key, current, options) {
        const opts = Object.entries(options).map(([k, lbl]) =>
            '<option value="' + escapeHtml(k) + '"' + ((current || '') === k ? ' selected' : '') + '>' + escapeHtml(lbl) + '</option>',
        ).join('');
        return [
            '<div class="vb-field">',
            '<label class="vb-field-label"><span>', escapeHtml(label), responsiveBadge(key), '</span></label>',
            '<select class="vb-field-select" data-vb-style="', escapeHtml(key), '">', opts, '</select>',
            '</div>',
        ].join('');
    }

    // ─────────────────────────────────────────────────────────────
    // Advanced tab
    // ─────────────────────────────────────────────────────────────

    function renderAdvancedTab(section) {
        return [
            '<div class="vb-group">',
            '<div class="vb-group-title">Section info</div>',
            '<div class="vb-field">',
            '<label class="vb-field-label"><span>Database ID</span></label>',
            '<input type="text" class="vb-field-input" value="', escapeHtml(String(section.id)), '" readonly>',
            '</div>',
            '<div class="vb-field">',
            '<label class="vb-field-label"><span>Instance key</span></label>',
            '<input type="text" class="vb-field-input" value="', escapeHtml(section.instance_key || '__default__'), '" readonly>',
            '</div>',
            '<div class="vb-field">',
            '<label class="vb-field-label"><span>Sort order</span></label>',
            '<input type="text" class="vb-field-input" value="', escapeHtml(String(section.sort_order || 0)), '" readonly>',
            '</div>',
            '<div class="vb-field-help">Drag sections in the preview to reorder (planned for v0.2).</div>',
            '</div>',
        ].join('');
    }

    // ─────────────────────────────────────────────────────────────
    // Event wiring
    // ─────────────────────────────────────────────────────────────

    function bindFieldEditors(container, section) {
        const publishedInput = container.querySelector('[data-vb-published]');
        if (publishedInput) {
            publishedInput.addEventListener('change', () => {
                applyPendingChange(section.id, s => {
                    s.is_published = publishedInput.checked;
                });
            });
        }

        container.querySelectorAll('[data-vb-input]').forEach(input => {
            input.addEventListener('input', () => handleInputChange(section, input));
            input.addEventListener('change', () => handleInputChange(section, input));
        });

        container.querySelectorAll('[data-vb-locale][data-vb-field-key]').forEach(tab => {
            tab.addEventListener('click', () => {
                const fieldKey = tab.getAttribute('data-vb-field-key');
                const locale = tab.getAttribute('data-vb-locale');
                const type = state.config.types[section.type];
                const field = (type.fields || []).find(f => f.key === fieldKey);
                if (field) {
                    field._activeLocale = locale;
                    renderTraits(state.selectedSectionId);
                }
            });
        });

        container.querySelectorAll('[data-vb-toggle-field]').forEach(btn => {
            btn.addEventListener('click', () => {
                const fieldKey = btn.getAttribute('data-vb-toggle-field');
                applyPendingChange(section.id, s => {
                    ensurePath(s, ['content', '_visibility']);
                    const current = s.content._visibility[fieldKey];
                    s.content._visibility[fieldKey] = current === false ? true : false;
                });
                renderTraits(state.selectedSectionId);
                const newVisible = (findSection(section.id).content._visibility || {})[fieldKey] !== false;
                postToIframe({ type: 'visibility-update', sectionId: section.id, fieldKey, visible: newVisible });
            });
        });

        container.querySelectorAll('[data-vb-image-upload]').forEach(btn => {
            btn.addEventListener('click', () => openImageUpload(section, btn.getAttribute('data-vb-image-upload')));
        });
        container.querySelectorAll('[data-vb-image-clear]').forEach(btn => {
            btn.addEventListener('click', () => {
                const key = btn.getAttribute('data-vb-image-clear');
                applyPendingChange(section.id, s => {
                    ensurePath(s, ['content']);
                    s.content[key] = '';
                });
                renderTraits(state.selectedSectionId);
                postToIframe({ type: 'image-update', sectionId: section.id, fieldKey: key, url: '' });
            });
        });

        container.querySelectorAll('[data-vb-reference]').forEach(cb => {
            cb.addEventListener('change', () => {
                const fieldKey = cb.getAttribute('data-vb-reference');
                const id = parseInt(cb.value, 10);
                applyPendingChange(section.id, s => {
                    ensurePath(s, ['content']);
                    const list = Array.isArray(s.content[fieldKey]) ? s.content[fieldKey].map(Number) : [];
                    const idx = list.indexOf(id);
                    if (cb.checked && idx === -1) list.push(id);
                    if (!cb.checked && idx !== -1) list.splice(idx, 1);
                    s.content[fieldKey] = list;
                });
            });
        });
    }

    /**
     * Commit an inline contenteditable change from the iframe. Mirrors
     * what handleInputChange does for a traits-panel input, but sourced
     * from the iframe's blur/Enter event rather than a form element.
     * Re-renders the traits panel so if the same field is visible there,
     * the input reflects the new value after the user closes/reopens
     * the section.
     */
    function handleInlineEdit(sectionId, fieldKey, locale, value) {
        const section = findSection(sectionId);
        if (!section || !fieldKey) return;

        applyPendingChange(sectionId, s => {
            ensurePath(s, ['content']);
            if (locale) {
                if (typeof s.content[fieldKey] !== 'object' || Array.isArray(s.content[fieldKey])) {
                    s.content[fieldKey] = {};
                }
                s.content[fieldKey][locale] = value;
            } else {
                s.content[fieldKey] = value;
            }
        });

        if (state.selectedSectionId === sectionId) {
            renderTraits(sectionId, fieldKey);
        }
    }

    function handleInputChange(section, input) {
        const key = input.getAttribute('data-vb-input');
        const locale = input.getAttribute('data-vb-locale');
        const value = input.type === 'checkbox' ? input.checked : input.value;

        applyPendingChange(section.id, s => {
            ensurePath(s, ['content']);
            if (locale) {
                if (typeof s.content[key] !== 'object' || Array.isArray(s.content[key])) s.content[key] = {};
                s.content[key][locale] = value;
            } else {
                s.content[key] = value;
            }
        });

        postToIframe({
            type: 'field-update',
            sectionId: section.id,
            fieldKey: key,
            locale: locale || state.config.fallback_locale,
            value,
        });
    }

    function bindStyleEditors(container, section) {
        container.querySelectorAll('[data-vb-style]').forEach(input => {
            input.addEventListener('input', () => handleStyleChange(section, input));
            input.addEventListener('change', () => handleStyleChange(section, input));
        });
        container.querySelectorAll('[data-vb-style-text]').forEach(input => {
            input.addEventListener('input', () => {
                const key = input.getAttribute('data-vb-style-text');
                const colorInput = container.querySelector('input[type="color"][data-vb-style="' + CSS.escape(key) + '"]');
                if (colorInput && /^#[0-9a-fA-F]{3,8}$/.test(input.value)) colorInput.value = input.value;
                handleStyleChange(section, input, key);
            });
        });
    }

    function handleStyleChange(section, input, overrideKey) {
        const key = overrideKey || input.getAttribute('data-vb-style');
        if (!key) return;
        const value = input.value;
        if (input.type === 'color') {
            const textInput = document.querySelector('input[type="text"][data-vb-style-text="' + CSS.escape(key) + '"]');
            if (textInput) textInput.value = value;
        }

        const isResponsive = VB_RESPONSIVE_KEYS.indexOf(key) !== -1;
        const bp = state.activeBreakpoint;

        applyPendingChange(section.id, s => {
            ensurePath(s, ['style']);

            if (!isResponsive) {
                s.style[key] = value;
                return;
            }

            // Responsive key — ensure the slot is an object, then write
            // only the active-breakpoint slice. Empty values clear just
            // that slice; when the object ends up empty we drop it so
            // backwards-compat readers never see `{}`.
            const existing = s.style[key];
            const isObj = existing !== null && typeof existing === 'object' && !Array.isArray(existing);
            if (!isObj) {
                // First per-breakpoint write for this key: promote the
                // current scalar (if any) to the desktop slice so
                // switching to tablet/mobile first and back to desktop
                // does not wipe the legacy scalar value.
                s.style[key] = (existing === null || existing === undefined || existing === '')
                    ? {}
                    : { desktop: existing };
            }
            if (value === null || value === undefined || value === '') {
                delete s.style[key][bp];
                if (Object.keys(s.style[key]).length === 0) {
                    delete s.style[key];
                }
            } else {
                s.style[key][bp] = value;
            }
        });

        postToIframe({ type: 'style-update', sectionId: section.id, style: findSection(section.id).style || {} });
    }

    function bindSectionActions(container, section) {
        container.querySelectorAll('[data-vb-action-section]').forEach(btn => {
            btn.addEventListener('click', () => {
                const action = btn.getAttribute('data-vb-action-section');
                if (action === 'delete') return sectionDelete(section);
                if (action === 'duplicate') return sectionDuplicate(section);
            });
        });
    }

    async function sectionDelete(section) {
        if (!confirm('Delete this section?')) return;
        const url = state.config.routes.destroy_template.replace(/\/0$/, '/' + section.id);
        try {
            const response = await fetch(url, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': state.config.csrf_token,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });
            if (!response.ok) return;
            if (state.selectedSectionId === section.id) {
                state.selectedSectionId = null;
                const traits = document.querySelector('[data-vb-traits]');
                if (traits) setHtml(traits, '<div class="vb-empty"><i class="vb-icon vb-icon-pointer vb-empty-icon"></i><p class="vb-empty-text">Click a section in the preview to edit.</p></div>');
            }
            const data = await response.json();
            applyMutationResponse(data);
        } catch (err) {
            console.error('[VBuilder] Delete failed:', err);
        }
    }

    async function sectionDuplicate(section) {
        const url = state.config.routes.duplicate_template.replace(/\/0\/duplicate$/, '/' + section.id + '/duplicate');
        try {
            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': state.config.csrf_token,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: '_token=' + encodeURIComponent(state.config.csrf_token),
            });
            if (!response.ok) return;
            const data = await response.json();
            applyMutationResponse(data);
        } catch (err) {
            console.error('[VBuilder] Duplicate failed:', err);
        }
    }

    function openImageUpload(section, fieldKey) {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.addEventListener('change', async () => {
            if (!input.files || input.files.length === 0) return;
            const formData = new FormData();
            formData.append('file', input.files[0]);
            formData.append('_token', state.config.csrf_token);
            try {
                const response = await fetch(state.config.routes.upload, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': state.config.csrf_token,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                if (!response.ok) throw new Error('HTTP ' + response.status);
                const data = await response.json();
                applyPendingChange(section.id, s => {
                    ensurePath(s, ['content']);
                    s.content[fieldKey] = data.path;
                });
                renderTraits(state.selectedSectionId);
                postToIframe({ type: 'image-update', sectionId: section.id, fieldKey, url: data.url });
            } catch (err) {
                console.error('[VBuilder] Upload failed:', err);
                alert('Upload failed: ' + err.message);
            }
        });
        input.click();
    }

    // ─────────────────────────────────────────────────────────────
    // Undo / Redo history
    // ─────────────────────────────────────────────────────────────

    /**
     * Push a snapshot of the current sections onto the history stack.
     * Truncates any forward entries (the redo tail) because committing
     * a new change means the user has abandoned that branch. Oldest
     * entries are evicted when the stack exceeds `state.history.max`.
     */
    function pushHistorySnapshot(label) {
        const h = state.history;
        if (h.current < h.entries.length - 1) {
            h.entries.length = h.current + 1;
        }
        h.entries.push({
            sections: JSON.stringify(state.config.sections),
            label: label || '',
        });
        if (h.entries.length > h.max) {
            h.entries.shift();
        } else {
            h.current++;
        }
        updateUndoRedoButtons();
    }

    /**
     * Debounced history capture — used by applyPendingChange. Typing
     * rapidly fires one input event per keystroke, so we wait until
     * the user has been idle `clusterMs` before recording a snapshot.
     * This gives word-level undo granularity instead of one entry per
     * character.
     */
    function scheduleHistoryCapture(label) {
        const h = state.history;
        if (h.clusterTimer) clearTimeout(h.clusterTimer);
        h.clusterTimer = setTimeout(function () {
            h.clusterTimer = null;
            pushHistorySnapshot(label);
        }, h.clusterMs);
    }

    /**
     * Flush a pending cluster capture immediately — called before any
     * undo/redo/save so the "current state" is always represented in
     * the history before we move off it.
     */
    function flushHistoryCluster() {
        const h = state.history;
        if (h.clusterTimer) {
            clearTimeout(h.clusterTimer);
            h.clusterTimer = null;
            pushHistorySnapshot('flush');
        }
    }

    /**
     * Clear history and seed a new baseline. Called after structural
     * operations (create/duplicate/delete/move) because those mutate
     * the server immediately and the bulk save endpoint cannot recreate
     * a row it already deleted — trying to undo across a structural op
     * would desync client and server.
     */
    function resetHistoryBaseline(label) {
        const h = state.history;
        h.entries = [];
        h.current = -1;
        if (h.clusterTimer) {
            clearTimeout(h.clusterTimer);
            h.clusterTimer = null;
        }
        pushHistorySnapshot(label || 'baseline');
    }

    /**
     * Restore state from a snapshot entry. Updates client state, marks
     * the restored sections as pending so the user's Save button still
     * persists them, and replays the snapshot to the iframe via
     * postMessage — style-update for CSS and field-update for every
     * content leaf — so the live preview reflects the restored values
     * immediately WITHOUT a full iframe reload. The previous
     * implementation auto-saved and reloaded the iframe, which caused
     * a full-page flash on every Ctrl+Z.
     *
     * Structural differences (sections added/deleted since the snapshot)
     * are not replayable via these messages — the history is already
     * cleared on every structural op, so undo never crosses a structural
     * anchor and this restore always sees a matching section list in
     * the iframe DOM.
     */
    function restoreHistoryEntry(entry) {
        const restored = JSON.parse(entry.sections);
        state.config.sections = restored;
        state.pending = {
            sections: restored.reduce(function (acc, s) {
                acc[s.id] = {
                    content: s.content || {},
                    style: s.style || {},
                    is_published: !!s.is_published,
                };
                return acc;
            }, {}),
            orderedIds: null,
        };
        markDirty();
        if (state.selectedSectionId && findSection(state.selectedSectionId)) {
            renderTraits(state.selectedSectionId);
        }
        replaySnapshotToIframe(restored);
        updateUndoRedoButtons();
    }

    /**
     * Push every content leaf + style + visibility of each section into
     * the iframe DOM via postMessage. No-op when the iframe has not yet
     * reported ready; the next reload will pick up the canonical state
     * from the server once the user hits Save.
     */
    function replaySnapshotToIframe(sections) {
        sections.forEach(function (s) {
            postToIframe({ type: 'style-update', sectionId: s.id, style: s.style || {} });
            postToIframe({ type: 'visibility-update', sectionId: s.id, fieldKey: null, visible: !!s.is_published });

            const content = s.content || {};
            Object.keys(content).forEach(function (key) {
                const val = content[key];
                if (val !== null && typeof val === 'object' && !Array.isArray(val)) {
                    // Translatable field — locale map.
                    Object.keys(val).forEach(function (locale) {
                        postToIframe({
                            type: 'field-update',
                            sectionId: s.id,
                            fieldKey: key,
                            locale: locale,
                            value: val[locale],
                        });
                    });
                } else {
                    // Scalar content field (non-translatable) — iframe
                    // ignores unknown field keys silently, so broadcasting
                    // every key is cheap even for non-text shapes.
                    postToIframe({
                        type: 'field-update',
                        sectionId: s.id,
                        fieldKey: key,
                        locale: state.config.fallback_locale,
                        value: val,
                    });
                }
            });
        });
    }

    function undo() {
        flushHistoryCluster();
        const h = state.history;
        if (h.current <= 0) return;
        h.current--;
        restoreHistoryEntry(h.entries[h.current]);
    }

    function redo() {
        flushHistoryCluster();
        const h = state.history;
        if (h.current >= h.entries.length - 1) return;
        h.current++;
        restoreHistoryEntry(h.entries[h.current]);
    }

    function updateUndoRedoButtons() {
        const h = state.history;
        const undoBtn = document.querySelector('[data-vb-action="undo"]');
        const redoBtn = document.querySelector('[data-vb-action="redo"]');
        if (undoBtn) undoBtn.disabled = h.current <= 0;
        if (redoBtn) redoBtn.disabled = h.current >= h.entries.length - 1;
    }

    /** Bind Ctrl+Z / Ctrl+Y / Ctrl+Shift+Z. Inputs keep native undo. */
    function bindUndoRedoShortcuts() {
        window.addEventListener('keydown', function (e) {
            const tag = (e.target && e.target.tagName) || '';
            const editable = e.target && e.target.isContentEditable;
            if (editable || tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
            if (!(e.ctrlKey || e.metaKey)) return;
            const key = (e.key || '').toLowerCase();
            if (key === 'z' && !e.shiftKey) {
                e.preventDefault();
                undo();
            } else if ((key === 'z' && e.shiftKey) || key === 'y') {
                e.preventDefault();
                redo();
            }
        });
    }

    // ─────────────────────────────────────────────────────────────
    // Pending state
    // ─────────────────────────────────────────────────────────────

    function applyPendingChange(sectionId, mutator) {
        const section = findSection(sectionId);
        if (!section) return;
        mutator(section);

        if (!state.pending.sections[sectionId]) state.pending.sections[sectionId] = {};
        state.pending.sections[sectionId].content = deepClone(section.content);
        state.pending.sections[sectionId].style = deepClone(section.style);
        state.pending.sections[sectionId].is_published = section.is_published;

        markDirty();
        scheduleHistoryCapture('edit');
    }

    function markDirty() {
        state.dirty = true;
        const badge = document.querySelector('[data-vb-dirty]');
        if (badge) badge.hidden = false;
    }

    function clearDirty() {
        state.dirty = false;
        state.pending = { sections: {}, orderedIds: null };
        const badge = document.querySelector('[data-vb-dirty]');
        if (badge) badge.hidden = true;
    }

    // ─────────────────────────────────────────────────────────────
    // Save
    // ─────────────────────────────────────────────────────────────

    async function save() {
        // If the user clicks Save mid-cluster (before the 400ms idle
        // timer fires), we still want the pre-save state in history so
        // undo after save can return to it.
        flushHistoryCluster();

        const payload = {};
        if (state.pending.orderedIds) payload.ordered_ids = state.pending.orderedIds;
        if (Object.keys(state.pending.sections).length) payload.sections = state.pending.sections;
        if (Object.keys(payload).length === 0) return;

        try {
            const response = await fetch(state.config.routes.save, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': state.config.csrf_token,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            await response.json();
            clearDirty();
            reloadIframe();
        } catch (err) {
            console.error('[VBuilder] Save failed:', err);
            alert('Save failed: ' + err.message);
        }
    }

    function reloadIframe() {
        if (!state.iframe) return;
        const loading = document.querySelector('[data-vb-loading]');
        if (loading) loading.classList.remove('vb-hidden');
        state.iframeReady = false;
        const src = state.iframe.src.split('#')[0];
        state.iframe.src = src + (src.indexOf('?') === -1 ? '?' : '&') + '_vb=' + Date.now();
    }

    // ─────────────────────────────────────────────────────────────
    // Iframe messaging
    // ─────────────────────────────────────────────────────────────

    function postToIframe(message) {
        if (!state.iframe || !state.iframe.contentWindow) return;
        state.iframe.contentWindow.postMessage(
            Object.assign({ source: 'vb-parent' }, message),
            window.location.origin,
        );
    }

    function initIframeMessaging() {
        window.addEventListener('message', event => {
            if (event.origin !== window.location.origin) return;
            const msg = event.data;
            if (!msg || msg.source !== 'vb-iframe') return;

            switch (msg.type) {
                case 'loaded': {
                    state.iframeReady = true;
                    const loading = document.querySelector('[data-vb-loading]');
                    if (loading) loading.classList.add('vb-hidden');
                    break;
                }
                case 'section-clicked':
                    renderTraits(msg.sectionId);
                    break;
                case 'field-focused':
                    // Pass the field key so the traits panel auto-opens
                    // Content tab + scrolls + focuses that specific input.
                    renderTraits(msg.sectionId, msg.fieldKey);
                    break;
                case 'insert-requested':
                    // Iframe `+` inserter clicked → enter "insert mode".
                    // The next block-palette click will create a section
                    // right after `msg.afterSectionId` (null = prepend).
                    enterInsertMode(msg.afterSectionId);
                    break;
                case 'inline-edit':
                    // User double-clicked text in the iframe and committed
                    // a change (blur / Enter). Mirror the edit into pending
                    // state so Save flushes it, and refresh the traits
                    // panel if this section is currently selected.
                    handleInlineEdit(msg.sectionId, msg.fieldKey, msg.locale, msg.value);
                    break;
                case 'duplicate-section':
                    duplicateSection(msg.sectionId);
                    break;
                case 'delete-section':
                    deleteSection(msg.sectionId);
                    break;
                case 'move-up':
                    moveSection(msg.sectionId, 'up');
                    break;
                case 'move-down':
                    moveSection(msg.sectionId, 'down');
                    break;
            }
        });
    }

    // ─────────────────────────────────────────────────────────────
    // Toolbar
    // ─────────────────────────────────────────────────────────────

    function bindToolbar() {
        document.querySelectorAll('[data-vb-action]').forEach(btn => {
            btn.addEventListener('click', () => handleToolbar(btn.getAttribute('data-vb-action')));
        });
        document.querySelectorAll('[data-vb-device]').forEach(btn => {
            btn.addEventListener('click', () => setDevice(btn.getAttribute('data-vb-device'), btn));
        });

        window.addEventListener('beforeunload', event => {
            if (state.dirty) {
                event.preventDefault();
                event.returnValue = '';
            }
        });

        document.addEventListener('keydown', event => {
            const cmd = event.ctrlKey || event.metaKey;
            if (!cmd) return;
            if (event.key.toLowerCase() === 's') {
                event.preventDefault();
                save();
            }
        });
    }

    function handleToolbar(action) {
        if (action === 'save') save();
        else if (action === 'reload') reloadIframe();
        else if (action === 'site-settings') openSiteSettings();
        else if (action === 'navigator') toggleNavigator();
        else if (action === 'revisions') openRevisions();
        else if (action === 'undo') undo();
        else if (action === 'redo') redo();
    }

    // ─────────────────────────────────────────────────────────────
    // Modal + Navigator helpers
    // ─────────────────────────────────────────────────────────────

    /** Open a modal by its data-vb-modal="name" key. */
    function openModal(name) {
        const modal = document.querySelector('[data-vb-modal="' + name + '"]');
        if (!modal) return null;
        modal.classList.add('vb-modal-open');
        modal.setAttribute('aria-hidden', 'false');
        return modal;
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('vb-modal-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    /**
     * Site Settings modal — shows current design tokens (colors + fonts)
     * from state.config.tokens, allows editing, saves via the existing
     * /design-tokens endpoint. Tokens are site-wide and apply to every
     * page rendered with the visual-builder injected CSS variables.
     */
    function openSiteSettings() {
        const modal = openModal('site-settings');
        if (!modal) return;
        const body = modal.querySelector('[data-vb-modal-body]');
        if (!body) return;

        const tokens = state.config.tokens || { colors: [], fonts: [] };
        const parts = [
            '<div class="vb-group"><div class="vb-group-title">Colors</div>',
        ];
        (tokens.colors || []).forEach((c, idx) => {
            parts.push(
                '<div class="vb-field" style="display:flex;gap:8px;align-items:center">',
                '<input type="color" value="', escapeHtml(c.value), '" data-vb-token-color="', idx, '">',
                '<input type="text" class="vb-field-input" style="flex:1" value="', escapeHtml(c.label), '" data-vb-token-color-label="', idx, '">',
                '<code style="font-size:11px;color:#6b7280">', escapeHtml(c.id), '</code>',
                '</div>',
            );
        });
        parts.push('</div><div class="vb-group"><div class="vb-group-title">Fonts</div>');
        const FONT_PRESETS = [
            { label: 'System UI', value: 'system-ui, -apple-system, sans-serif' },
            { label: 'Roboto', value: "'Roboto', sans-serif" },
            { label: 'Open Sans', value: "'Open Sans', sans-serif" },
            { label: 'Lato', value: "'Lato', sans-serif" },
            { label: 'Montserrat', value: "'Montserrat', sans-serif" },
            { label: 'Poppins', value: "'Poppins', sans-serif" },
            { label: 'Inter', value: "'Inter', sans-serif" },
            { label: 'Nunito', value: "'Nunito', sans-serif" },
            { label: 'Raleway', value: "'Raleway', sans-serif" },
            { label: 'Oswald', value: "'Oswald', sans-serif" },
            { label: 'Source Sans 3', value: "'Source Sans 3', sans-serif" },
            { label: 'Playfair Display', value: "'Playfair Display', serif" },
            { label: 'Merriweather', value: "'Merriweather', serif" },
            { label: 'Lora', value: "'Lora', serif" },
            { label: 'Georgia', value: 'Georgia, serif' },
            { label: 'Times New Roman', value: "'Times New Roman', serif" },
            { label: 'Arial', value: 'Arial, sans-serif' },
            { label: 'Helvetica', value: "'Helvetica Neue', Helvetica, sans-serif" },
            { label: 'Courier New', value: "'Courier New', monospace" },
        ];
        (tokens.fonts || []).forEach((f, idx) => {
            const currentValue = f.family || '';
            const isPreset = FONT_PRESETS.some(p => p.value === currentValue);
            const optionsHtml = FONT_PRESETS.map(p =>
                '<option value="' + escapeHtml(p.value) + '" style="font-family:' + escapeHtml(p.value) + '"'
                + (p.value === currentValue ? ' selected' : '') + '>' + escapeHtml(p.label) + '</option>'
            ).join('')
            + '<option value="__custom__"' + (isPreset ? '' : ' selected') + '>Custom…</option>';

            parts.push(
                '<div class="vb-field">',
                '<label class="vb-field-label"><span>', escapeHtml(f.label), '</span><code style="font-size:10px;color:#6b7280">', escapeHtml(f.id), '</code></label>',
                '<select class="vb-field-input" data-vb-token-font-select="', idx, '" style="font-family:', escapeHtml(currentValue), '">', optionsHtml, '</select>',
                '<input type="text" class="vb-field-input" value="', escapeHtml(currentValue), '" data-vb-token-font-family="', idx, '" placeholder="\'Roboto\', sans-serif" style="margin-top:6px;', (isPreset ? 'display:none' : ''), '">',
                '</div>',
            );
        });
        parts.push('</div>');

        setHtml(body, parts.join(''));

        // Font dropdown → syncs to hidden text input that save logic reads
        body.querySelectorAll('[data-vb-token-font-select]').forEach(function (sel) {
            const idx = sel.getAttribute('data-vb-token-font-select');
            const textInput = body.querySelector('[data-vb-token-font-family="' + idx + '"]');
            sel.addEventListener('change', function () {
                if (sel.value === '__custom__') {
                    if (textInput) { textInput.style.display = ''; textInput.focus(); }
                } else {
                    if (textInput) { textInput.value = sel.value; textInput.style.display = 'none'; }
                    sel.style.fontFamily = sel.value;
                }
            });
        });

        // Save button
        const saveBtn = modal.querySelector('[data-vb-site-settings-save]');
        if (saveBtn) {
            saveBtn.onclick = async function () {
                const colors = Array.from(body.querySelectorAll('[data-vb-token-color]')).map((input, idx) => ({
                    id: tokens.colors[idx].id,
                    label: body.querySelector('[data-vb-token-color-label="' + idx + '"]').value,
                    value: input.value,
                }));
                const fonts = Array.from(body.querySelectorAll('[data-vb-token-font-family]')).map((input, idx) => ({
                    id: tokens.fonts[idx].id,
                    label: tokens.fonts[idx].label,
                    family: input.value,
                }));

                try {
                    const response = await fetch(state.config.routes.design_tokens, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': state.config.csrf_token,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ colors, fonts }),
                    });
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    const data = await response.json();
                    if (data.tokens) state.config.tokens = data.tokens;
                    closeModal(modal);
                    reloadIframe();
                } catch (err) {
                    alert('Site Settings save failed: ' + err.message);
                }
            };
        }

        // Close handlers
        modal.querySelectorAll('[data-vb-modal-close]').forEach(function (btn) {
            btn.onclick = function () { closeModal(modal); };
        });
    }

    /**
     * Navigator sidebar — vertical list of sections with icon + label.
     * Click an entry to scroll the iframe to that section and select it.
     */
    function toggleNavigator() {
        const nav = document.querySelector('[data-vb-navigator]');
        if (!nav) return;
        const isOpen = nav.getAttribute('aria-hidden') !== 'true';
        if (isOpen) {
            nav.setAttribute('aria-hidden', 'true');
            nav.classList.remove('vb-navigator-open');
            return;
        }

        const body = nav.querySelector('[data-vb-navigator-body]');
        if (body) {
            const sections = (state.config.sections || []).slice().sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
            if (sections.length === 0) {
                setHtml(body, '<div class="vb-empty"><p class="vb-empty-text">No sections yet. Use the left panel to add one.</p></div>');
            } else {
                const html = sections.map((s) => {
                    const type = state.config.types[s.type];
                    const label = type ? type.label : s.type;
                    return '<button type="button" class="vb-navigator-item" data-vb-nav-section-id="' + s.id + '">'
                        + '<span class="vb-navigator-item-label">' + escapeHtml(label) + '</span>'
                        + '<span class="vb-navigator-item-id">#' + s.id + '</span>'
                        + (s.is_published ? '' : ' <span class="vb-badge-draft">draft</span>')
                        + '</button>';
                }).join('');
                setHtml(body, html);

                body.querySelectorAll('[data-vb-nav-section-id]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const id = parseInt(btn.getAttribute('data-vb-nav-section-id'), 10);
                        postToIframe({ type: 'highlight-section', sectionId: id });
                        renderTraits(id);
                    });
                });
            }
        }

        nav.setAttribute('aria-hidden', 'false');
        nav.classList.add('vb-navigator-open');

        const closeBtn = nav.querySelector('[data-vb-navigator-close]');
        if (closeBtn) closeBtn.onclick = function () { toggleNavigator(); };
    }

    /**
     * Revisions modal — server-side history is scaffolded but the
     * persistence layer lands in v0.4. Placeholder keeps the button
     * from looking broken; when the v0.4 endpoint exists this fills in.
     */
    function openRevisions() {
        const modal = openModal('revisions');
        if (!modal) return;
        const body = modal.querySelector('[data-vb-modal-body]');
        if (body) {
            setHtml(body, '<div class="vb-empty"><p class="vb-empty-text" style="padding:32px 16px;text-align:center;color:#6b7280">Revisions history lands in v0.4 — track every save + restore any prior version. Not yet available.</p></div>');
        }
        modal.querySelectorAll('[data-vb-modal-close]').forEach(function (btn) {
            btn.onclick = function () { closeModal(modal); };
        });
    }

    function setDevice(mode, btn) {
        const viewport = document.querySelector('[data-vb-viewport]');
        if (!viewport) return;
        viewport.classList.remove('vb-device-tablet', 'vb-device-mobile');
        if (mode === 'tablet' || mode === 'mobile') viewport.classList.add('vb-device-' + mode);
        document.querySelectorAll('[data-vb-device]').forEach(b => b.classList.remove('vb-device-active'));
        btn.classList.add('vb-device-active');

        // Map the preview device to an edit breakpoint: the monitor
        // button (no explicit mode → 'desktop') edits desktop values,
        // tablet / mobile edit their namesakes. This keeps the editor's
        // write target aligned with the device the user is previewing.
        const nextBp = (mode === 'tablet' || mode === 'mobile') ? mode : 'desktop';
        if (nextBp !== state.activeBreakpoint) {
            state.activeBreakpoint = nextBp;
            // Re-render traits so responsive field values and badges
            // reflect the newly active breakpoint. No-op when nothing
            // is selected yet.
            if (state.selectedSectionId !== null && findSection(state.selectedSectionId)) {
                renderTraits(state.selectedSectionId);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Boot
    // ─────────────────────────────────────────────────────────────

    function boot() {
        state.config = readBootstrap();
        if (!state.config) return;

        state.iframe = document.querySelector('[data-vb-iframe]');

        renderBlockPalette();
        bindToolbar();
        bindUndoRedoShortcuts();
        initIframeMessaging();

        // Seed the history with the initial state so the very first
        // undo has somewhere to return to.
        resetHistoryBaseline('initial');

        // Belt-and-suspenders loading overlay dismissal: the iframe posts
        // a 'loaded' message to the parent when its DOM is ready, but if
        // the iframe loads before this parent listener is wired (common
        // on the very first page load), that message is missed and the
        // overlay hangs forever until the user clicks "Reload Preview".
        //
        // The browser's native `load` event fires for every iframe src
        // change (initial load + every reloadIframe() call), so use it
        // as a fallback. A short delay gives the inject script time to
        // register its handlers inside the iframe before we reveal.
        if (state.iframe) {
            state.iframe.addEventListener('load', function () {
                setTimeout(function () {
                    state.iframeReady = true;
                    const loading = document.querySelector('[data-vb-loading]');
                    if (loading) loading.classList.add('vb-hidden');
                }, 150);
            });

            // If iframe was ALREADY loaded by the time parent JS ran, its
            // 'load' event has already fired and won't fire again. Check
            // readyState and dismiss immediately in that race.
            const iframeDoc = state.iframe.contentDocument;
            if (iframeDoc && iframeDoc.readyState === 'complete') {
                setTimeout(function () {
                    state.iframeReady = true;
                    const loading = document.querySelector('[data-vb-loading]');
                    if (loading) loading.classList.add('vb-hidden');
                }, 150);
            }
        }

        window.VBuilder = { state, save, renderTraits };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
