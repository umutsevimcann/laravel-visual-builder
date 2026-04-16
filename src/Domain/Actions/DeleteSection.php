<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Actions;

use RuntimeException;
use Umutsevimcann\VisualBuilder\Contracts\BuilderRepositoryInterface;
use Umutsevimcann\VisualBuilder\Domain\Events\SectionDeleted;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;
use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeRegistry;

/**
 * Delete a section after verifying the section type allows deletion.
 *
 * Some section types are mandatory (the hero on a homepage, the site footer,
 * etc.). Their SectionTypeInterface::isDeletable() returns false and this
 * Action refuses the delete with a clear error — catches both admin UI
 * bypass attempts AND direct API calls.
 *
 * Dispatches SectionDeleted after the database row is gone; the in-memory
 * model is still traversable at that point (listeners may read it to
 * cascade-clean any related resources).
 */
final class DeleteSection
{
    public function __construct(
        private readonly BuilderRepositoryInterface $repository,
        private readonly SectionTypeRegistry $registry,
    ) {}

    /**
     * @throws RuntimeException If the section's type forbids deletion.
     */
    public function execute(BuilderSection $section): void
    {
        $type = $this->registry->find($section->type);

        if ($type !== null && ! $type->isDeletable()) {
            throw new RuntimeException(
                "Section type '{$section->type}' is marked as non-deletable.",
            );
        }

        $this->repository->delete($section);
        SectionDeleted::dispatch($section);
    }
}
