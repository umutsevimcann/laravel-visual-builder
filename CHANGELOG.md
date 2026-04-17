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

## [0.5.0] — 2026-04-17

### Added

- **Block palette redesign — category tabs + card grid.** The left
  panel now opens as a horizontal tab bar (Basic · Media · Layout ·
  …) with a 2-column card grid underneath. Each widget card shows
  the icon, the label, and is natively `draggable="true"` ready for
  the drag-drop interactions shipping in v0.5.1. Matches the
  layout Elementor / Gutenberg content editors use; host apps can
  introduce their own category keys and a new tab appears
  automatically.

- **Optional `category()` method on atomic widgets.** Added to
  `AbstractAtomicWidget` with a `'basic'` default — widgets override
  to pick a tab. The Editor component serializes the category into
  the bootstrap payload with a `method_exists()` fallback so
  host-app section types that predate this release keep rendering
  (they land in a `'general'` tab so they are still visible).

### Categories for shipped widgets

- **basic** — Heading, Paragraph, Button, Icon
- **media** — Image, Video
- **layout** — Spacer, Divider, Columns, IconBox

### State + persistence

- `state.activePaletteCategory` persists the chosen tab across
  structural mutations. Creating / deleting a section no longer
  resets the user to the first tab.

- Card order inside a category preserves registry insertion order.
  Categories themselves follow a canonical `basic → media → layout
  → general` ordering; unknown host categories come last sorted
  alphabetically.

### Compatibility

- Existing `.vb-block` / `.vb-block-icon` / etc. CSS stays in the
  stylesheet. Host apps that published a customized
  `editor.blade.php` with `data-vb-blocks` on the same element
  keep rendering with the new structure inside it. Views that are
  not customized need no change.

### Tests

- 5 new unit tests (category defaults + 10 shipped widget
  classifications). Total: **133 tests / 396 assertions**.

## [0.4.3] — 2026-04-17

### Added

- **Inline rich-text editing for `data-vb-html` fields — floating
  bubble-menu on text selection.** Double-clicking a paragraph, icon-box
  body, or any other widget field flagged `data-vb-html` now enters
  contenteditable mode with a floating toolbar above the selection.
  Buttons: **Bold · Italic · Underline · H2 · H3 · Paragraph · Bullet
  list · Numbered list · Link · Unlink · Clear formatting**. Commit on
  blur posts `innerHTML` (not `textContent`) to the parent, which
  routes into the existing field-update DOMParser + replaceChildren
  pipeline so HTML tags survive the round-trip into
  `state.pending.content` and onward into the server-side HtmlField
  sanitizer.

- **Keyboard shortcuts** work out of the box: **Ctrl+B / Ctrl+I /
  Ctrl+U** are wired by the browser through `execCommand` on
  contenteditable — the bubble toolbar is additive UI, not a
  replacement for the native shortcuts.

- **URL safety gate on the Link button.** Host apps still run the
  final HTML through `HtmlField → SanitizerInterface` (HTMLPurifier by
  default) server-side, but we also whitelist the accepted URL shapes
  at the UX boundary so broken pastes (`javascript:`, `data:` blobs)
  never land in the DOM — the purifier stripping the whole anchor
  tag silently later isn't a good user experience. Bare hostnames
  (`example.com/foo`) auto-prepend `https://`.

### Design notes

- The package intentionally chose `document.execCommand` over a
  framework dependency (TipTap / ProseMirror / Slate). Rationale:
  zero external dependencies, zero CDN/bundler cost, works offline,
  existing Ctrl+B keyboard shortcut is free. The feature surface
  (bold / italic / underline / headings / lists / link) covers every
  Paragraph / IconBox-body / custom HtmlField use case without
  needing slash-commands or a virtual DOM on the editing side.

### Not affected

- Plain-text inline editing (Heading, Button label, …) still uses
  the legacy `textContent`-commit path — attribute-selector split
  between `[data-vb-editable]:not([data-vb-html])` and the new
  `[data-vb-editable][data-vb-html]` handler.
- Server-side storage shape for HTML fields is unchanged.

## [0.4.2] — 2026-04-17

### Added

