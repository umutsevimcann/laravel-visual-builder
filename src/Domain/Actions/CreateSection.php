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
 *  4. Place the new section at the end of the current order (max + 1).
 *  5. Dispatch SectionCreated for cache invalidation and audit listeners.
 */
final class CreateSection
{
    public function __construct(
        private readonly BuilderRepositoryInterface $repository,
        private readonly SectionTypeRegistry $registry,
    ) {}

    /**
     * @throws RuntimeException If the type is not registered or the singleton
     *                          constraint would be violated.
     */
    public function execute(
        Model $target,
        string $type,
        string $instanceKey = '__default__',
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

        $section = $this->repository->create([
            'builder_type' => $target->getMorphClass(),
            'builder_id' => $target->getKey(),
            'type' => $type,
            'instance_key' => $instanceKey,
            'is_published' => true,
            'sort_order' => $this->nextSortOrder($target),
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
}
