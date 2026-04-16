<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Fields;

/**
 * Boolean on/off switch, rendered as a toggle in the admin UI.
 *
 * Non-translatable by design — booleans don't vary by language. The
 * `toggleable` per-field visibility flag is forced to false: a toggle's
 * own value already expresses show/hide, a secondary eye icon would be
 * redundant and confusing.
 */
final class ToggleField extends FieldDefinition
{
    public function __construct(
        string $key,
        string $label,
        null|string $help = null,
        public readonly bool $defaultValue = true,
    ) {
        parent::__construct(
            key: $key,
            label: $label,
            help: $help,
            required: false,
            translatable: false,
            toggleable: false,
        );
    }

    public function adminInputType(): string
    {
        return 'toggle';
    }

    public function validationRules(): array
    {
        return [$this->key => ['nullable', 'boolean']];
    }

    /**
     * Coerce any truthy/falsy representation into a strict bool.
     * Handles strings ("1", "true"), ints (0/1), and native booleans.
     */
    public function sanitize(mixed $value): mixed
    {
        return (bool) $value;
    }

    public function default(): mixed
    {
        return $this->defaultValue;
    }
}