- **Nested containers and the Columns widget.** The tenth atomic
  widget lands: a horizontal grid of 1–6 slots, configurable `gap`,
  `stack_on` breakpoint (mobile / tablet / never). Children are real
  `BuilderSection` rows — not embedded JSON — so every inner widget
  keeps its own editable fields, styles, visibility flags and
  breakpoint overrides. A user can drop a Heading + Paragraph into
  column 0 and an Image into column 1 and edit each one exactly the
  way they edit top-level sections.

- **`parent_id` + `column_index` schema.** A new published migration
  `add_parent_and_column_index_to_builder_sections_table` adds the
  two columns (both nullable, so legacy rows keep rendering at the
  top level) plus a composite index on
  `(parent_id, column_index, sort_order)` for the per-column render
  query.

- **`BuilderSection` relations.**
  - `parent()` belongsTo — the containing section.
  - `children()` hasMany — direct descendants (no default ordering;
    relation definitions stay pure so Larastan keeps the return type
    as `HasMany`).
  - `orderedChildren()` — `Collection` of children ordered by
    `column_index` then `sort_order`; the canonical read used by
    container render partials.
  - `childrenInColumn(int)` — `Collection` filtered to one column
    slot, still sort_order-ordered.

- **Repository top-level filter.** `forTarget()` and
  `visibleForTarget()` now filter `WHERE parent_id IS NULL` so the
  admin section list, the block palette singleton checks, and the
  frontend render all see only top-level sections. Container
  children surface lazily through their parent at render time.

### Tests

- 4 new unit tests on `ColumnsWidget` (key / viewPartial / field
  identities / defaults).
- 5 new feature tests on the hierarchy contract: repository
  top-level filter (both methods), `orderedChildren()` ordering,
  `childrenInColumn()` filter, `parent()` lookup.
- Total suite: **128 tests / 365 assertions**.

### Known limitations — admin insertion UI

- The block palette's `+` inserter still adds top-level sections
  only. Adding a widget INTO a column (setting its `parent_id` /
  `column_index` via the UI) is a separate admin-UX slice and lands
  in a follow-up release. For now, nested children can be seeded
  programmatically (factories, artisan tinker) and render correctly
  end-to-end through the Columns blade partial.

## [0.4.1] — 2026-04-17

### Added

- **Four media widgets.** **Image**, **Video**, **Icon**, **IconBox**
  join the atomic-widget lineup — all opt-in through the same
  `visual-builder.widgets` config block shipped in v0.4.0.
  - **Image** — storage-path / asset / absolute URL `src`, translatable
    alt text, optional link URL, `object-fit` select (cover / contain /
    fill / none). Render resolves the `src` through the existing URL
    conventions (`assets/` prefix, `/storage/` fallback, absolute
    passthrough) so it drops into any host stack without a media
    service rewrite.
  - **Video** — accepts a raw `.mp4` URL (native `<video>` tag) or a
    YouTube / Vimeo page URL (normalized to the embed-player iframe).
    Autoplay, loop and controls toggles map to the provider's
    query-string flags; aspect-ratio selector covers 16/9 · 4/3 · 1/1
    · 21/9. Unrecognized URLs render a placeholder block rather than
    emitting an arbitrary iframe `src`.
  - **Icon** — library-agnostic: `class` field takes any CSS class
    string (Font Awesome, Bootstrap Icons, Lucide, etc.). Size
    selector maps to 5 CSS `font-size` presets (16→64px). Optional
    link wraps the icon in an `<a>`.
  - **IconBox** — composed "features" block: icon + translatable
    title + rich-text body. `layout` select controls icon placement
    (top / left / right, mapped to flex-direction). Title and body
    both carry `data-vb-editable` so inline editing works in the
    iframe without extra wiring.

- **Tests.** 10 new unit tests on the media widget schemas plus
  registration coverage for the expanded `available` map. Total
  suite: **119 tests / 328 assertions**.

### Housekeeping

- `visual-builder.widgets.list` default now includes the 4 new keys
  (`image`, `video`, `icon`, `icon_box`). Host apps that already
  trimmed the list in their published config keep their choices.

## [0.4.0] — 2026-04-17

### Added

- **Atomic widgets — Elementor-style building blocks.** Five
  first-class, built-in section types ship with the package:
  **Heading**, **Paragraph**, **Button**, **Spacer**, **Divider**.
  Each is a `SectionTypeInterface` implementation living under
  `Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\*` and
  extending a new `AbstractAtomicWidget` base that defaults
  `allowsMultipleInstances=true`, `isDeletable=true` and routes
  `viewPartial()` to `visual-builder::widgets.{key}` by convention.

