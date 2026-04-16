<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\DTOs;

use Illuminate\Http\Request;

/**
 * Immutable transport object for bulk builder save operations.
 *
 * The editor sends a single save request per click — it may contain:
 *  - A new order of section IDs (drag-reorder outcome)
 *  - Per-section content, style, or is_published updates
 *
 * Any subset of these fields may be present (or all absent — validation
 * in BuilderSaveRequest rejects empty payloads as a fail-fast guard).
 *
 * Typed as readonly so the save pipeline has a single source of truth
 * for input data without worrying about downstream mutation.
 */
final readonly class BuilderLayoutData
{
    /**
     * @param  array<int, int>|null  $orderedIds
     *                                            New sort order as a sequence of BuilderSection IDs, or null when
     *                                            the user did not change the order in this save.
     * @param  array<int, array{content?: array<string, mixed>, style?: array<string, mixed>, is_published?: bool}>|null  $sections
     *                                                                                                                               Per-section updates keyed by BuilderSection ID. Each payload
     *                                                                                                                               may contain any subset of content/style/is_published keys.
     */
    public function __construct(
        public array|null $orderedIds,
        public array|null $sections,
    ) {}

    /**
     * Construct from an HTTP request; honors FormRequest::validated() when
     * available, falls back to Request::all() for non-validated callers.
     */
    public static function fromRequest(Request $request): self
    {
        /** @var array<string, mixed> $input */
        $input = method_exists($request, 'validated') ? $request->validated() : $request->all();

        return new self(
            orderedIds: isset($input['ordered_ids']) && is_array($input['ordered_ids'])
                ? array_map('intval', $input['ordered_ids'])
                : null,
            sections: isset($input['sections']) && is_array($input['sections'])
                ? $input['sections']
                : null,
        );
    }
}
