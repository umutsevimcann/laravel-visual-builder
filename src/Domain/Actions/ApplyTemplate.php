<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Umutsevimcann\VisualBuilder\Contracts\BuilderRepositoryInterface;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderTemplate;
use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeRegistry;

/**
 * Apply a saved template to a target model.
 *
 * Two modes:
 *   - `replace`  Drop every existing top-level section on the target
 *                first (via soft-delete-aware repository calls),
 *                then insert the template's sections from sort_order 0.
 *   - `append`   Leave existing sections alone. New sections start at
 *                max(existing sort_order) + 1 and march upward.
 *
 * Unknown section types in the payload are skipped silently. The
 * registry is consulted for each entry — a template created when type
 * 'testimonials' was registered but applied on a host where it is not
 * lands with that entry omitted rather than rejecting the whole
 * operation. Skipped entries are counted in the return shape so the
 * admin UI can surface a "3 blocks omitted" notice.
 *
 * Children are inserted after their parent so the parent_id column can
 * use the just-inserted row's id — the snapshot carries children
 * inline, no remap table needed on the persistence side.
 *
 * Transaction-wrapped: either the full template applies or nothing does.
 */
final class ApplyTemplate
{
    public const MODE_REPLACE = 'replace';
    public const MODE_APPEND = 'append';

    public function __construct(
        private readonly BuilderRepositoryInterface $repository,
        private readonly SectionTypeRegistry $registry,
    ) {}

    /**
     * @return array{inserted: int, skipped: int}
     */
    public function execute(Model $target, BuilderTemplate $template, string $mode): array
    {
        if (! in_array($mode, [self::MODE_REPLACE, self::MODE_APPEND], true)) {
            throw new InvalidArgumentException(
                "Unknown apply mode '{$mode}'. Expected 'replace' or 'append'."
            );
        }

        // Template payload is free-form JSON — defensive filter keeps
        // only array-shaped entries so a hand-edited row in the DB with
        // a scalar stuck in the list can't crash the insert loop.
        $payload = array_values(array_filter(
            $template->payload ?? [],
            static fn ($entry): bool => is_array($entry),
        ));

        $result = ['inserted' => 0, 'skipped' => 0];

        DB::transaction(function () use ($target, $payload, $mode, &$result): void {
            if ($mode === self::MODE_REPLACE) {
                foreach ($this->repository->forTarget($target) as $existing) {
                    $this->repository->delete($existing);
                }
            }

            $baseOrder = $mode === self::MODE_APPEND
                ? $this->nextAppendOrder($target)
                : 0;

            foreach ($payload as $offset => $entry) {
                $insertedRoot = $this->insertWithChildren(
                    target: $target,
                    entry: $entry,
                    parentId: null,
                    columnIndex: null,
                    sortOrder: $baseOrder + $offset,
                    result: $result,
                );
                if ($insertedRoot === null) {
                    // Skipped (unknown type) — still count so the UI
                    // message is accurate.
                    continue;
                }
            }
        });

        return $result;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array{inserted: int, skipped: int}  $result
     */
    private function insertWithChildren(
        Model $target,
        array $entry,
        null|int $parentId,
        null|int $columnIndex,
        int $sortOrder,
        array &$result,
    ): null|BuilderSection {
        $type = is_string($entry['type'] ?? null) ? $entry['type'] : '';
        if ($type === '' || ! $this->registry->has($type)) {
            $result['skipped']++;

            return null;
        }

        $section = new BuilderSection([
            'builder_type' => $target->getMorphClass(),
            'builder_id' => $target->getKey(),
            'type' => $type,
            'instance_key' => is_string($entry['instance_key'] ?? null)
                ? $entry['instance_key']
                : '__default__',
            'parent_id' => $parentId,
            'column_index' => $columnIndex,
            'content' => is_array($entry['content'] ?? null) ? $entry['content'] : [],
            'style' => is_array($entry['style'] ?? null) ? $entry['style'] : [],
            'is_published' => (bool) ($entry['is_published'] ?? true),
            'sort_order' => $sortOrder,
        ]);
        $section->save();
        $result['inserted']++;

        $children = is_array($entry['children'] ?? null) ? $entry['children'] : [];
        foreach (array_values($children) as $childOffset => $childEntry) {
            if (! is_array($childEntry)) {
                continue;
            }
            $childColumnIndex = isset($childEntry['column_index']) && is_int($childEntry['column_index'])
                ? $childEntry['column_index']
                : 0;
            $this->insertWithChildren(
                target: $target,
                entry: $childEntry,
                parentId: (int) $section->id,
                columnIndex: $childColumnIndex,
                sortOrder: $childOffset,
                result: $result,
            );
        }

        return $section;
    }

    private function nextAppendOrder(Model $target): int
    {
        $existing = $this->repository->forTarget($target);
        if ($existing->isEmpty()) {
            return 0;
        }

        return ((int) $existing->max('sort_order')) + 1;
    }
}
