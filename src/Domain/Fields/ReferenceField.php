<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Fields;

use Closure;

/**
 * Reference to one or many records from an external model.
 *
 * Stored value is either a single integer ID (`multiple=false`) or an array
 * of integer IDs preserving the chosen order (`multiple=true`).
 *
 * Options are produced at render time by calling the supplied resolver —
 * this keeps the package decoupled from the user's domain models. The
 * resolver receives the current target model (the page/post/etc. being
 * edited) and must return a collection of ['id' => int, 'label' => string].
 *
 * Example — featured products picker:
 *
 *     new ReferenceField(
 *         key: 'featured_product_ids',
 *         label: 'Featured Products',
 *         multiple: true,
 *         optionsResolver: fn() => \App\Models\Product::published()
 *             ->orderBy('sort_order')
 *             ->get()
 *             ->map(fn($p) => ['id' => $p->id, 'label' => $p->name])
 *             ->all(),
 *     )
 */
final class ReferenceField extends FieldDefinition
{
    /**
     * @param  Closure(mixed=): array<int, array{id: int, label: string}>  $optionsResolver
     */
    public function __construct(
        string $key,
        string $label,
        public readonly Closure $optionsResolver,
        ?string $help = null,
        bool $required = false,
        public readonly bool $multiple = true,
        public readonly ?int $maxItems = null,
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
        return 'reference';
    }

    public function validationRules(): array
    {
        if (! $this->multiple) {
            return [
                $this->key => [
                    $this->required ? 'required' : 'nullable',
                    'integer',
                    'min:1',
                ],
            ];
        }

        $arrayRules = ['array'];
        if ($this->required) {
            $arrayRules[] = 'min:1';
        }
        if ($this->maxItems !== null) {
            $arrayRules[] = "max:{$this->maxItems}";
        }

        return [
            $this->key => array_merge(
                [$this->required ? 'required' : 'nullable'],
                $arrayRules,
            ),
            "{$this->key}.*" => ['integer', 'min:1'],
        ];
    }

    /**
     * Resolve the live option list using the user-provided closure.
     *
     * Called at render time by the admin controller. The target model is
     * passed so resolvers can scope options (e.g. only products in the
     * current category).
     *
     * @return array<int, array{id: int, label: string}>
     */
    public function resolveOptions(mixed $target = null): array
    {
        return ($this->optionsResolver)($target);
    }

    public function default(): mixed
    {
        return $this->multiple ? [] : null;
    }
}
