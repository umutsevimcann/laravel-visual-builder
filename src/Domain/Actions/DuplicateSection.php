<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Actions;

use RuntimeException;
use Umutsevimcann\VisualBuilder\Contracts\BuilderRepositoryInterface;
use Umutsevimcann\VisualBuilder\Domain\Events\SectionCreated;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;
use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeRegistry;

/**
 * Clone an existing section. The copy:
 *  - Keeps the source's content + style (by value, not by reference).
 *  - Gets a unique instance_key (original + "_copy_" + timestamp suffix).
 *  - Starts UNPUBLISHED so editors can review the copy before going live.
 *  - Appends to the end of the target's sort order.
 *
 * For singleton section types, duplicate is NOT available — it would
 * violate the uniqueness constraint. The caller (usually an admin UI
 * button) should hide the action when allowsMultipleInstances = false.
 */
final class DuplicateSection
{
    public function __construct(
        private readonly BuilderRepositoryInterface $repository,
        private readonly SectionTypeRegistry $registry,
    ) {}

    /**
     * @throws RuntimeException When the source section's type is a singleton.
     */
    public function execute(BuilderSection $source): BuilderSection
    {
        $type = $this->registry->findOrFail($source->type);

        if (! $type->allowsMultipleInstances()) {
            throw new RuntimeException(
                "Section type '{$source->type}' is a singleton and cannot be duplicated.",
            );
        }

        $copy = $this->repository->create([
            'builder_type' => $source->builder_type,
            'builder_id' => $source->builder_id,
            'type' => $source->type,
            'instance_key' => $source->instance_key . '_copy_' . time(),
            'is_published' => false,
            'sort_order' => $this->nextSortOrder($source),
            'content' => $source->content,
            'style' => $source->style,
        ]);

        SectionCreated::dispatch($copy);

        return $copy;
    }

    private function nextSortOrder(BuilderSection $source): int
    {
        $target = $source->builder()->first();

        if ($target === null) {
            return $source->sort_order + 1;
        }

        $siblings = $this->repository->forTarget($target);
        /** @var int $currentMax */
        $currentMax = $siblings->max('sort_order') ?? -1;

        return $currentMax + 1;
    }
}
