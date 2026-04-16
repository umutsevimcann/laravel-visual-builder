# Field Types Reference

Every section type in your app is built from a sequence of fields. The package ships eight concrete field types plus the abstract `FieldDefinition` base you can extend to add your own.

Each field handles three responsibilities:

1. **Schema** — its admin-input type, label, help text, required/translatable/toggleable flags.
2. **Validation** — which Laravel rules apply on save.
3. **Sanitization** — how the incoming value is cleaned before persistence.

Register fields in order inside your `SectionTypeInterface::fields()` method. The admin UI renders them top-to-bottom in the right-hand Content tab.

---

## Common constructor arguments

Shared by every shipped field:

| Argument | Type | Default | Purpose |
|---|---|---|---|
| `key` | `string` | required | Storage key inside `content` JSON (snake_case). |
| `label` | `string` | required | Human-readable label in the admin UI. |
| `help` | `?string` | `null` | Optional help text under the label. |
| `required` | `bool` | `false` | Whether the value must be present on save. |
| `translatable` | `bool` | varies | Store as a per-locale map `{en: "...", de: "..."}`. |
| `placeholder` | `?string` | `null` | Placeholder shown in empty inputs. |
| `toggleable` | `bool` | `true` | Show a per-field eye icon to hide the value on the site. |

---

## `TextField`

Single-line string. The most common field.

```php
use Umutsevimcann\VisualBuilder\Domain\Fields\TextField;

new TextField(
    key: 'headline',
    label: 'Headline',
    required: true,
    translatable: true,
    maxLength: 120,
);
```

**Extras:** `maxLength` (default 255).

**Stored value:** plain string or locale map.

---

## `HtmlField`

WYSIWYG body text. Always translatable — HTML almost always needs per-language authoring.

```php
use Umutsevimcann\VisualBuilder\Domain\Fields\HtmlField;

new HtmlField(
    key: 'body',
    label: 'Body',
    required: true,
);
```

Every input passes through `SanitizerInterface::purify()` before persistence. The default sanitizer delegates to `mews/purifier` if installed; otherwise falls back to a `strip_tags` allow-list.

**Stored value:** locale map of sanitized HTML strings.

---

## `ToggleField`

Boolean on/off.

```php
use Umutsevimcann\VisualBuilder\Domain\Fields\ToggleField;

new ToggleField(
    key: 'show_badges',
    label: 'Show technology badges',
    defaultValue: true,
);
```

Non-translatable and non-toggleable (the value IS the toggle — a second eye icon would be redundant).

**Stored value:** strict `bool` (truthy inputs coerced).

---

## `SelectField`

Fixed dropdown of options.

```php
use Umutsevimcann\VisualBuilder\Domain\Fields\SelectField;

new SelectField(
    key: 'alignment',
    label: 'Alignment',
    options: [
        'left' => 'Left',
        'center' => 'Center',
        'right' => 'Right',
    ],
    defaultValue: 'left',
);
```

Validation enforces `Rule::in(array_keys($options))` — unknown keys are rejected even if the UI is bypassed.

**Stored value:** string key from the options map.

---

## `LinkField`

URL + optional translatable label. A common composite for CTAs.

```php
use Umutsevimcann\VisualBuilder\Domain\Fields\LinkField;

new LinkField(
    key: 'cta',
    label: 'Call-to-action Button',
    withLabel: true,
);
```

Stores under two sibling keys:

```json
{
  "cta_url": "/contact",
  "cta_label": { "en": "Contact us", "de": "Kontakt" }
}
```

Set `withLabel: false` for icon-only buttons that need no text.

---

## `ImageField`

Storage path to an uploaded image.

```php
use Umutsevimcann\VisualBuilder\Domain\Fields\ImageField;

new ImageField(
    key: 'hero_image',
    label: 'Hero image',
    defaultAsset: 'assets/img/placeholder.png',
);
```

The editor Upload button POSTs the file to the package's `upload-image` route, which delegates to `MediaServiceInterface`. The returned storage path lands in `content[$key]` — your frontend resolves it to a URL via `MediaServiceInterface::url($path)`.

Paths may be:

- Relative storage paths — `visual-builder/abc.jpg`
- Shipped asset paths — `assets/img/hero.png`
- Absolute URLs — `https://cdn.example.com/hero.webp`

---

## `ReferenceField`

One or many IDs from an external Eloquent model.

```php
use Umutsevimcann\VisualBuilder\Domain\Fields\ReferenceField;
use App\Models\Product;

new ReferenceField(
    key: 'featured_product_ids',
    label: 'Featured Products',
    multiple: true,
    maxItems: 6,
    optionsResolver: fn() => Product::where('is_published', true)
        ->orderBy('sort_order')
        ->get()
        ->map(fn($p) => ['id' => $p->id, 'label' => $p->name])
        ->all(),
);
```

The `optionsResolver` closure is called at render time and must return an array of `{'id': int, 'label': string}`. This keeps the package fully decoupled from your domain models — you decide what's pickable and how to label it.

**Stored value:** integer ID (`multiple: false`) or array of integer IDs preserving order (`multiple: true`).

---

## `RepeaterField`

Variable-length list of nested field groups. Models 1-to-many structured content within a section.

```php
use Umutsevimcann\VisualBuilder\Domain\Fields\RepeaterField;
use Umutsevimcann\VisualBuilder\Domain\Fields\TextField;
use Umutsevimcann\VisualBuilder\Domain\Fields\HtmlField;

new RepeaterField(
    key: 'faqs',
    label: 'FAQ Items',
    itemFields: [
        new TextField('question', 'Question', required: true, translatable: true),
        new HtmlField('answer', 'Answer', required: true),
    ],
    minItems: 1,
    maxItems: 20,
);
```

**Stored value:** array of associative arrays, each matching the `itemFields` schema:

```json
[
  { "question": {"en": "..."}, "answer": {"en": "<p>...</p>"} },
  { "question": {"en": "..."}, "answer": {"en": "<p>...</p>"} }
]
```

> **v0.1 UI note:** The editor renders a "planned for v0.2" placeholder for repeater fields. You can still edit them by writing the JSON directly in a custom admin form — the persistence layer and sanitizer already work correctly. The inline repeater UI lands in the v0.2 minor release.

---

## Creating a custom field type

Extend `FieldDefinition` and implement three abstract methods:

```php
use Umutsevimcann\VisualBuilder\Domain\Fields\FieldDefinition;

final class ColorField extends FieldDefinition
{
    public function adminInputType(): string
    {
        return 'color';  // must match a widget in visual-builder.js
    }

    public function validationRules(): array
    {
        return [
            $this->key => [
                $this->required ? 'required' : 'nullable',
                'string',
                'regex:/^#[0-9a-fA-F]{3,8}$/',
            ],
        ];
    }

    public function sanitize(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $value)
            ? strtolower($value)
            : null;
    }
}
```

The admin UI will emit `input_type: 'color'` in the bootstrap payload — extend `visual-builder.js` to render a color widget for that type, or override the published view entirely.

See [extension-points.md](extension-points.md) for swap points you can hook into without forking the package.
