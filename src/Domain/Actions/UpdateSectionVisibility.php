<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Actions;

use Illuminate\Support\Carbon;
use Umutsevimcann\VisualBuilder\Contracts\BuilderRepositoryInterface;
use Umutsevimcann\VisualBuilder\Domain\Events\SectionUpdated;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;

/**
 * Update a section's publish state and optional scheduling window.
 *
 * Atomic — changes is_published, starts_at, and ends_at in a single
 * persistence call. Dispatches SectionUpdated with a granular change list
 * so cache listeners know exactly which attribute(s) flipped.
 *
 * Scheduling semantics:
 *  - starts_at null → visible immediately (subject to is_published)
 *  - ends_at   null → no end date; visible indefinitely
 *  - Both set  → section visible only within the window
 */
final class UpdateSectionVisibility
{
    public function __construct(
        private readonly BuilderRepositoryInterface $repository,
    ) {}

    public function execute(
        BuilderSection $section,
        bool $isPublished,
        null|Carbon $startsAt = null,
        null|Carbon $endsAt = null,
    ): BuilderSection {
        $changes = [];

        if ($section->is_published !== $isPublished) {
            $changes[] = 'is_published';
        }
        if ($section->starts_at?->timestamp !== $startsAt?->timestamp) {
            $changes[] = 'starts_at';
        }
        if ($section->ends_at?->timestamp !== $endsAt?->timestamp) {
            $changes[] = 'ends_at';
        }

        $updated = $this->repository->update($section, [
            'is_published' => $isPublished,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        if ($changes !== []) {
            SectionUpdated::dispatch($updated, $changes);
        }

        return $updated;
    }
}
