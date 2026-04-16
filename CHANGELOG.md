# Changelog

All notable changes to `laravel-visual-builder` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## v0.1.0 — Initial Release - 2026-04-16

Initial public release. Foundational feature set.

### Highlights

- 8 field types + polymorphic `BuilderSection` model + 4 extension contracts
- 8 domain actions (Create/Update/Delete/Duplicate/Reorder/BulkApply)
- Blade component `<x-visual-builder::editor>` — drop into any admin
- Live iframe preview with postMessage sync
- 10 entrance animations, IntersectionObserver-based
- Vanilla JS (no jQuery), framework-agnostic CSS
- Pest test suite (33 tests, 54 assertions)
- PHPStan level 5, zero errors

See [CHANGELOG.md](https://github.com/umutsevimcann/laravel-visual-builder/blob/main/CHANGELOG.md) for full list.

### Install

    composer require umutsevimcann/laravel-visual-builder
    php artisan visual-builder:install
    
### Dogfooding Bugs Fixed Post-Tag

Four integration bugs (route double-registration, view data pass, DesignToken table probe, Blade component namespace) surfaced and fixed in commits after the tag. v0.1.1 will capture these.

## [0.1.1] — 2026-04-16

First dogfooding pass on a fresh Laravel 12 install surfaced four
integration defects not caught by unit tests. All four fixed; the editor
now renders HTTP 200 against a clean install.

### Fixed

- **Double route registration.** `hasRoute('web')` in `configurePackage()`
  and the manual route group in `packageBooted()` both ran, producing every
  endpoint at two URL paths. Removed `hasRoute('web')` — `packageBooted()`
  is now the sole registration site, keeping config-driven prefix +
  middleware + name prefix.
- **`$bootstrap` undefined in editor view.** Blade Component `data()`
  extraction did not expose payload when it was only produced in
  `render()` with explicit view data. Refactored `Editor` to compute every
  view variable in the constructor as public readonly properties.
- **`DesignTokenService` crashed on fresh installs without settings table.**
  Introduced `settingsTableExists()` schema probe; `all()` falls back to
  defaults when the table is missing, `save()` raises a clear
  `RuntimeException` with remediation steps.
- **Editor view collided with anonymous component path.** Moved from
  `resources/views/components/editor.blade.php` (conflicted with Laravel's
  anonymous component convention) to `resources/views/editor.blade.php`.
  Switched from Spatie's `hasViewComponent()` (produced hyphenated
  `visual-builder-editor` tag) to `Blade::componentNamespace()` — the
  documented `<x-visual-builder::editor>` syntax now works.

### Added

- `tests/Feature/ServiceProviderTest.php` — 8 integration tests covering
  provider bootstrap, contract bindings, singleton resolution, config
  namespace, and migration execution on the test database.

## [0.1.0] — 2026-04-16

Initial public release. Foundational feature set; internal API is considered
experimental until 1.0 (patch/minor releases may adjust contract signatures
when documented in release notes).

### Added

**Core framework**

- `SectionTypeInterface` + `SectionTypeRegistry` (fluent, singleton) for
  declarative section schemas.
- `FieldDefinition` abstract base plus eight concrete field types:
  Text, Html, Toggle, Select, Link, Image, Reference, Repeater.
- Polymorphic `BuilderSection` Eloquent model (morphs to any host model)
  plus `HasVisualBuilder` trait.
- `BuilderRevision` model + server-side snapshot persistence (opt-in via
  `visual-builder.revisions.server_enabled`).

**Extension contracts**

- `BuilderRepositoryInterface` (default: `EloquentBuilderRepository`)
- `MediaServiceInterface` (default: `StorageMediaService`)
- `SanitizerInterface` (default: `PurifierSanitizer`, falls back to
  `strip_tags` allow-list when `mews/purifier` is not installed)
- `AuthorizationInterface` (default: `GateAuthorization`, respects
  optional `visual-builder.authorization_gate` config)

**Domain actions**

- `CreateSection`, `UpdateSectionContent`, `UpdateSectionStyle`,
  `UpdateSectionVisibility`, `DeleteSection`, `DuplicateSection`,
  `ReorderSections`, `ApplyBuilderLayout` (single-transaction batched save).

**Domain events**

- `SectionCreated`, `SectionUpdated`, `SectionDeleted`,
  `SectionsReordered` — all `Dispatchable` + `SerializesModels` for
  queue compatibility.

**Services**

- `DesignTokenService` — global colors + fonts with CSS-variable output
  and settings-table-backed persistence.

**HTTP layer**

- `BuilderController` (show/save/store/duplicate/destroy/uploadImage)
  with morph-map-based target resolution + cross-target guards.
- `DesignTokenController` (update).
- `BuilderSaveRequest`, `DesignTokensRequest`, `UploadImageRequest`
  form requests with regex-based hex color + font-family validation.
- Config-driven routes (prefix, middleware, name prefix customizable).

**UI layer**

- `<x-visual-builder::editor :target="$model" />` Blade component —
  drops the entire editor shell into any admin view, no layout
  assumptions (works under AdminLTE, Filament, Nova, custom).
- Editor views: iframe-based live preview, 3-column grid layout,
  toolbar (device switcher, site-settings/navigator/revisions,
  undo/redo/reload/save), modals, navigator panel, context menu.
- Frontend injection partial (`visual-builder::inject`) — click-to-edit,
  right-click context menu, postMessage-based live updates from parent.
- Framework-agnostic CSS with custom-property theming.
- 10 entrance animation presets with `IntersectionObserver` trigger
  and `prefers-reduced-motion` accessibility support.
- Vanilla JS client (no jQuery/framework), CSRF-aware, origin-pinned
  postMessage protocol.

**Examples**

- `BlankContainerSectionType` (generic rich-text body).
- `TwoColumnSectionType` (image + heading + body + CTA template).

**Tooling**

- Migrations for `builder_sections` + `builder_revisions` tables
  with polymorphic indexes and singleton uniqueness constraint.
- Publishable config with full inline documentation.
- Install command: `php artisan visual-builder:install`.
- GitHub Actions CI: test matrix (PHP 8.2-8.4 × Laravel 11-12 ×
  prefer-lowest/prefer-stable), PHPStan, Pint auto-fix.
- Pest test suite (25 tests, 43 assertions) on Orchestra Testbench.
- PHPStan level 5 (0 errors at 0.1.0 release).

### Security

- All HTML fields pass through `SanitizerInterface` before persistence.
- Image uploads validate MIME + size + directory regex (no path traversal).
- `_element_styles` CSS property whitelist (13 safe props only —
  `onclick`, `background: url(javascript:)` attacks impossible).
- `_visibility` + content keys filtered against registered field
  whitelist on every update.
- Design tokens: hex-regex colors, angle-bracket-blacklist font families.
- `BuilderController` verifies section's stored morph owner matches the
  route's `targetType`+`targetId` (prevents cross-target attacks).
- `postMessage` protocol pins `targetOrigin = window.location.origin`.
- Zero hardcoded credentials, secrets, or environment-sensitive values.
