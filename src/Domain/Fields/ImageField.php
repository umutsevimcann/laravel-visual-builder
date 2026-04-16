<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Fields;

/**
 * Image picker + upload field.
 *
 * Stored value is a relative storage path — resolved to a URL at render time
 * via the bound MediaServiceInterface. Supports three path shapes:
 *
 *  - Relative upload:       "visual-builder/abc123.jpg"
 *  - Pre-shipped asset:     "assets/img/placeholder.png"
 *  - Absolute URL:          "https://cdn.example.com/hero.webp"
 *
 * The admin UI drives the upload flow; this field only validates the stored
 * path's shape. Actual file upload happens through the builder's upload route
 * which delegates to MediaServiceInterface.
 */
final class ImageField extends FieldDefinition
{
    public function __construct(
        string $key,
        string $label,
        ?string $help = null,
        bool $required = false,
        public readonly ?string $defaultAsset = null,
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
        return 'image';
    }

    public function validationRules(): array
    {
        return [
            $this->key => [
                $this->required ? 'required' : 'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    public function default(): mixed
    {
        return $this->defaultAsset;
    }
}
