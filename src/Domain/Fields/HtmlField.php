<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Fields;

use Umutsevimcann\VisualBuilder\Contracts\SanitizerInterface;

/**
 * Rich-text editor field (WYSIWYG). Always translatable — HTML content
 * almost always needs per-language authoring.
 *
 * Values are stored as locale maps of sanitized HTML:
 *     ["en" => "<p>Hello</p>", "de" => "<p>Hallo</p>"]
 *
 * All incoming HTML passes through SanitizerInterface before persistence.
 * XSS vectors (script tags, event handlers, javascript: URIs) are stripped.
 */
final class HtmlField extends FieldDefinition
{
    public function __construct(
        string $key,
        string $label,
        ?string $help = null,
        bool $required = false,
        ?string $placeholder = null,
        bool $toggleable = true,
    ) {
        parent::__construct(
            key: $key,
            label: $label,
            help: $help,
            required: $required,
            translatable: true,
            placeholder: $placeholder,
            toggleable: $toggleable,
        );
    }

    public function adminInputType(): string
    {
        return 'html';
    }

    public function validationRules(): array
    {
        $rules = [$this->key => ['array']];
        $fallback = (string) config('app.fallback_locale', 'en');
        $locales = (array) config('visual-builder.locales', config('app.locales', ['en']));

        foreach ($locales as $locale) {
            $rules["{$this->key}.{$locale}"] = [
                $this->required && $locale === $fallback ? 'required' : 'nullable',
                'string',
            ];
        }

        return $rules;
    }

    /**
     * Sanitize each locale's HTML via the bound SanitizerInterface.
     *
     * Non-string and missing values are normalized to empty strings so the
     * output is always a valid locale map with consistent types.
     */
    public function sanitize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return [];
        }

        $sanitizer = app(SanitizerInterface::class);
        $clean = [];

        foreach ($value as $locale => $html) {
            $clean[$locale] = (is_string($html) && $html !== '')
                ? $sanitizer->purify($html)
                : '';
        }

        return $clean;
    }
}