- **`visual-builder.widgets` config block.** Registration is opt-in
  via `enabled: true`. The `list` array selects which widgets appear
  in the host's block palette — unknown keys are silently ignored so
  trimming a widget is a one-line change. Host-app registrations for
  the same key always win (the package checks `$registry->has($key)`
  before registering, sidestepping the duplicate-key exception
  `SectionTypeRegistry::register()` throws).

- **Widget Blade partials** — one per widget, published under
  `resources/views/widgets/`. Each partial emits the minimum semantic
  markup with the standard `data-vb-editable` / `data-vb-field`
  attributes the iframe click handlers depend on. Inline edits work
  in the admin iframe immediately; the public frontend renders the
  same partial without the builder-mode wrapper.

- **Test coverage.** 16 new tests (10 unit on widget schemas, 4
  feature on registration toggle, plus existing 5 on the resolver
  widgets touch) lock the public shape of every widget — keys, field
  names, field types, default content / style — so future renames or
  type-churn show up as a red test before they desync the iframe
  click handlers. Total suite: **109 tests / 266 assertions**.

### Not yet

- **Image / Video / Icon / IconBox** widgets land in **v0.4.1**.
- **Columns** widget with nested children lands in **v0.4.2**
  alongside a `parent_section_id` migration.
- **TipTap** swap on the Paragraph widget lands in **v0.4.3**.

## [0.3.3] — 2026-04-17

### Fixed

- **Headline / subtitle / any inline-editable child going invisible
  after the @vbSectionStyles directive renders.** The CSS emitted by
  the directive used the plain attribute selector
  `[data-vb-section-id="X"]` which also matches every editable
  descendant (h1, p, img) — those carry the same attribute so the
  iframe click handler can identify their owning section. As a result
  the wrapper's `animation: fadeIn !important` cascaded through to the
  host's `.fadeDownShort` h1, replaced that animation with a
  zero-duration fadeIn whose `fill-mode: none` left the element at
  opacity:0 and the text simply stopped being visible. Selector is
  now `[data-vb-section-id="X"]:not([data-vb-editable])`; cascade
  stops at the wrapper where it belongs. Same fix applied to the
  iframe's live-preview `updateSectionStyleInDom` so editor and
  production stay cascade-equivalent.

- **`animation` and `animation_delay` no longer emit as CSS
  declarations.** Those keys store CSS CLASS names (e.g. `fadeIn`)
  consumed by host templates as `vb-anim-fadeIn` class, not as raw
  `animation:` shorthand values. Emitting `animation: fadeIn` on the
  wrapper caused the incident above and could also override host
  keyframe animations silently. Both keys are now in
  `CSS_SKIPPED_KEYS` — stored verbatim, never rendered.

- **Unit-less spacing values are now auto-postfixed with `px`.**
  Legacy seeded data stored values as `"40"` / `"80"` — browsers
  reject `padding-top: 40` as invalid and silently fall back to 0.
  The resolver now appends `px` when a spacing key (padding_*,
  margin_*) holds a bare numeric string; values with explicit units
  (`5rem`, `2vh`) pass through unchanged; colour / alignment / font
  values are never touched.

Four new unit tests cover the `px` auto-append, the unit passthrough,
the non-spacing passthrough, and the animation skip. Regression
covered by browser-automation reproduction before fix (confirmed
h1 at opacity:0 in live preview; post-fix h1 renders normally).

## [0.3.2] — 2026-04-17

### Fixed

- **Per-breakpoint style values now persist.** `UpdateSectionStyle`
  filtered object-shape values through `is_scalar()` and silently
  dropped them, so the client could promote `padding_y` to
  `{desktop, tablet, mobile}` in the editor but the Save endpoint
  would reject everything except the scalar leaf. v0.3.0 shipped
  the UI and the resolver; this release wires the storage path end
  to end. Scalar values keep working verbatim — only object shape
  handling was broken.
  Nine new unit tests cover the sanitization contract
  (scalar + object + mixed + partial + empty slots + unknown breakpoint
  keys + final null-on-empty behaviour).

- **Undo / Redo no longer reloads the iframe.** The restore path
  previously called `save()` to resync the DB and `save()` triggers
  `reloadIframe()` on success — causing a whole-page flash on every
  Ctrl+Z. Undo/Redo now replay the snapshot to the iframe via the
  existing `style-update`, `visibility-update` and `field-update`
  postMessage channels; the iframe DOM updates in place and the
  user's Save button still persists the restored state when they
  explicitly press Save.

