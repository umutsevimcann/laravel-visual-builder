<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Fields;

/**
 * Combined URL + optional button label. A common composite for CTAs.
 *
 * Stores under two sibling keys in content JSON:
 *   {key}_url   — string (non-translatable)
 *   {key}_label — locale map when withLabel=true, absent otherwise
 *
 * Example content for a LinkField('cta'):
 *     "cta_url"   => "/contact",
 *     "cta_label" => { "en" => "Contact us", "de" => "Kontakt" }
 *
 * When withLabel=false (icon-only buttons, etc.), only `{key}_url` is stored.
 */
final class LinkField extends FieldDefinition
{
    public function __construct(
        string $key,
        string $label,
        null|string $help = null,
        bool $required = false,
        public readonly bool $withLabel = true,
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
        return 'link';
    }

    public function validationRules(): array
    {
        $rules = [
            "{$this->key}_url" => [
                $this->required ? 'required' : 'nullable',
                'string',
                'max:500',
            ],
        ];

        if ($this->withLabel) {
            $rules["{$this->key}_label"] = ['nullable', 'array'];
            $locales = (array) config('visual-builder.locales', config('app.locales', ['en']));
            foreach ($locales as $locale) {
                $rules["{$this->key}_label.{$locale}"] = ['nullable', 'string', 'max:100'];
            }
        }

        return $rules;
    }
}
