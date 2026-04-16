# Laravel Visual Builder

[![Latest Version on Packagist](https://img.shields.io/packagist/v/umutsevimcann/laravel-visual-builder.svg?style=flat-square)](https://packagist.org/packages/umutsevimcann/laravel-visual-builder)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/umutsevimcann/laravel-visual-builder/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/umutsevimcann/laravel-visual-builder/actions/workflows/run-tests.yml)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/umutsevimcann/laravel-visual-builder/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/umutsevimcann/laravel-visual-builder/actions/workflows/fix-php-code-style-issues.yml)
[![Codecov](https://img.shields.io/codecov/c/github/umutsevimcann/laravel-visual-builder?style=flat-square&token=)](https://codecov.io/gh/umutsevimcann/laravel-visual-builder)
[![Total Downloads](https://img.shields.io/packagist/dt/umutsevimcann/laravel-visual-builder.svg?style=flat-square)](https://packagist.org/packages/umutsevimcann/laravel-visual-builder)
[![License](https://img.shields.io/packagist/l/umutsevimcann/laravel-visual-builder.svg?style=flat-square)](LICENSE.md)

**Modern visual page builder for Laravel.** Drag-drop section editor with live iframe preview, global design tokens, entrance animations, copy/paste, revisions, and responsive controls. Polymorphic — attach to any Eloquent model.

> Framework-agnostic UI: no AdminLTE/Filament/Bootstrap assumptions. Plays with any admin layout via a publishable Blade component.

## Feature Highlights

- **Live iframe preview** — click an element, edit on the right, see changes instantly
- **3-tab panel** — Content / Style / Advanced
- **Full field library** — text, HTML, toggle, select, link, image (upload), reference (multi-select), repeater
- **Element-level styling** — typography, colors, spacing, border radius, shadows per-field
- **Global Design System** — site-wide colors + fonts via CSS variables
- **Motion effects** — 10 entrance animations, IntersectionObserver-driven, accessibility-aware
- **Copy/paste + context menu** — right-click to duplicate, copy styles, paste
- **Undo/redo** — 50-step history, `Ctrl+Z` / `Ctrl+Shift+Z` / `Ctrl+S`
- **Responsive preview** — desktop/tablet/mobile viewport modes
- **Revisions** — timestamped snapshots, restore any version
- **Polymorphic** — attach builder to Pages, Posts, Products, any Eloquent model

## Philosophy

This package provides the **framework** for a visual builder. You bring the **content schema**:

- Register your own `SectionType` classes (Hero, Gallery, Contact Form, etc.)
- Implement `MediaServiceInterface` with your file storage strategy
- Implement `AuthorizationInterface` with your permission system
- Customize Blade views if defaults don't match your admin layout

Two example section types ship with the package to get you started.

## Requirements

- PHP 8.2+
- Laravel 11.0 or 12.0
- MySQL 5.7+ / PostgreSQL 10+ / SQLite 3.9+ (JSON column support)

## Installation

```bash
composer require umutsevimcann/laravel-visual-builder
```

Publish config, migrations, and assets:

```bash
php artisan vendor:publish --tag="visual-builder-config"
php artisan vendor:publish --tag="visual-builder-migrations"
php artisan vendor:publish --tag="visual-builder-assets"
php artisan migrate
```

Optional — publish views for full control:

```bash
php artisan vendor:publish --tag="visual-builder-views"
```

## Quick Start

### 1. Make a model "buildable"

```php
use Illuminate\Database\Eloquent\Model;
use Umutsevimcann\VisualBuilder\Support\Concerns\HasVisualBuilder;

class Page extends Model
{
    use HasVisualBuilder;
}
```

### 2. Define your own section types

```php
// app/Builder/HeroSection.php
namespace App\Builder;

use Umutsevimcann\VisualBuilder\Domain\SectionTypes\SectionTypeInterface;
use Umutsevimcann\VisualBuilder\Domain\SectionTypes\Fields\TextField;
use Umutsevimcann\VisualBuilder\Domain\SectionTypes\Fields\ImageField;
use Umutsevimcann\VisualBuilder\Domain\SectionTypes\Fields\LinkField;

class HeroSection implements SectionTypeInterface
{
    public function key(): string { return 'hero'; }
    public function label(): string { return 'Hero Banner'; }
    public function description(): string { return 'Full-width banner with heading, subtitle, and CTA.'; }
    public function icon(): string { return 'fa-solid fa-image'; }

    public function fields(): array
    {
        return [
            new TextField('headline', 'Headline', translatable: true, maxLength: 120),
            new TextField('subtitle', 'Subtitle', translatable: true, maxLength: 250),
            new ImageField('background_image', 'Background Image'),
            new LinkField('cta', 'Call-to-action Button'),
        ];
    }

    public function defaultContent(): array
    {
        return ['headline' => ['en' => 'Welcome'], 'subtitle' => ['en' => '']];
    }

    public function defaultStyle(): array { return ['padding_y' => '80px']; }
    public function viewPartial(): string { return 'builder.sections.hero'; }
    public function allowsMultipleInstances(): bool { return false; }
    public function isDeletable(): bool { return true; }
    public function previewImage(): ?string { return null; }
    public function buildContentFromLegacySettings(array $legacy): array { return []; }
}
```

### 3. Register in your AppServiceProvider

```php
use App\Builder\HeroSection;
use Umutsevimcann\VisualBuilder\Domain\SectionTypes\SectionTypeRegistry;

public function boot(): void
{
    $this->app->extend(SectionTypeRegistry::class, function ($registry) {
        $registry->register(new HeroSection());
        return $registry;
    });
}
```

### 4. Add the editor to your admin view

```blade
@extends('admin.layout')

@section('content')
    <x-visual-builder::editor :target="$page" />
@endsection
```

### 5. Render on the frontend

```blade
@foreach($page->builderSections as $section)
    @includeIf('builder.sections.' . str_replace('_', '-', $section->type), [
        'section' => $section,
    ])
@endforeach
```

## Documentation

- **[Architecture](docs/architecture.md)** — layer diagram, persistence schema, save pipeline, iframe protocol
- **[Extension Points](docs/extension-points.md)** — 4 swap-point contracts, events, publishable assets, morph map
- **[Field Types Reference](docs/field-types.md)** — all 8 shipped fields + custom field guide

## Customization

### Swap the media service

By default uploads go to `storage/app/public/visual-builder/`. Override by binding your own:

```php
use Umutsevimcann\VisualBuilder\Contracts\MediaServiceInterface;

$this->app->bind(MediaServiceInterface::class, MyS3MediaService::class);
```

### Authorization

Guard the builder with any gate or middleware:

```php
// config/visual-builder.php
'middleware' => ['web', 'auth'],
'authorization_gate' => 'edit-pages', // optional Gate check
```

### Custom views

Publish views and edit any template:

```bash
php artisan vendor:publish --tag="visual-builder-views"
```

### Field types

Extend `FieldDefinition` abstract class to create custom fields (e.g., color palette, icon picker, map coordinates).

## Architecture

```
┌─────────────────────────────────────────────────┐
│  Your App                                       │
│  ├─ AppServiceProvider: register SectionTypes   │
│  ├─ config/visual-builder.php                   │
│  └─ resources/views/builder/sections/           │
└─────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────┐
│  Visual Builder Package                         │
│  ├─ Contracts/ (interfaces you implement)       │
│  ├─ Domain/                                     │
│  │  ├─ Models/BuilderSection (polymorphic)      │
│  │  ├─ SectionTypes/ (registry + fields)        │
│  │  ├─ Actions/ (CRUD + ApplyBuilderLayout)     │
│  │  └─ Services/DesignTokenService              │
│  ├─ Http/ (controllers, requests, routes)       │
│  └─ Resources (views, CSS, JS)                  │
└─────────────────────────────────────────────────┘
```

## Testing

```bash
composer test
composer test-coverage
composer analyse  # PHPStan
composer format   # Pint
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for recent changes.

## Contributing

Pull requests welcome. Please make sure tests pass and code style matches:

```bash
composer test && composer format
```

## Credits

- [Umut Sevimcan](https://github.com/umutsevimcann)
- Built with [spatie/laravel-package-tools](https://github.com/spatie/laravel-package-tools)

## License

MIT — please see [LICENSE.md](LICENSE.md).
