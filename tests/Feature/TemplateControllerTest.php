<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderTemplate;
use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeRegistry;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\HeadingWidget;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\ParagraphWidget;
use Umutsevimcann\VisualBuilder\Tests\Stubs\Models\TestPage;

/*
 * HTTP-layer coverage for TemplateController (v0.6.0). The domain
 * layer is already locked by TemplateLibraryTest — these tests
 * focus on the four endpoint contracts: correct status codes,
 * correct JSON shapes, correct target resolution errors.
 *
 * Targets: uses TestPage + the 'page' morph alias registered here.
 */

beforeEach(function (): void {
    Relation::enforceMorphMap([
        'page' => TestPage::class,
    ]);

    /** @var SectionTypeRegistry $registry */
    $registry = app(SectionTypeRegistry::class);
    if (! $registry->has('heading')) {
        $registry->register(new HeadingWidget);
    }
    if (! $registry->has('paragraph')) {
        $registry->register(new ParagraphWidget);
    }
});

function makeControllerTestPage(): TestPage
{
    return TestPage::create(['title' => 'Template controller fixture']);
}

it('GET /templates returns an empty list when the library is empty', function (): void {
    $response = $this->getJson('/visual-builder/templates');

    $response->assertOk()
        ->assertExactJson(['templates' => []]);
});

it('GET /templates returns saved templates newest-first with shape-light entries', function (): void {
    BuilderTemplate::create([
        'name' => 'Older',
        'type' => 'section',
        'payload' => [['type' => 'heading']],
        'created_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ]);
    BuilderTemplate::create([
        'name' => 'Newer',
        'description' => 'A recent one',
        'type' => 'section',
        'payload' => [['type' => 'paragraph'], ['type' => 'heading']],
    ]);

    $response = $this->getJson('/visual-builder/templates');

    $response->assertOk()
        ->assertJsonStructure(['templates' => [['id', 'name', 'description', 'type', 'section_count', 'created_at']]]);

    $templates = $response->json('templates');
    expect($templates)->toHaveCount(2)
        ->and($templates[0]['name'])->toBe('Newer')
        ->and($templates[0]['section_count'])->toBe(2)
        ->and($templates[1]['section_count'])->toBe(1);
});

it('POST /templates/{type}/{id} snapshots current sections as a new template', function (): void {
    $page = makeControllerTestPage();
    BuilderSection::create([
        'builder_type' => 'page',
        'builder_id' => $page->id,
        'type' => 'heading',
        'instance_key' => 'k1',
        'content' => [],
        'style' => [],
        'is_published' => true,
        'sort_order' => 0,
    ]);

    $response = $this->postJson("/visual-builder/templates/page/{$page->id}", [
        'name' => 'Saved snapshot',
        'description' => 'Quick description',
    ]);

    $response->assertCreated()
        ->assertJson([
            'success' => true,
            'template' => ['name' => 'Saved snapshot', 'section_count' => 1],
        ]);

    expect(BuilderTemplate::query()->count())->toBe(1);
});

it('POST /templates/{type}/{id} rejects a missing name with 422', function (): void {
    $page = makeControllerTestPage();

    $this->postJson("/visual-builder/templates/page/{$page->id}", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('POST /templates/{type}/{id} 404s for an unknown morph type', function (): void {
    $this->postJson('/visual-builder/templates/ghost/1', ['name' => 'x'])
        ->assertNotFound();
});

it('POST /templates/{id}/apply/{type}/{id} creates sections in append mode', function (): void {
    $sourcePage = makeControllerTestPage();
    BuilderSection::create([
        'builder_type' => 'page', 'builder_id' => $sourcePage->id,
        'type' => 'heading', 'instance_key' => 's1',
        'content' => [], 'style' => [], 'is_published' => true, 'sort_order' => 0,
    ]);

    $template = BuilderTemplate::create([
        'name' => 'Apply fixture',
        'type' => 'section',
        'payload' => [[
            'type' => 'heading', 'instance_key' => 'x',
            'content' => [], 'style' => [],
            'is_published' => true, 'sort_order' => 0, 'children' => [],
        ]],
    ]);

    $destination = makeControllerTestPage();

    $response = $this->postJson(
        "/visual-builder/templates/{$template->id}/apply/page/{$destination->id}",
        ['mode' => 'append']
    );

    $response->assertOk()
        ->assertJson(['success' => true, 'inserted' => 1, 'skipped' => 0]);
});

it('POST apply rejects an unknown mode value with 422', function (): void {
    $template = BuilderTemplate::create([
        'name' => 't', 'type' => 'section', 'payload' => [],
    ]);
    $page = makeControllerTestPage();

    $this->postJson(
        "/visual-builder/templates/{$template->id}/apply/page/{$page->id}",
        ['mode' => 'overwrite']
    )->assertStatus(422)
        ->assertJsonValidationErrors(['mode']);
});

it('POST apply 404s when the template id does not exist', function (): void {
    $page = makeControllerTestPage();

    $this->postJson(
        "/visual-builder/templates/99999/apply/page/{$page->id}",
        ['mode' => 'append']
    )->assertNotFound();
});

it('DELETE /templates/{id} removes the template', function (): void {
    $template = BuilderTemplate::create([
        'name' => 'To delete', 'type' => 'section', 'payload' => [],
    ]);

    $this->deleteJson("/visual-builder/templates/{$template->id}")
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(BuilderTemplate::query()->find($template->id))->toBeNull();
});

it('DELETE /templates/{id} 404s for an unknown id', function (): void {
    $this->deleteJson('/visual-builder/templates/99999')
        ->assertNotFound();
});
