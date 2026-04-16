{{--
    Visual Builder — admin editor shell.

    Rendered by <x-visual-builder::editor :target="$model" /> in host app
    views. Self-contained — brings its own CSS/JS via @push('head'), no
    layout assumptions beyond standard Laravel @stack('head') / @stack('scripts').

    If the host layout doesn't define those stacks, publish the views
    (php artisan vendor:publish --tag=visual-builder-views) and drop the
    <link> / <script> tags inline wherever fits.

    Contract:
      $bootstrap  array — payload consumed by visual-builder.js on boot
      $target     Model — the buildable parent (for display only)
      $sections   Collection<BuilderSection>
      $types      array<string, SectionTypeInterface>
      $tokens     array — design tokens (unused in this view, client reads bootstrap)
--}}
@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/laravel-visual-builder/css/visual-builder.css') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

<div class="vb-shell" data-vb-shell>

    {{-- Left panel: block palette (adds new sections) --}}
    <aside class="vb-panel vb-panel-left" aria-label="Section blocks">
        <div class="vb-panel-header">
            <i class="vb-icon vb-icon-blocks" aria-hidden="true"></i>
            <span>Add Section</span>
        </div>
        <div class="vb-panel-body" data-vb-blocks></div>
        <div class="vb-panel-footer">
            <small>Click a block to add it</small>
        </div>
    </aside>

    {{-- Center: live preview iframe + toolbar --}}
    <main class="vb-canvas-wrap" aria-label="Live preview">
        <div class="vb-canvas-toolbar">
            <div class="vb-canvas-toolbar-left">
                <span class="vb-canvas-title">Live Preview</span>
                <span class="vb-dirty-badge" data-vb-dirty hidden>
                    <span class="vb-dot"></span> Unsaved
                </span>
            </div>
            <div class="vb-canvas-toolbar-right">
                <button type="button" class="vb-btn vb-btn-ghost" data-vb-action="site-settings" title="Global colors &amp; fonts">
                    <i class="vb-icon vb-icon-palette" aria-hidden="true"></i>
                    <span class="vb-sr">Site Settings</span>
                </button>
                <button type="button" class="vb-btn vb-btn-ghost" data-vb-action="navigator" title="Navigator">
                    <i class="vb-icon vb-icon-list" aria-hidden="true"></i>
                    <span class="vb-sr">Navigator</span>
                </button>
                <button type="button" class="vb-btn vb-btn-ghost" data-vb-action="revisions" title="Revisions">
                    <i class="vb-icon vb-icon-clock" aria-hidden="true"></i>
                    <span class="vb-sr">Revisions</span>
                </button>
                <span class="vb-toolbar-sep" aria-hidden="true"></span>
                <button type="button" class="vb-btn vb-btn-ghost vb-device-btn vb-device-active" data-vb-device="desktop" title="Desktop">
                    <i class="vb-icon vb-icon-desktop" aria-hidden="true"></i>
                </button>
                <button type="button" class="vb-btn vb-btn-ghost vb-device-btn" data-vb-device="tablet" title="Tablet">
                    <i class="vb-icon vb-icon-tablet" aria-hidden="true"></i>
                </button>
                <button type="button" class="vb-btn vb-btn-ghost vb-device-btn" data-vb-device="mobile" title="Mobile">
                    <i class="vb-icon vb-icon-mobile" aria-hidden="true"></i>
                </button>
                <span class="vb-toolbar-sep" aria-hidden="true"></span>
                <button type="button" class="vb-btn vb-btn-ghost" data-vb-action="undo" disabled title="Undo (Ctrl+Z)">
                    <i class="vb-icon vb-icon-undo" aria-hidden="true"></i>
                </button>
                <button type="button" class="vb-btn vb-btn-ghost" data-vb-action="redo" disabled title="Redo (Ctrl+Shift+Z)">
                    <i class="vb-icon vb-icon-redo" aria-hidden="true"></i>
                </button>
                <button type="button" class="vb-btn vb-btn-ghost" data-vb-action="reload" title="Reload preview">
                    <i class="vb-icon vb-icon-refresh" aria-hidden="true"></i>
                </button>
                <button type="button" class="vb-btn vb-btn-primary" data-vb-action="save">
                    <i class="vb-icon vb-icon-save" aria-hidden="true"></i>
                    <span>Save</span>
                </button>
            </div>
        </div>

        <div class="vb-preview-viewport" data-vb-viewport>
            <div class="vb-loading" data-vb-loading>
                <div class="vb-spinner" aria-hidden="true"></div>
                <div class="vb-loading-text">Loading preview…</div>
            </div>
            @php
                $previewUrl = method_exists($target, 'builderPreviewUrl')
                    ? $target->builderPreviewUrl()
                    : url($target->getTable() . '/' . $target->getKey());
                $builderParam = (string) config('visual-builder.builder_query_param', 'builder');
                $previewUrl .= (str_contains($previewUrl, '?') ? '&' : '?') . $builderParam . '=1';
            @endphp
            <iframe
                data-vb-iframe
                class="vb-preview-iframe"
                src="{{ $previewUrl }}"
                title="Preview of {{ class_basename($target) }} #{{ $target->getKey() }}"></iframe>
        </div>
    </main>

    {{-- Right panel: traits (Content/Style/Advanced tabs) --}}
    <aside class="vb-panel vb-panel-right" aria-label="Section settings">
        <div class="vb-panel-header">
            <i class="vb-icon vb-icon-sliders" aria-hidden="true"></i>
            <span>Section Settings</span>
        </div>
        <div class="vb-panel-body" data-vb-traits>
            <div class="vb-empty">
                <i class="vb-icon vb-icon-pointer vb-empty-icon" aria-hidden="true"></i>
                <p class="vb-empty-text">Click a section in the preview to edit its fields</p>
            </div>
        </div>
    </aside>

    {{-- Modals + Navigator + Context menu (rendered once, opened by JS) --}}
    <div class="vb-modal" data-vb-modal="site-settings" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="vb-modal-backdrop" data-vb-modal-close></div>
        <div class="vb-modal-dialog">
            <header class="vb-modal-header">
                <h3><i class="vb-icon vb-icon-palette" aria-hidden="true"></i> Site Settings</h3>
                <button type="button" class="vb-modal-close" data-vb-modal-close aria-label="Close">×</button>
            </header>
            <div class="vb-modal-body" data-vb-modal-body></div>
            <footer class="vb-modal-footer">
                <button type="button" class="vb-btn vb-btn-ghost" data-vb-modal-close>Cancel</button>
                <button type="button" class="vb-btn vb-btn-primary" data-vb-site-settings-save>
                    <i class="vb-icon vb-icon-save" aria-hidden="true"></i> Save
                </button>
            </footer>
        </div>
    </div>

    <div class="vb-modal" data-vb-modal="revisions" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="vb-modal-backdrop" data-vb-modal-close></div>
        <div class="vb-modal-dialog">
            <header class="vb-modal-header">
                <h3><i class="vb-icon vb-icon-clock" aria-hidden="true"></i> Revisions</h3>
                <button type="button" class="vb-modal-close" data-vb-modal-close aria-label="Close">×</button>
            </header>
            <div class="vb-modal-body" data-vb-modal-body></div>
        </div>
    </div>

    <aside class="vb-navigator" data-vb-navigator aria-hidden="true">
        <header class="vb-navigator-header">
            <span><i class="vb-icon vb-icon-list" aria-hidden="true"></i> Navigator</span>
            <button type="button" class="vb-navigator-close" data-vb-navigator-close aria-label="Close">×</button>
        </header>
        <div class="vb-navigator-body" data-vb-navigator-body></div>
    </aside>

    <div class="vb-context-menu" data-vb-context-menu role="menu"></div>
</div>

{{-- Data bootstrap — picked up by visual-builder.js on DOMContentLoaded --}}
<script type="application/json" data-vb-bootstrap>@json($bootstrap)</script>

@push('scripts')
    <script src="{{ asset('vendor/laravel-visual-builder/js/visual-builder.js') }}" defer></script>
@endpush
