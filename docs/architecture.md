# Architecture

High-level tour of how the package is put together. Written for maintainers and for users planning non-trivial customizations.

---

## Layers

```
┌─────────────────────────────────────────────────────────────────┐
│  Your Host App                                                  │
│  ├─ Eloquent models (Page, Post, Product) + HasVisualBuilder    │
│  ├─ AppServiceProvider: morph map + section type registration   │
│  └─ Admin view embedding <x-visual-builder::editor/>            │
└─────────────────────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  HTTP Layer (src/Http)                                          │
│  ├─ BuilderController       show/save/store/duplicate/destroy   │
│  ├─ DesignTokenController   global colors & fonts               │
│  └─ Form Requests           top-level shape validation          │
└─────────────────────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  Domain Layer (src/Domain)                                      │
│  ├─ Actions       Create/Update/Delete/Duplicate/Reorder/Apply  │
│  ├─ DTOs          BuilderLayoutData                             │
│  ├─ Events        SectionCreated/Updated/Deleted/sReordered     │
│  ├─ Models        BuilderSection + BuilderRevision              │
│  ├─ Fields        FieldDefinition + 8 concrete types            │
│  ├─ Sections      SectionTypeInterface + Registry               │
│  └─ Services      DesignTokenService                            │
└─────────────────────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  Infrastructure Layer (src/Infrastructure)                      │
│  ├─ EloquentBuilderRepository                                   │
│  ├─ StorageMediaService                                         │
│  └─ PurifierSanitizer                                           │
└─────────────────────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  Contracts (src/Contracts) — binding swap points                │
│  BuilderRepositoryInterface · MediaServiceInterface             │
│  SanitizerInterface · AuthorizationInterface                    │
└─────────────────────────────────────────────────────────────────┘
```

Contracts point "up" to the layers that depend on them — classic Dependency Inversion. The Domain layer never imports Infrastructure directly; it speaks through contracts. Users override any contract with a single `app()->bind()` call, no forking required.

---

## Persistence

### `builder_sections` table

| Column | Type | Purpose |
|---|---|---|
| `id` | `bigint` | Primary key |
| `builder_type` | `string` | Morph alias of the owner (page, post, product) |
| `builder_id` | `bigint` | Primary key of the owner |
| `type` | `string` | Section type key from `SectionTypeRegistry` |
| `instance_key` | `string` | Distinguishes sibling instances; default `__default__` |
| `content` | `json` | Field values (translatable fields as locale maps) |
| `style` | `json` | Style overrides (bg_color, padding_y, animation, etc.) |
| `is_published` | `bool` | Draft flag |
| `sort_order` | `int` | Position within the target's section list |
| `starts_at` | `timestamp?` | Optional scheduling window start |
| `ends_at` | `timestamp?` | Optional scheduling window end |
| `created_at` / `updated_at` | timestamps | Standard Eloquent |

**Composite index:** `(builder_type, builder_id, is_published, sort_order)` — covers the dominant query on every public page render.

**Unique constraint:** `(builder_type, builder_id, type, instance_key)` — enforces the singleton invariant at the DB level. Attempting to insert a second `hero/__default__` for the same page raises an integrity error even if the Action-level check is bypassed.

### Content JSON shape

Translatable fields are stored as locale maps. Two reserved meta keys carry per-field state:

```json
{
  "headline": { "en": "Welcome", "de": "Willkommen" },
  "subtitle": { "en": "…", "de": "…" },
  "cta_url": "/contact",
  "cta_label": { "en": "Contact", "de": "Kontakt" },
  "_visibility": { "subtitle": false },
  "_element_styles": {
    "headline": { "color": "#ffffff", "font_size": "48px" }
  }
}
```

The reserved keys are whitelisted in `UpdateSectionContent` — unknown field keys and unknown CSS properties are rejected even if the UI is bypassed.

---

## Save pipeline

The editor batches every change into a single save call. `BuilderSaveRequest` validates the top-level shape; `BuilderLayoutData` transports the payload; `ApplyBuilderLayout` orchestrates the per-section writes inside one database transaction.

```
editor.js
  state.pending.sections[id] = { content, style, is_published }
  state.pending.orderedIds   = [id, id, id, ...]
       │ user clicks Save
       ▼
POST /visual-builder/page/5/save
       │
       ▼
BuilderSaveRequest  ←  top-level array validation + auth check
       │
       ▼
BuilderLayoutData   ←  readonly DTO
       │
       ▼
ApplyBuilderLayout  ←  DB::transaction
       │
       ├─ UpdateSectionContent    ·  per-field sanitize via FieldDefinition
       ├─ UpdateSectionStyle      ·  key whitelist
       ├─ UpdateSectionVisibility ·  is_published + schedule
       └─ ReorderSections         ·  sort_order bulk update
       │
       ▼
Events fire ← cache invalidation listeners, audit logs, etc.
```

If ANY sub-action throws, the entire transaction rolls back. The editor sees either "all saved" or "nothing saved" — never partial state.

---

## Frontend render

Your frontend view iterates `$target->builderSections` (or `visibleBuilderSections` for public rendering) and picks the right partial per section type via `SectionTypeInterface::viewPartial()`.

```blade
@foreach($page->visibleBuilderSections as $section)
    @includeIf(
        app(\Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeRegistry::class)
            ->findOrFail($section->type)
            ->viewPartial(),
        ['section' => $section],
    )
@endforeach
```

The partial has full access to the `$section->content`, `$section->style`, and helper methods like `$section->contentField('headline')` (locale-aware) and `$section->isFieldVisible('subtitle')`.

---

## Builder iframe protocol

The admin editor renders the target's public URL in an iframe with `?builder=1`. The frontend template includes `visual-builder::inject` when that query flag is present, which adds:

1. CSS outlines on `.vb-section-wrap` and `[data-vb-editable]` elements.
2. A click handler that posts `section-clicked` / `field-focused` to the parent window.
3. A message listener for live DOM updates (`field-update`, `style-update`, `image-update`, `visibility-update`, `element-style-update`).

All `postMessage` calls pin `targetOrigin = window.location.origin` — the iframe and its parent must be same-origin for any cross-frame communication to succeed.

Persistence-wise the iframe is a dumb renderer — every edit lives in the parent's `state.pending` buffer until Save. The iframe merely reflects what the parent tells it to show, without ever reaching back to the database.

---

## Animations

Section-level entrance animations are driven by a single `IntersectionObserver` in `animations.js`. When a `[data-vb-animation]` element crosses into the viewport, the observer toggles `.vb-anim-play`, which triggers a CSS keyframe.

- **Trigger threshold:** 10% visibility + 50px bottom margin.
- **One-shot:** the observer unobserves each element after its first play.
- **Accessibility:** `@media (prefers-reduced-motion: reduce)` disables every animation site-wide.

---

## Extension points cheat sheet

| Need | Swap target |
|---|---|
| Custom DB backend | `BuilderRepositoryInterface` |
| Custom file storage | `MediaServiceInterface` |
| Custom HTML sanitizer | `SanitizerInterface` |
| Custom permission check | `AuthorizationInterface` |
| New field type | Extend `FieldDefinition` |
| New section type | Implement `SectionTypeInterface`, register in `SectionTypeRegistry` |
| Different route prefix/middleware | `config('visual-builder.routes')` |
| Different table names | `config('visual-builder.tables')` |
| Different settings store | `config('visual-builder.design_tokens.settings_table')` |
| Fully custom editor chrome | `vendor:publish --tag=visual-builder-views`, edit the Blade |

Every swap is a single line in your `AppServiceProvider` or a config edit. See [extension-points.md](extension-points.md) for full examples.
