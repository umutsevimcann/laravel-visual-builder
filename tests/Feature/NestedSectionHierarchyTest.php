<?php

declare(strict_types=1);

use Umutsevimcann\VisualBuilder\Contracts\BuilderRepositoryInterface;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;
use Umutsevimcann\VisualBuilder\Tests\Stubs\Models\TestPage;

/*
 * Verifies the parent_id / column_index foundation that ColumnsWidget
 * (and future nested containers) rely on. Three contracts:
 *
 *   1. Repository::forTarget returns TOP-LEVEL sections only.
 *   2. BuilderSection->children returns direct descendants ordered by
 *      column_index then sort_order.
 *   3. BuilderSection->childrenInColumn(n) filters to one column slot.
 */

function makeNestedPage(): TestPage
{
    return TestPage::create(['title' => 'Nested hierarchy page']);
}

function makeNested(TestPage $page, string $type, array $overrides = []): BuilderSection
{
    return BuilderSection::create(array_merge([
        'builder_type' => $page->getMorphClass(),
        'builder_id' => $page->id,
        'type' => $type,
        'instance_key' => 'k-'.uniqid(),
        'parent_id' => null,
        'column_index' => null,
        'content' => [],
        'style' => [],
        'is_published' => true,
        'sort_order' => 0,
    ], $overrides));
}

it('repository forTarget omits children rows so the admin list stays flat', function (): void {
    $page = makeNestedPage();
    $container = makeNested($page, 'columns');
    makeNested($page, 'heading', ['parent_id' => $container->id, 'column_index' => 0, 'sort_order' => 1]);
    makeNested($page, 'paragraph', ['parent_id' => $container->id, 'column_index' => 0, 'sort_order' => 2]);
    makeNested($page, 'button', ['parent_id' => $container->id, 'column_index' => 1, 'sort_order' => 1]);
    $anotherTopLevel = makeNested($page, 'heading', ['sort_order' => 5]);

    $repo = app(BuilderRepositoryInterface::class);
    $top = $repo->forTarget($page);

    expect($top)->toHaveCount(2)
        ->and($top->pluck('id')->all())->toEqual([$container->id, $anotherTopLevel->id]);
});

it('repository visibleForTarget also omits children rows', function (): void {
    $page = makeNestedPage();
    $container = makeNested($page, 'columns');
    makeNested($page, 'heading', ['parent_id' => $container->id, 'column_index' => 0]);

    $repo = app(BuilderRepositoryInterface::class);
    expect($repo->visibleForTarget($page))->toHaveCount(1);
});

it('orderedChildren returns direct descendants ordered by column then sort', function (): void {
    $page = makeNestedPage();
    $container = makeNested($page, 'columns');

    $colA2 = makeNested($page, 'paragraph', ['parent_id' => $container->id, 'column_index' => 0, 'sort_order' => 2]);
    $colA1 = makeNested($page, 'heading', ['parent_id' => $container->id, 'column_index' => 0, 'sort_order' => 1]);
    $colB1 = makeNested($page, 'button', ['parent_id' => $container->id, 'column_index' => 1, 'sort_order' => 1]);

    $children = $container->orderedChildren();

    expect($children->pluck('id')->all())
        ->toEqual([$colA1->id, $colA2->id, $colB1->id]);
});

it('childrenInColumn filters to a single column slot', function (): void {
    $page = makeNestedPage();
    $container = makeNested($page, 'columns');

    makeNested($page, 'heading', ['parent_id' => $container->id, 'column_index' => 0, 'sort_order' => 1]);
    makeNested($page, 'paragraph', ['parent_id' => $container->id, 'column_index' => 0, 'sort_order' => 2]);
    $colB = makeNested($page, 'button', ['parent_id' => $container->id, 'column_index' => 1, 'sort_order' => 1]);

    expect($container->childrenInColumn(0)->count())->toBe(2)
        ->and($container->childrenInColumn(1)->pluck('id')->all())->toEqual([$colB->id])
        ->and($container->childrenInColumn(5)->count())->toBe(0);
});

it('parent belongsTo resolves upward to the container section', function (): void {
    $page = makeNestedPage();
    $container = makeNested($page, 'columns');
    $child = makeNested($page, 'heading', ['parent_id' => $container->id, 'column_index' => 0]);

    expect($child->parent->id)->toBe($container->id);
});
