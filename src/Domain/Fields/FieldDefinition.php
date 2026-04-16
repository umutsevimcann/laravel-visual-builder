<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Fields;

/**
 * Abstract base for every field type in a SectionType's schema.
 *
 * A field definition is pure metadata — it knows its name, label, help text,
 * localization flags, and how to validate/sanitize incoming values. It holds
 * NO state: each SectionType instantiates its fields once and reuses the same
 * instances forever.
 *
 * Concrete subclasses:
 *  - TextField         (single-line string, optionally translatable)
 *  - HtmlField         (rich text, always translatable, sanitized)
 *  - ToggleField       (boolean on/off)
 *  - SelectField       (fixed set of options)
 *  - LinkField         (URL + optional translatable label)
 *  - ImageField        (storage path + preview)
 *  - ReferenceField    (one or many IDs from an external model)
 *  - RepeaterField     (variable-length list of nested field groups)
 *
 * To create a custom field, extend this class and implement the three abstract
 * methods. Register the new field like any other in a SectionType's fields().
 *
 * Implements Open-Closed: add new behaviors by subclassing, without touching
 * the package core.
 */
abstract class FieldDefinition
{
    /**
     * @param  string        $key          Storage key in content JSON (snake_case).
     * @param  string        $label        Human-readable label shown above the input.
     * @param  string|null   $help         Optional help text displayed under the label.
     * @param  bool          $required     Whether this field must be present on save.
     * @param  bool          $translatable Whether value is a locale-map {en:"...", de:"..."}.
     * @param  string|null   $placeholder  Placeholder shown inside the empty input.
     * @param  bool          $toggleable   Whether the field has an individual show/hide eye icon.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly ?string $help = null,
        public readonly bool $required = false,
        public readonly bool $translatable = false,
        public readonly ?string $placeholder = null,
        public readonly bool $toggleable = true,
    ) {}

    /**
     * The admin UI input widget used to render this field.
     *
     * Return a short stable key (e.g. 'text', 'html', 'link', 'image').
     * The admin JS maps these to concrete widgets; custom fields should
     * return a unique key and register a matching widget client-side.
     */
    abstract public function adminInputType(): string;

    /**
     * Laravel validation rules for the incoming form data.
     *
     * Return format mirrors Request::rules() — an associative array where
     * keys are dotted input names (typically the field key) and values are
     * arrays of Laravel rule strings or Rule objects.
     *
     * Translatable fields conventionally produce two entries:
     *   [$key => ['array'], "$key.{locale}" => ['required','string','max:...']]
     *
     * @return array<string, array<int, mixed>>
     */
    abstract public function validationRules(): array;

    /**
     * Sanitize an incoming value before persistence.
     *
     * Override when the default behavior (pass-through) is unsafe — e.g.
     * HtmlField purifies through SanitizerInterface, ToggleField coerces
     * to bool, SelectField enforces a whitelist of valid options.
     *
     * @param  mixed  $value  Raw value from the request.
     * @return mixed          Cleaned value safe to persist.
     */
    public function sanitize(mixed $value): mixed
    {
        return $value;
    }

    /**
     * Default value used when creating a new section of this type.
     *
     * Override to provide type-appropriate defaults (empty string vs empty
     * array vs false, etc.). The base implementation returns null which
     * the content JSON stores as an absent key.
     */
    public function default(): mixed
    {
        return null;
    }
}
