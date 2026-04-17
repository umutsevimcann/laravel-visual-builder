<?php

declare(strict_types=1);

/**
 * Visual Builder — publishable configuration.
 *
 * Publish with:
 *   php artisan vendor:publish --tag="visual-builder-config"
 *
 * Customize any entry in your app. Missing keys fall back to package defaults.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Route registration
    |--------------------------------------------------------------------------
    |
    | Package routes are auto-registered under the configured prefix and
    | middleware. Disable by setting `enabled` to false and register your
    | own routes pointing to the package controllers.
    |
    */
    'routes' => [
        'enabled' => true,
        'prefix' => 'visual-builder',
        'name_prefix' => 'visual-builder.',
        'middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | Optional Laravel Gate name. If set, the package controller will call
    | Gate::authorize() before any write operation. Return true for authorized
    | users in your app's AuthServiceProvider.
    |
    */
    'authorization_gate' => null,

    /*
    |--------------------------------------------------------------------------
    | Table names
    |--------------------------------------------------------------------------
    |
    | Customize the underlying table names. Change before running migrations
    | if you need to avoid collisions with existing tables.
    |
    */
    'tables' => [
        'sections' => 'builder_sections',
        'revisions' => 'builder_revisions',
        'templates' => 'builder_templates',
    ],

    /*
    |--------------------------------------------------------------------------
    | Design tokens
    |--------------------------------------------------------------------------
    |
    | Default global colors and fonts. Users can change these via the Site
    | Settings modal in the builder UI; the stored values in the settings
    | row override these defaults.
    |
    */
    'design_tokens' => [
        'settings_key' => 'visual_builder_design_tokens',
        'cache_ttl' => 600, // seconds
        'defaults' => [
            'colors' => [
                ['id' => 'primary',   'label' => 'Primary',   'value' => '#2563eb'],
                ['id' => 'secondary', 'label' => 'Secondary', 'value' => '#10b981'],
                ['id' => 'text',      'label' => 'Text',      'value' => '#1f2937'],
                ['id' => 'accent',    'label' => 'Accent',    'value' => '#f59e0b'],
            ],
            'fonts' => [
                ['id' => 'heading', 'label' => 'Heading', 'family' => "'Roboto', sans-serif"],
                ['id' => 'body',    'label' => 'Body',    'family' => "'Open Sans', sans-serif"],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Media uploads
    |--------------------------------------------------------------------------
    |
    | The default MediaService uploads to the configured disk under the given
    | directory. Swap out the MediaServiceInterface binding to plug in your
    | own file storage (S3, Spatie Media Library, custom CDN, etc.).
    |
    */
    'media' => [
        'disk' => 'public',
        'directory' => 'visual-builder',
        'allowed_mimes' => ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'],
        'max_size_kb' => 8192,
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization
    |--------------------------------------------------------------------------
    |
    | Locales available for translatable fields. Defaults to `app.locales`
    | if present, otherwise falls back to ['en'].
    |
    */
    'locales' => null, // null → auto-detect from app config

    /*
    |--------------------------------------------------------------------------
    | Builder mode parameter
    |--------------------------------------------------------------------------
    |
    | The iframe query parameter that activates builder injection on the
    | frontend (data-vb-editable attributes, contextmenu, etc.).
    |
    */
    'builder_query_param' => 'builder',

    /*
    |--------------------------------------------------------------------------
    | Sanitization
    |--------------------------------------------------------------------------
    |
    | HTML fields run through the configured sanitizer. Swap
    | SanitizerInterface to use HTMLPurifier, DOMPurify-PHP, custom, etc.
    |
    */
    'sanitizer' => [
        'default' => 'purifier', // registered alias in the service container
    ],

    /*
    |--------------------------------------------------------------------------
    | Revisions
    |--------------------------------------------------------------------------
    |
    | Local (browser) revisions are always available. Set `server_enabled` to
    | true to also persist server-side revisions in the revisions table.
    |
    */
    'revisions' => [
        'server_enabled' => false,
        'max_per_target' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | View namespace
    |--------------------------------------------------------------------------
    |
    | Change if you need to avoid namespace collisions. Default: 'visual-builder'.
    | Published views live in resources/views/vendor/visual-builder/.
    |
    */
    'view_namespace' => 'visual-builder',

    /*
    |--------------------------------------------------------------------------
    | Atomic widgets (Elementor-style building blocks)
    |--------------------------------------------------------------------------
    |
    | The package ships a set of small, single-purpose section types
    | (Heading, Paragraph, Button, Spacer, Divider — more arrive in the
    | 0.4.x line) under `Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\*`.
    |
    | Registration is OPT-IN. Host apps that already ship their own
    | domain-specific sections (Hero, AboutBox, PricingTable …) leave
    | `enabled` as false to keep their block palette clean. Set it to
    | true — and optionally trim the `list` — to surface the widgets in
    | the admin block palette and expose them to content editors.
    |
    */
    'widgets' => [
        'enabled' => false,
        // Names correspond to each widget's key() method. Unknown keys
        // are silently ignored so removing one from this list disables
        // it without touching the service provider.
        'list' => [
            'heading',
            'paragraph',
            'button',
            'spacer',
            'divider',
            'image',
            'video',
            'icon',
            'icon_box',
            'columns',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Responsive breakpoints
    |--------------------------------------------------------------------------
    |
    | Maximum viewport widths for the tablet and mobile breakpoints, in
    | pixels. These values drive the `@media (max-width: Npx)` queries
    | emitted by the @vbSectionStyles directive AND the iframe preview's
    | live breakpoint detection (window.innerWidth).
    |
    | Defaults match Bootstrap 5 boundaries so a host app running Bootstrap
    | or Tailwind doesn't need to override anything. tablet_max MUST be
    | strictly greater than mobile_max — BreakpointStyleResolver throws
    | at construction otherwise.
    |
    */
    'breakpoints' => [
        'tablet_max' => 1023, // applies at <= 1023px
        'mobile_max' => 767,  // applies at <=  767px
    ],

];
