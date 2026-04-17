<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Actions;

use Illuminate\Database\Eloquent\Model;
use Umutsevimcann\VisualBuilder\Contracts\BuilderRepositoryInterface;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderTemplate;

/**
 * Capture the current sections of a target model as a reusable template.
 *
 * Snapshot shape persisted in `payload`:
 *
 *     [
 *         [
 *             'type'          => 'hero',
 *             'instance_key'  => '__default__',
 *             'content'       => [...],
 *             'style'         => [...],
 *             'is_published'  => true,
 *             'sort_order'    => 0,
 *             'column_index'  => null,
 *             'children'      => [ {nested snapshot}, ... ],
 *         ],
 *         ...
 *     ]
 *
 * IDs and polymorphic owner references are intentionally NOT serialized.
 * A template is portable across targets; the Apply action re-generates
 * primary keys and wires the child->parent relationships using a
 * parent-id remap table built during insertion.
 *
 * Only top-level sections are read here (those with parent_id IS NULL —
 * the repository already filters to top level). Children are included
 * recursively via `orderedChildren()`.
 */
final class SaveAsTemplate
{
    public function __construct(
        private readonly BuilderRepositoryInterface $repository,
    ) {}

    public function execute(Model $target, string $name, null|string $description = null): BuilderTemplate
    {
        $topLevel = $this->repository->forTarget($target);

        $payload = $topLevel->map(fn (BuilderSection $s) => $this->snapshot($s))->all();

        return BuilderTemplate::create([
            'name' => trim($name),
            'description' => $description !== null ? trim($description) : null,
            'type' => 'section',
            'payload' => $payload,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(BuilderSection $section): array
    {
        $children = $section->orderedChildren();

        return [
            'type' => (string) $section->type,
            'instance_key' => (string) $section->instance_key,
            'content' => $section->content ?? [],
            'style' => $section->style ?? [],
            'is_published' => (bool) $section->is_published,
            'sort_order' => (int) $section->sort_order,
            'column_index' => $section->column_index !== null ? (int) $section->column_index : null,
            'children' => $children->map(fn (BuilderSection $c) => $this->snapshot($c))->all(),
        ];
    }
}
