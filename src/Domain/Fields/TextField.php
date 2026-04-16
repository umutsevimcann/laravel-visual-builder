<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Fields;

/**
 * Single-line text input. The most common field type.
 *
 * Optionally translatable — translatable values are stored as locale maps:
 *     ["en" => "Welcome", "de" => "Willkommen"]
 *
 * Non-translatable values are stored as plain strings:
 *     "https://example.com/video.mp4"
 *
 * Validation enforces max length (default 255). For translatable fields,
 * the `required` flag applies only to the fallback locale — secondary
 * locales are always optional to let editors fill translations later.
 */
final class TextField extends FieldDefinition
{
    public function __construct(
        string $key,
        string $label,
        null|string $help = null,
        bool $required = false,
        bool $translatable = false,
        null|string $placeholder = null,
        public readonly int $maxLength = 255,
        bool $toggleable = true,
    ) {
        parent::__construct($key, $label, $help, $required, $translatable, $placeholder, $toggleable);
    }

    public function adminInputType(): string
    {
        return 'text';
    }

    public function validationRules(): array
    {
        if (! $this->translatable) {
            return [
                $this->key => [
                    $this->required ? 'required' : 'nullable',
                    'string',
                    "max:{$this->maxLength}",
                ],
            ];
        }

        $rules = [$this->key => ['array']];
        $fallback = (string) config('app.fallback_locale', 'en');
        $locales = (array) config('visual-builder.locales', config('app.locales', ['en']));

        foreach ($locales as $locale) {
            $rules["{$this->key}.{$locale}"] = [
                $this->required && $locale === $fallback ? 'required' : 'nullable',
                'string',
                "max:{$this->maxLength}",
            ];
        }

        return $rules;
    }
}
