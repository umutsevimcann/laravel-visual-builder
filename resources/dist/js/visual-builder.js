/**
 * Visual Builder — admin editor client.
 *
 * Single-file vanilla JS (no jQuery, no framework). IIFE-scoped to avoid
 * global pollution; exposes window.VBuilder only for debugging.
 *
 * Architecture:
 *   - Reads JSON bootstrap payload from <script data-vb-bootstrap>.
 *   - Boots iframe messaging (postMessage to/from the preview frame).
 *   - Renders block palette in left panel, traits form in right panel.
 *   - Owns the pending-updates buffer — nothing persists until Save click.
 *
 * Security:
 *   - All dynamic strings pass through escapeHtml() before innerHTML/template.
 *   - postMessage enforces targetOrigin = window.location.origin.
 *   - CSRF token from bootstrap injected as X-CSRF-TOKEN on every fetch.
 *
 * This file is intentionally minimal — the full admin interactions (tabs,
 * sliders, color pickers, modals, navigator, revisions, context menu,
 * drag-reorder) are implemented incrementally. Phase D ships a working
 * foundation; Phase E adds test coverage; future minor releases deepen
 * the UI feature set.
 */
(function () {
    'use strict';

    function escapeHtml(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Replace an element's children with parsed HTML — avoids bare
     * innerHTML assignment and clears stale event listeners at once.
     */
    function setHtml(el, html) {
        if (!el) return;
        el.replaceChildren();
        el.insertAdjacentHTML('afterbegin', html);
    }

    var VBuilder = {
        config: null,
        iframe: null,
        iframeReady: false,
        dirty: false,
        pendingUpdates: {},
        pendingOrderedIds: null,
        selectedSectionId: null,
        origin: window.location.origin,

        boot: function () {
            var node = document.querySelector('[data-vb-bootstrap]');
            if (!node) {
                console.error('[VBuilder] Bootstrap payload missing');
                return;
            }
            try {
                this.config = JSON.parse(node.textContent || '{}');
            } catch (e) {
                console.error('[VBuilder] Bootstrap parse failed:', e);
                return;
            }

            this.iframe = document.querySelector('[data-vb-iframe]');
            this.renderBlockPalette();
            this.bindToolbar();
            this.listenIframe();

            window.VBuilder = this;
        },

        /**
         * Left panel: one card per registered section type.
         */
        renderBlockPalette: function () {
            var container = document.querySelector('[data-vb-blocks]');
            if (!container) return;

            var existingTypes = {};
            (this.config.sections || []).forEach(function (s) { existingTypes[s.type] = true; });

            var html = Object.keys(this.config.types || {}).map(function (key) {
                var type = this.config.types[key];
                var disabled = !type.allows_multiple && existingTypes[key];
                return [
                    '<button type="button" class="vb-block" data-vb-block="' + escapeHtml(key) + '"',
                    disabled ? ' disabled' : '',
                    '>',
                    '<span class="vb-block-icon" aria-hidden="true"></span>',
                    '<span class="vb-block-info">',
                    '<span class="vb-block-title">' + escapeHtml(type.label) + '</span>',
                    '<span class="vb-block-desc">' + escapeHtml(type.description || '') + '</span>',
                    '</span>',
                    '</button>',
                ].join('');
            }.bind(this)).join('');

            setHtml(container, html);

            container.querySelectorAll('[data-vb-block]:not([disabled])').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    this.createSection(btn.getAttribute('data-vb-block'));
                }.bind(this));
            }.bind(this));
        },

        bindToolbar: function () {
            var self = this;

            document.querySelectorAll('[data-vb-action]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    self.handleToolbarAction(btn.getAttribute('data-vb-action'));
                });
            });

            document.querySelectorAll('[data-vb-device]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    self.setDevice(btn.getAttribute('data-vb-device'), btn);
                });
            });

            window.addEventListener('beforeunload', function (e) {
                if (self.dirty) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });

            // Keyboard shortcuts — standard editor conventions
            document.addEventListener('keydown', function (e) {
                var cmd = e.ctrlKey || e.metaKey;
                if (!cmd) return;
                var key = e.key.toLowerCase();
                if (key === 's') {
                    e.preventDefault();
                    self.save();
                }
            });
        },

        handleToolbarAction: function (action) {
            switch (action) {
                case 'save': this.save(); break;
                case 'reload': this.reloadIframe(); break;
                case 'site-settings': this.openModal('site-settings'); break;
                case 'revisions': this.openModal('revisions'); break;
                case 'navigator': this.toggleNavigator(); break;
            }
        },

        setDevice: function (mode, btn) {
            var viewport = document.querySelector('[data-vb-viewport]');
            if (!viewport) return;
            viewport.classList.remove('vb-device-tablet', 'vb-device-mobile');
            if (mode === 'tablet' || mode === 'mobile') {
                viewport.classList.add('vb-device-' + mode);
            }
            document.querySelectorAll('[data-vb-device]').forEach(function (b) {
                b.classList.remove('vb-device-active');
            });
            btn.classList.add('vb-device-active');
        },

        listenIframe: function () {
            var self = this;
            window.addEventListener('message', function (e) {
                if (e.origin !== self.origin) return;
                var msg = e.data;
                if (!msg || msg.source !== 'vb-iframe') return;

                if (msg.type === 'loaded') {
                    self.iframeReady = true;
                    var loading = document.querySelector('[data-vb-loading]');
                    if (loading) loading.classList.add('vb-hidden');
                } else if (msg.type === 'section-clicked' || msg.type === 'field-focused') {
                    self.selectSection(msg.sectionId);
                }
            });
        },

        selectSection: function (sectionId) {
            this.selectedSectionId = sectionId;
            var section = (this.config.sections || []).filter(function (s) {
                return s.id === sectionId;
            })[0];
            if (!section) return;

            var type = this.config.types[section.type];
            var container = document.querySelector('[data-vb-traits]');
            if (!container) return;

            setHtml(container, [
                '<div class="vb-section-header">',
                '<div class="vb-section-header-icon" aria-hidden="true"></div>',
                '<div>',
                '<div class="vb-section-header-label">' + escapeHtml((type && type.label) || section.type) + '</div>',
                '<div class="vb-section-header-meta">#' + section.id + ' · ' + escapeHtml(section.type) + '</div>',
                '</div>',
                '</div>',
                '<div class="vb-empty"><p class="vb-empty-text">Field editors render here — minimal shell ships in v0.1; extended widgets arrive in subsequent releases.</p></div>',
            ].join(''));
        },

        /**
         * Create a new section via POST to the bootstrap store route.
         * On success, reloads the page — the new section shows up in both
         * the block palette (now "Added") and the iframe.
         */
        createSection: function (typeKey) {
            var self = this;
            fetch(self.config.routes.store, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': self.config.csrf_token,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: 'type=' + encodeURIComponent(typeKey) + '&_token=' + encodeURIComponent(self.config.csrf_token),
                redirect: 'manual',
            }).then(function (response) {
                if (response.status >= 200 && response.status < 400) {
                    window.location.reload();
                } else {
                    console.error('[VBuilder] Create section failed:', response.status);
                }
            }).catch(function (err) {
                console.error('[VBuilder] Create section error:', err);
            });
        },

        /**
         * POST pending state to the save endpoint.
         */
        save: function () {
            var self = this;
            var payload = {};
            if (self.pendingOrderedIds && self.pendingOrderedIds.length) payload.ordered_ids = self.pendingOrderedIds;
            if (Object.keys(self.pendingUpdates).length) payload.sections = self.pendingUpdates;

            if (!payload.ordered_ids && !payload.sections) return;

            fetch(self.config.routes.save, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': self.config.csrf_token,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            }).then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            }).then(function () {
                self.pendingUpdates = {};
                self.pendingOrderedIds = null;
                self.dirty = false;
                var badge = document.querySelector('[data-vb-dirty]');
                if (badge) badge.hidden = true;
                self.reloadIframe();
            }).catch(function (err) {
                console.error('[VBuilder] Save failed:', err);
            });
        },

        reloadIframe: function () {
            if (!this.iframe) return;
            var loading = document.querySelector('[data-vb-loading]');
            if (loading) loading.classList.remove('vb-hidden');
            this.iframeReady = false;
            var src = this.iframe.src.split('#')[0];
            this.iframe.src = src + (src.indexOf('?') === -1 ? '?' : '&') + '_vb=' + Date.now();
        },

        postToIframe: function (message) {
            if (!this.iframe || !this.iframe.contentWindow) return;
            var payload = Object.assign({ source: 'vb-parent' }, message);
            this.iframe.contentWindow.postMessage(payload, this.origin);
        },

        openModal: function (name) {
            var modal = document.querySelector('[data-vb-modal="' + name + '"]');
            if (modal) modal.classList.add('vb-modal-open');
            document.querySelectorAll('[data-vb-modal-close]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    modal.classList.remove('vb-modal-open');
                }, { once: true });
            });
        },

        toggleNavigator: function () {
            var panel = document.querySelector('[data-vb-navigator]');
            if (panel) panel.classList.toggle('vb-navigator-open');
        },
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', VBuilder.boot.bind(VBuilder));
    } else {
        VBuilder.boot();
    }
})();
