<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Umutsevimcann\VisualBuilder\Contracts\BuilderRepositoryInterface;
use Umutsevimcann\VisualBuilder\Domain\DTOs\BuilderLayoutData;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;

/**
 * Apply a batched save payload atomically.
 *
 * The admin UI coalesces all pending changes into a single save request:
 *   - Per-section content patches
 *   - Per-section style patches
 *   - Per-section visibility flips
 *   - A new sort order across all sections
 *
 * This action orchestrates all sub-actions within ONE database transaction —
 * if anything fails (unknown section ID, validation, etc.) the entire save
 * rolls back. The editor sees either "everything saved" or "nothing saved",
 * never a partial state.
 *
 * Per-section sub-action delegation:
 *   - content        → UpdateSectionContent
 *   - style          → UpdateSectionStyle
 *   - is_published   → UpdateSectionVisibility (keeps existing schedule)
 *   - order change   → ReorderSections (last, outside per-section loop)
 *
 * Returns a summary telling the caller which IDs were touched and whether
 * reordering happened — used by the HTTP layer to shape its JSON response.
 */
final class ApplyBuilderLayout
{
    public function __construct(
        private readonly BuilderRepositoryInterface $repository,
        private readonly UpdateSectionContent $updateContent,
        private readonly UpdateSectionStyle $updateStyle,
        private readonly UpdateSectionVisibility $updateVisibility,
        private readonly ReorderSections $reorder,
    ) {}

    /**
     * @return array{updated: array<int, int>, reordered: bool}
     *         updated  = BuilderSection IDs that received at least one patch
     *         reordered = whether sort order was changed in this call
     */
    public function execute(Model $target, BuilderLayoutData $data): array
    {
        $updatedIds = [];
        $reordered = false;

        DB::transaction(function () use ($target, $data, &$updatedIds, &$reordered): void {
            if ($data->sections !== null) {
                foreach ($data->sections as $sectionId => $payload) {
                    $section = $this->repository->find((int) $sectionId);
                    if ($section === null) {
                        continue; // Stale ID — ignore, reorder step will clean up
                    }

                    $this->applySectionPayload($section, $payload);
                    $updatedIds[] = $section->id;
                }
            }

            if ($data->orderedIds !== null && $data->orderedIds !== []) {
                $this->reorder->execute($target, $data->orderedIds);
                $reordered = true;
            }
        });

        return ['updated' => $updatedIds, 'reordered' => $reordered];
    }

    /**
     * Dispatch a single section's patch to the appropriate sub-actions.
     *
     * @param  array{content?: array<string, mixed>, style?: array<string, mixed>, is_published?: bool}  $payload
     */
    private function applySectionPayload(BuilderSection $section, array $payload): void
    {
        if (isset($payload['content']) && is_array($payload['content'])) {
            $this->updateContent->execute($section, $payload['content']);
            $section = $this->repository->find($section->id) ?? $section;
        }

        if (isset($payload['style']) && is_array($payload['style'])) {
            $this->updateStyle->execute($section, $payload['style']);
            $section = $this->repository->find($section->id) ?? $section;
        }

        if (array_key_exists('is_published', $payload)) {
            $this->updateVisibility->execute(
                $section,
                (bool) $payload['is_published'],
                $section->starts_at,
                $section->ends_at,
            );
        }
    }
}
