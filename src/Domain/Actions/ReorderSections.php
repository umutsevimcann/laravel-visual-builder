<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Actions;

use Illuminate\Database\Eloquent\Model;
use Umutsevimcann\VisualBuilder\Contracts\BuilderRepositoryInterface;
use Umutsevimcann\VisualBuilder\Domain\Events\SectionsReordered;

/**
 * Bulk-apply a new sort order after drag-drop reorder in the admin UI.
 *
 * Delegates the actual write to the repository (which runs in a transaction)
 * and dispatches SectionsReordered once all writes complete. The event
 * carries the target model + new ID sequence so listeners can invalidate
 * target-scoped caches (e.g. "homepage.en", "post-42.de").
 *
 * Unknown IDs are silently dropped at the repository level — the UI may
 * send stale IDs after concurrent edits and we don't want to fail the
 * whole reorder over a single already-deleted section.
 */
final class ReorderSections
{
    public function __construct(
        private readonly BuilderRepositoryInterface $repository,
    ) {}

    /**
     * @param  array<int, int>  $orderedIds  BuilderSection IDs in desired order.
     */
    public function execute(Model $target, array $orderedIds): void
    {
        if ($orderedIds === []) {
            return;
        }

        $this->repository->reorder($orderedIds);

        SectionsReordered::dispatch($target, $orderedIds);
    }
}
