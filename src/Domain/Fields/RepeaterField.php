<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Fields;

/**
 * Variable-length list of nested field groups. Models 1-to-many structured
 * content within a section — testimonials, feature tiles, FAQ items, etc.
 *
 * Each repeater item is an associative array of sub-field values keyed by
 * the inner field's `key`. Translatable sub-fields produce locale maps as
 * usual; non-translatable sub-fields produce scalars.
 *
 * Example — FAQ repeater:
 *
 *     new RepeaterField(
 *         key: 'faqs',
 *         label: 'FAQ Items',
 *         itemFields: [
 *             new TextField('question', 'Question', required: true, translatable: true),
 *             new HtmlField('answer',   'Answer',   required: true),
 *         ],
 *         minItems: 1,
 *         maxItems: 20,
 *     )
 *
 * Stored content:
 *     [
 *       ["question" => ["en" => "..."], "answer" => ["en" => "<p>...</p>"]],
 *       ["question" => ["en" => "..."], "answer" => ["en" => "<p>...</p>"]],
 *     ]
 */
final class RepeaterField extends FieldDefinition
{
    /**
     * @param  array<int, FieldDefinition>  $itemFields  Sub-field definitions rendered inside each item.
     * @param  int   $minItems  Minimum items; 0 means the repeater may be empty.
     * @param  int   $maxItems  Maximum items; 0 means unlimited.
     */
    public function __construct(
        string $key,
        string $label,
        public readonly array $itemFields,
        ?string $help = null,
        public readonly int $minItems = 0,
        public readonly int $maxItems = 0,
        bool $toggleable = true,
    ) {
        parent::__construct(
            key: $key,
            label: $label,
            help: $help,
            required: false,
            translatable: false,
            toggleable: $toggleable,
        );
    }

    public function adminInputType(): string
    {
        return 'repeater';
    }

    public function validationRules(): array
    {
        $arrayRules = ['nullable', 'array'];

        if ($this->minItems > 0) {
            $arrayRules[] = "min:{$this->minItems}";
        }
        if ($this->maxItems > 0) {
            $arrayRules[] = "max:{$this->maxItems}";
        }

        $rules = [$this->key => $arrayRules];

        // Flatten each sub-field's rules under a wildcard item index.
        foreach ($this->itemFields as $field) {
            foreach ($field->validationRules() as $subKey => $subRules) {
                $rules["{$this->key}.*.{$subKey}"] = $subRules;
            }
        }

        return $rules;
    }

    /**
     * Sanitize each item by delegating to each sub-field's own sanitize().
     *
     * Non-array inputs are coerced to empty arrays. Inner items that are not
     * arrays are skipped — a defensive stance against malformed requests.
     */
    public function sanitize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return [];
        }

        $clean = [];
        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }

            $cleanItem = [];
            foreach ($this->itemFields as $field) {
                if (array_key_exists($field->key, $item)) {
                    $cleanItem[$field->key] = $field->sanitize($item[$field->key]);
                }
            }
            $clean[] = $cleanItem;
        }

        return $clean;
    }

    public function default(): mixed
    {
        return [];
    }
}