- **Iframe "Live Preview" no longer auto-scrolls on every inline
  edit.** `highlightSection()` inside the iframe unconditionally
  called `scrollIntoView({ block: 'center' })`, which pulled the
  preview to-center every time the parent panel echoed a highlight —
  e.g. on every keystroke in an inline-editable field. Now only
  scrolls when the target section is entirely above or below the
  iframe viewport; a partially-visible section stays where it is.

## [0.3.1] — 2026-04-17

### Fixed

- **Iframe inject script no longer breaks host app render in builder
  mode.** Two JS comments inside `inject.blade.php` referenced
  `@vbSectionStyles` verbatim, which Blade eagerly compiled as a
  directive call with zero arguments — causing
  `Too few arguments to sectionStylesTag()` on every builder-mode
  page hit. Escaped to `@@vbSectionStyles` so the text reaches the
  browser as a literal comment and the directive is only invoked when
  the host app actually writes it in a template.

## [0.3.0] — 2026-04-17

### Added

- **Per-breakpoint style values (Elementor-style responsive editing).**
  Style keys may now store either a scalar (applies everywhere, legacy
  shape) or a per-breakpoint object:
  ```json
  { "padding_y": { "desktop": "80px", "tablet": "60px", "mobile": "40px" } }
  ```
  The top toolbar's device buttons (monitor / tablet / mobile) now
  switch the editor's active breakpoint. Responsive fields (padding,
  margin, alignment) write only the active breakpoint's slice and show
  a small badge indicating which device they affect. Inheritance is
  `mobile ← tablet ← desktop` so partially-filled object values still
  produce sensible output at every size.
- **`BreakpointStyleResolver` service** — resolves a mixed style array
  for a specific breakpoint and emits browser-ready CSS with `@media`
  queries, `!important`-scoped so builder settings win over legacy
  inline `style=` attributes in host section partials. Container-bound
  as a singleton; construction is config-driven.
- **`@vbSectionStyles($section)` Blade directive** — emits a scoped
  `<style>` block containing the resolved CSS for one section, ready
  to drop in above the section's markup in production templates. Host
  apps add one line per section partial and the full responsive
  cascade is rendered server-side with no template rewrites required.
- **`config/visual-builder.php` → `breakpoints` block** — configurable
  `tablet_max` (default 1023) and `mobile_max` (default 767) viewport
  thresholds. Thresholds flow to both the server resolver and the
  iframe inject script via bootstrap, guaranteeing the live preview
  matches production cascade behaviour.

### Changed

- **Iframe inject script switches to scoped `<style>` blocks for live
  preview style updates.** `updateSectionStyleInDom` no longer mutates
  inline `element.style` — it writes the CSS text of a
  `style[data-vb-section-styles="{id}"]` tag (creating it if absent),
  matching the production render shape 1:1. Browser media queries take
  over when the iframe is resized by the top toolbar's device buttons,
  so the live preview reacts without any JS resize listener.

### Backwards compatibility

- Existing flat scalar style values keep working verbatim. Users adopt
  the responsive shape by simply swapping a scalar for an object — no
  data migration required, no forced rewrite of section partials. Host
  apps that do not call `@vbSectionStyles` still render as before; the
  directive is additive.

## [0.2.7] — 2026-04-17

### Added

- **Undo / Redo for content, style and visibility edits.** The two
  history buttons at the top of the editor (and `Ctrl+Z` / `Ctrl+Y` /
  `Ctrl+Shift+Z`) now work. Each edit cluster (typing runs, style
  tweaks, publish toggles) captures a snapshot after 400 ms of idle;
  the stack holds 50 entries. Undo restores the snapshot, rebuilds
  `state.pending` as a full rewrite of every affected section, and
  auto-saves so the DB matches — the iframe reload then reflects the
  restored state end-to-end.

### Known limitations

- Structural operations (add / duplicate / delete / move) reset the
  history to a new baseline. They are persisted immediately by the
  server and the bulk save endpoint cannot recreate a deleted row, so
  letting undo cross a structural anchor would desync client and DB.
  Full structural history is planned for v0.4 along with revision
  storage.

## [0.2.6] — 2026-04-17

