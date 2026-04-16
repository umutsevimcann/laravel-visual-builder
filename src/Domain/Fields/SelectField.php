<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Fields;

use Illuminate\Validation\Rule;

/**
 * Dropdown of fixed options. Non-translatable — the stored value is a
 * stable key (e.g. "left", "center", "right"); labels are localized in
 * the admin UI only.
 *
 * Example usage:
 *     new SelectField('alignment', 'Alignment', options: [
 *         'left'   => 'Left',
 *         'center' => 'Center',
 *         'right'  => 'Right',
 *     ])
 */
final class SelectField extends FieldDefinition
{
    /**
     * @param  array<string, string>  $options  Map of stored-key => display-label.
     */
    public function __construct(
        string $key,
        string $label,
        public readonly array $options,
        ?string $help = null,
        bool $required = false,
        public readonly ?string $defaultValue = null,
        bool $toggleable = true,
    ) {
        parent::__construct(
            key: $key,
            label: $label,
            help: $help,
            required: $required,
            translatable: false,
            toggleable: $toggleable,
        );
    }

    public function adminInputType(): string
    {
        return 'select';
    }

    public function validationRules(): array
    {
        return [
            $this->key => [
                $this->required ? 'required' : 'nullable',
                'string',
                Rule::in(array_keys($this->options)),
            ],
        ];
    }

    public function default(): mixed
    {
        return $this->defaultValue;
    }
}
