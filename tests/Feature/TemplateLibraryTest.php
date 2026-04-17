<?php

declare(strict_types=1);

use Umutsevimcann\VisualBuilder\Domain\Actions\ApplyTemplate;
use Umutsevimcann\VisualBuilder\Domain\Actions\SaveAsTemplate;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderTemplate;
use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeRegistry;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\ColumnsWidget;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\HeadingWidget;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\ParagraphWidget;
use Umutsevimcann\VisualBuilder\Tests\Stubs\Models\TestPage;

/*
 * Covers the round-trip of SaveAsTemplate + ApplyTemplate:
 *
 *   [target A] → SaveAsTemplate → BuilderTemplate row
 *              ↓
 *   BuilderTemplate row → ApplyTemplate(replace|append) → [target B]
 *
 * Verifies:
 *   1. Snapshot captures top-level AND nested children.
 *   2. Apply in REPLACE mode drops existing sections first.
 *   3. Apply in APPEND mode preserves existing sections.
 *   4. Unknown section types in payload are skipped, not fatal.
 *   5. sort_order renumbers correctly per mode.
 */

function registerStandardWidgets(): void
{
    /** @var SectionTypeRegistry $registry */
    $registry = app(SectionTypeRegistry::class);
    if (! $registry->has('heading')) {
        $registry->register(new HeadingWidget);
    }
    if (! $registry->has('paragraph')) {
        $registry->register(new ParagraphWidget);
    }
}

function makeTplPage(): TestPage
{
    return TestPage::create(['title' => 'Template fixture page']);
}

function makeTplSection(TestPage $page, string $type, int $sortOrder, null|int $parentId = null, null|int $colIdx = null): BuilderSection
{
    return BuilderSection::create([
        'builder_type' => $page->getMorphClass(),
        'builder_id' => $page->id,
        'type' => $type,
        'instance_key' => 'k-'.uniqid(),
        'parent_id' => $parentId,
        'column_index' => $colIdx,
        'content' => ['text' => ['en' => 'Sample '.$type]],
        'style' => [],
        'is_published' => true,
        'sort_order' => $sortOrder,
    ]);
}

beforeEach(function (): void {
    registerStandardWidgets();
});

it('SaveAsTemplate snapshots top-level sections in order', function (): void {
    $page = makeTplPage();
    makeTplSection($page, 'heading', 0);
    makeTplSection($page, 'paragraph', 1);
    makeTplSection($page, 'heading', 2);

    $template = app(SaveAsTemplate::class)->execute($page, 'Three-block stack', 'Hero + intro + closing');

    expect($template)->toBeInstanceOf(BuilderTemplate::class)
        ->and($template->name)->toBe('Three-block stack')
        ->and($template->type)->toBe('section')
        ->and($template->payload)->toHaveCount(3)
        ->and($template->payload[0]['type'])->toBe('heading')
        ->and($template->payload[1]['type'])->toBe('paragraph')
        ->and($template->payload[2]['type'])->toBe('heading');
});

it('SaveAsTemplate captures nested children inline under their parent', function (): void {
    $page = makeTplPage();
    registerStandardWidgets();
    app(SectionTypeRegistry::class)->has('columns') or app(SectionTypeRegistry::class)->register(
        new ColumnsWidget
    );
    $container = makeTplSection($page, 'columns', 0);
    makeTplSection($page, 'heading', 0, $container->id, 0);
    makeTplSection($page, 'paragraph', 1, $container->id, 0);
    makeTplSection($page, 'heading', 0, $container->id, 1);

    $template = app(SaveAsTemplate::class)->execute($page, 'Two-col feature');

    expect($template->payload)->toHaveCount(1)
        ->and($template->payload[0]['type'])->toBe('columns')
        ->and($template->payload[0]['children'])->toHaveCount(3);
});

it('ApplyTemplate in append mode preserves existing sections and inserts after them', function (): void {
    $source = makeTplPage();
    makeTplSection($source, 'heading', 0);
    makeTplSection($source, 'paragraph', 1);
    $template = app(SaveAsTemplate::class)->execute($source, 'Snapshot');

    $destination = makeTplPage();
    $existing = makeTplSection($destination, 'paragraph', 0);

    $result = app(ApplyTemplate::class)->execute($destination, $template, ApplyTemplate::MODE_APPEND);

    expect($result)->toBe(['inserted' => 2, 'skipped' => 0]);

    $all = BuilderSection::query()
        ->where('builder_type', $destination->getMorphClass())
        ->where('builder_id', $destination->id)
        ->orderBy('sort_order')
        ->get();

    expect($all->pluck('type')->all())->toEqual(['paragraph', 'heading', 'paragraph'])
        ->and($all->first()->id)->toBe($existing->id);
});

it('ApplyTemplate in replace mode drops existing top-level sections before inserting', function (): void {
    $source = makeTplPage();
    makeTplSection($source, 'heading', 0);
    $template = app(SaveAsTemplate::class)->execute($source, 'One-block');

    $destination = makeTplPage();
    makeTplSection($destination, 'paragraph', 0);
    makeTplSection($destination, 'paragraph', 1);

    $result = app(ApplyTemplate::class)->execute($destination, $template, ApplyTemplate::MODE_REPLACE);

    expect($result)->toBe(['inserted' => 1, 'skipped' => 0]);

    $all = BuilderSection::query()
        ->where('builder_type', $destination->getMorphClass())
        ->where('builder_id', $destination->id)
        ->get();

    expect($all)->toHaveCount(1)
        ->and($all->first()->type)->toBe('heading');
});

it('ApplyTemplate skips payload entries whose type is no longer registered', function (): void {
    $page = makeTplPage();

    $template = BuilderTemplate::create([
        'name' => 'Forward-compat test',
        'type' => 'section',
        'payload' => [
            ['type' => 'heading', 'content' => [], 'style' => [], 'is_published' => true, 'sort_order' => 0, 'children' => []],
            ['type' => 'not_registered', 'content' => [], 'style' => [], 'is_published' => true, 'sort_order' => 1, 'children' => []],
            ['type' => 'paragraph', 'content' => [], 'style' => [], 'is_published' => true, 'sort_order' => 2, 'children' => []],
        ],
    ]);

    $result = app(ApplyTemplate::class)->execute($page, $template, ApplyTemplate::MODE_APPEND);

    expect($result)->toBe(['inserted' => 2, 'skipped' => 1]);
});

it('ApplyTemplate rejects an unknown mode', function (): void {
    $template = BuilderTemplate::create([
        'name' => 't',
        'type' => 'section',
        'payload' => [],
    ]);

    app(ApplyTemplate::class)->execute(makeTplPage(), $template, 'overwrite');
})->throws(InvalidArgumentException::class);
