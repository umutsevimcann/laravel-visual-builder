<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Actions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Umutsevimcann\VisualBuilder\Contracts\BuilderRepositoryInterface;
use Umutsevimcann\VisualBuilder\Domain\Events\SectionCreated;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;
use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeRegistry;

/**
 * Create a new section on a buildable target.
 *
 * Flow:
 *  1. Validate the section type is registered in the registry.
 *  2. For singleton types (allowsMultipleInstances = false), refuse if a
 *     section with the same type+instance already exists on the target.
 *  3. Build an initial row from the type's defaultContent / defaultStyle.
 *  4. Place the new section:
 *       - at position `afterSortOrder + 1` if $afterSortOrder is passed
 *         (existing sections at or above that slot are shifted down by 1)
 *       - at the end of the current order otherwise
 *  5. Dispatch SectionCreated for cache invalidation and audit listeners.
 *
 * Position-aware insertion lets the Elementor-style `+` inserter inject
 * a new block between two existing ones without a second reorder call.
 */
final class CreateSection
{
    public function __construct(
        private readonly BuilderRepositoryInterface $repository,
        private readonly SectionTypeRegistry $registry,
    ) {}

    /**
     * @param  int|null  $afterSortOrder  When set, the new section takes the
     *                                    slot right after this sort_order
     *                                    value; every section currently at
     *                                    or after that slot gets bumped +1.
     *                                    Null (default) = append to end.
     *
     * @throws RuntimeException If the type is not registered or the singleton
     *                          constraint would be violated.
     */
    public function execute(
        Model $target,
        string $type,
        string $instanceKey = '__default__',
        null|int $afterSortOrder = null,
    ): BuilderSection {
        $sectionType = $this->registry->findOrFail($type);

        if (! $sectionType->allowsMultipleInstances()) {
            $existing = $this->repository->findByTypeInstance($target, $type, $instanceKey);
            if ($existing !== null) {
                throw new RuntimeException(
                    "Section type '{$type}' is a singleton and already exists on this target.",
                );
            }
        }

        $sortOrder = $afterSortOrder === null
            ? $this->nextSortOrder($target)
            : $this->insertAt($target, $afterSortOrder + 1);

        $section = $this->repository->create([
            'builder_type' => $target->getMorphClass(),
            'builder_id' => $target->getKey(),
            'type' => $type,
            'instance_key' => $instanceKey,
            'is_published' => true,
            'sort_order' => $sortOrder,
            'content' => $sectionType->defaultContent(),
            'style' => $sectionType->defaultStyle(),
        ]);

        SectionCreated::dispatch($section);

        return $section;
    }

    /**
     * Next sort_order value after the current max for this target.
     * New sections always append to the bottom — editors reorder via the UI.
     */
    private function nextSortOrder(Model $target): int
    {
        $existing = $this->repository->forTarget($target);

        if ($existing->isEmpty()) {
            return 0;
        }

        /** @var int $currentMax */
        $currentMax = $existing->max('sort_order') ?? -1;

        return $currentMax + 1;
    }

    /**
     * Reserve `sort_order = $position` by bumping every section at or
     * beyond that position by +1. Returns $position so the caller can
     * use it when creating the new row.
     *
     * Single atomic UPDATE so concurrent edits get consistent shifts
     * and we avoid N+1 increments.
     */
    private function insertAt(Model $target, int $position): int
    {
        BuilderSection::query()
            ->where('builder_type', $target->getMorphClass())
            ->where('builder_id', $target->getKey())
            ->where('sort_order', '>=', $position)
            ->increment('sort_order');

        return $position;
    }
}
