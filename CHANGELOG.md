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

## [0.2.0] — 2026-04-16

Elementor-style inline editing lands. Editor finally feels like a page
builder instead of a forms-over-iframe split. Breaking change: none —
existing integrations keep working, new UI activates automatically when
the published views/assets refresh.

### Added

- **Inline `+` inserter between sections.** The iframe injects a
  hoverable strip before every section wrapper (and after the last one).
  Click opens "insert mode" — the next block-palette click on the parent
  creates a section at that exact slot instead of appending to the end.
- **Hover action toolbar on every section.** Four icon buttons
  (move up, move down, duplicate, delete) float at the top-right of
  each `.vb-section-wrap` in the preview. Up/down disable at extremes.
  Actions hit the package's existing REST endpoints; iframe reloads
  with the fresh state.
- **19 built-in SVG icons.** Editor chrome (palette, save, devices,
  undo/redo, etc.) + new inline controls (plus, copy, trash, chevrons,
  cog). Inlined as CSS mask-image data URIs so they inherit parent
  color and ship with no extra HTTP requests. Override any icon by
  publishing the CSS.
- `CreateSection::execute()` gains an optional `$afterSortOrder` param.
  When set, reserves the slot via a single atomic UPDATE (bumps every
  existing sort_order >= slot by +1) then creates at the free slot.
- `BuilderController::store` accepts `after_section_id` for position-
  aware insertion, and now returns JSON when the client asks for it.
- `BuilderController::duplicate` now returns JSON when asked.

### Fixed

- The v0.1 editor shipped `<i class="vb-icon vb-icon-*">` markup with
  no icon images — every slot rendered as a 16×16 empty box. 19 icons
  are now baked in via the CSS sprite described above.

### Tests

- 50 tests (was 48). Two new feature tests cover position-aware insert
  and cross-target fallback to append.

### Upgrade notes

Run `php artisan vendor:publish --tag=visual-builder-assets --force` to
refresh the CSS/JS into `public/vendor/visual-builder/`. Views are
unchanged. No migrations.

## [0.1.3] — 2026-04-16

CI-only fix release — no runtime behavior changes. All 12 matrix cells
(PHP 8.2–8.4 × Laravel 11–12 × prefer-lowest/stable) are now green on
Linux.

### Fixed

- **PSR-4 case mismatch on Linux.** Stub test fixtures lived under
  `tests/stubs/` (lowercase) but the PHP namespace segment was `Stubs`
  (capital S). Windows and macOS (case-insensitive filesystems) didn't
  care; Linux CI couldn't autoload any of the fixture classes and every
  feature test crashed at `pest --ci` with exit code 2. Renamed the
  directory to `tests/Stubs/` and updated the `loadMigrationsFrom()`
  path in the base TestCase.
- README badges: removed the blank `&token=` query on the Codecov
  badge (shields.io was rendering "invalid query parameter: token"),
  swapped the packagist license badge for a static shield linking to
  LICENSE.md (the packagist version misreports license on fresh
  packages until reindex).

### Changed

- CI matrix now runs with `fail-fast: false` so a regression in one
  (PHP, Laravel, stability) combo surfaces every broken cell in a
  single push instead of hiding the rest behind a cancelled badge.
- Added a `pest --log-junit` step + conditional artifact upload on
  failure. Future CI failures will include a downloadable junit XML
  with full test-level detail — no more log-hunting.

## [0.1.2] — 2026-04-16

Controller-level feature coverage plus CI coverage reporting.

### Added

- **Feature tests** — every BuilderController + DesignTokenController
  endpoint now has at least one happy-path + one failure-path test.
  48 tests total (33 unit + 15 feature, 105 assertions).
- **Codecov integration** — dedicated coverage job in `run-tests.yml`,
  `codecov.yml` with 1% project threshold and 80% patch target,
  Codecov + code-style badges in the README.
- **Comprehensive documentation** — `docs/architecture.md`,
  `docs/extension-points.md`, `docs/field-types.md`. README now links
  all three as a "Documentation" section.

### Fixed

- `BuilderController::assertSectionBelongsTo` compared
  `$section->builder_type` (stored as the morph alias when a morph map
  is registered) against the FQCN resolved from the map — duplicate and
  destroy requests always returned 403 for morph-mapped targets. The
  guard now normalizes both sides through the morph map so alias-stored
  and FQCN-stored sections both validate correctly. Surfaced while
  writing feature tests.

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