### Fixed

- **Clicking an inline-editable element no longer jumps the admin page.**
  `focusTraitsField` used `element.scrollIntoView({ block: 'center' })`
  which bubbles up through every scrollable ancestor — so scrolling the
  traits panel to the right field also scrolled the AdminLTE host page
  out from under the user. Replaced with a targeted scroll that walks up
  to the nearest scrollable container and scrolls only that. The follow-up
  `input.focus()` now passes `{ preventScroll: true }` to suppress the
  browser's secondary scroll-into-view from the focus event itself.

## [0.2.5] — 2026-04-17

### Changed

- **Site Settings → Fonts now uses a dropdown instead of a text input.**
  Previously heading/body font-family had to be typed by hand (e.g.
  `'Roboto', sans-serif`). Dropdown now lists 19 popular presets
  (Roboto, Open Sans, Lato, Montserrat, Poppins, Inter, Playfair
  Display, Merriweather, Georgia, Arial, Helvetica, Courier New, …)
  with each option rendered in its own face so users can preview.
  A `Custom…` option reveals the original text input for stacks
  outside the preset list. Colors already used the browser's native
  `<input type="color">` picker.

## [0.2.4] — 2026-04-17

### Fixed

- **Top toolbar buttons (palette / list / clock) now wired.** `handleToolbar`
  previously only knew `save` and `reload`; the three left-toolbar icons had
  no JS handlers. Site Settings opens the global tokens modal, Navigator
  toggles a section-tree sidebar, Revisions shows a v0.4 placeholder.

## [0.2.3] — 2026-04-17

### Fixed

- **Initial iframe loading overlay no longer hangs.** Race condition where
  the `preview-ready` postMessage from the iframe could fire before the
  editor attached its listener, leaving the grey "Loading preview…" curtain
  up forever. Added a native `iframe.addEventListener('load', …)` fallback
  that dismisses the overlay even if postMessage never arrives.

## [0.2.2] — 2026-04-16

### Fixed

- **Admin page no longer full-reloads on every mutation.** Every
  create/duplicate/delete/move used to call `window.location.reload()`
  which obliterated the editor's scroll position, selected tab, undo
  history, and open modals. Real-world feedback: "Bir şey eklerken
  sayfayı yenilemeden ekleyemiyor." Now the admin page stays put and
  only the iframe preview refreshes — exactly matches Elementor's
  editor behavior.

### Changed

- `BuilderController::store`, `duplicate`, `destroy`, and `save`
  JSON responses now include a fresh `sections` array — the target's
  full section list after the mutation. The JS client replaces
  `state.config.sections` in one step, re-renders the block palette
  (for singleton constraint checks), and reloads the iframe. No extra
  round-trip GET needed.
- `applyMutationResponse(data)` is the shared client-side helper for
  post-mutation state sync. All six mutation paths route through it.
- Selected section clears when its owner is deleted — traits panel
  resets to the empty "Click a section to edit" state instead of
  showing stale fields.

### Tests

- 4 new feature tests cover the `sections` response contract for each
  mutation endpoint. 54 tests total (was 50).

## [0.2.1] — 2026-04-16

Real-use feedback on v0.2.0 exposed a UX gap: editors clicked on text
in the preview and nothing useful happened. This patch turns clicks and
double-clicks on preview elements into first-class editing moves.

### Added

- **Click-to-focus in traits panel.** Clicking a `[data-vb-editable]`
  element in the iframe now forces the Content tab active, scrolls the
  matching field group into view, adds a transient blue halo, and
  focuses the first input. No more "I clicked, nothing happened."
- **Inline contenteditable.** Double-click any non-HTML editable
  element in the preview → it becomes contenteditable with a dashed
  amber outline. Type to edit, blur or Enter to commit, Escape to
  revert. Changes flush through the same Save pipeline as traits-panel
  inputs.
- `renderTraits(sectionId, focusFieldKey)` accepts an optional second
  arg; iframe's `field-focused` message forwards the clicked field key.

### Not in this release (v0.3 roadmap)

- Per-device (responsive) style values. The device toolbar (desktop /
  tablet / mobile) currently only swaps iframe width. Real per-device
  values need content/style JSON to carry `{desktop, tablet, mobile}`
  maps — a breaking data change deferred to a minor.
- Drag-drop section reorder. Toolbar up/down arrows cover the need.

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
