<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Umutsevimcann\VisualBuilder\Domain\Events\SectionCreated;
use Umutsevimcann\VisualBuilder\Domain\Events\SectionDeleted;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;
use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeRegistry;
use Umutsevimcann\VisualBuilder\Tests\Stubs\Models\TestPage;
use Umutsevimcann\VisualBuilder\Tests\Stubs\Sections\FakeMultiSectionType;
use Umutsevimcann\VisualBuilder\Tests\Stubs\Sections\FakeSectionType;

beforeEach(function (): void {
    Relation::enforceMorphMap([
        'testpage' => TestPage::class,
    ]);

    $registry = app(SectionTypeRegistry::class);
    $registry->register(new FakeSectionType);
    $registry->register(new FakeMultiSectionType);
});

function makeTestPage(): TestPage
{
    return TestPage::query()->create(['title' => 'Home']);
}

it('POST /{type}/{id}/sections creates a section with defaults', function (): void {
    Event::fake();
    $page = makeTestPage();

    $response = $this->post("/visual-builder/testpage/{$page->id}/sections", [
        'type' => 'fake_hero',
    ]);

    $response->assertRedirect();

    $section = BuilderSection::query()->first();
    expect($section)->not->toBeNull()
        ->and($section->type)->toBe('fake_hero')
        ->and($section->instance_key)->toBe('__default__')
        ->and($section->builder_type)->toBe('testpage')
        ->and($section->builder_id)->toBe($page->id)
        ->and($section->is_published)->toBeTrue()
        ->and($section->content['headline']['en'] ?? null)->toBe('Default headline')
        ->and($section->style['padding_y'] ?? null)->toBe('48px');

    Event::assertDispatched(SectionCreated::class);
});

it('rejects a second singleton instance with 500-class error', function (): void {
    $page = makeTestPage();

    $this->post("/visual-builder/testpage/{$page->id}/sections", ['type' => 'fake_hero'])
        ->assertRedirect();

    // Second request for the same singleton type must fail hard —
    // the Action throws and Laravel's default exception handler
    // surfaces it as a 500 under Testbench's non-debug mode.
    $this->withoutExceptionHandling();

    expect(fn () => $this->post(
        "/visual-builder/testpage/{$page->id}/sections",
        ['type' => 'fake_hero'],
    ))->toThrow(RuntimeException::class);

    expect(BuilderSection::query()->count())->toBe(1);
});

it('allows multiple instances for non-singleton types', function (): void {
    $page = makeTestPage();

    $this->post(
        "/visual-builder/testpage/{$page->id}/sections",
        ['type' => 'fake_gallery', 'instance_key' => 'left'],
    )->assertRedirect();

    $this->post(
        "/visual-builder/testpage/{$page->id}/sections",
        ['type' => 'fake_gallery', 'instance_key' => 'right'],
    )->assertRedirect();

    expect(BuilderSection::query()->count())->toBe(2);
});

it('POST /{type}/{id}/save persists per-section content updates', function (): void {
    $page = makeTestPage();

    $section = BuilderSection::query()->create([
        'builder_type' => 'testpage',
        'builder_id' => $page->id,
        'type' => 'fake_hero',
        'instance_key' => '__default__',
        'is_published' => true,
        'sort_order' => 0,
        'content' => ['headline' => ['en' => 'Old']],
        'style' => [],
    ]);

    $response = $this->postJson("/visual-builder/testpage/{$page->id}/save", [
        'sections' => [
            (string) $section->id => [
                'content' => ['headline' => ['en' => 'New headline']],
                'style' => ['padding_y' => '120px'],
                'is_published' => false,
            ],
        ],
    ]);

    $response->assertOk()->assertJson(['success' => true]);

    $section->refresh();
    expect($section->content['headline']['en'])->toBe('New headline')
        ->and($section->style['padding_y'])->toBe('120px')
        ->and((bool) $section->is_published)->toBeFalse();
});

it('POST /{type}/{id}/save reorders sections via ordered_ids', function (): void {
    $page = makeTestPage();

    $a = BuilderSection::query()->create([
        'builder_type' => 'testpage', 'builder_id' => $page->id,
        'type' => 'fake_gallery', 'instance_key' => 'a',
        'is_published' => true, 'sort_order' => 0,
        'content' => [], 'style' => [],
    ]);
    $b = BuilderSection::query()->create([
        'builder_type' => 'testpage', 'builder_id' => $page->id,
        'type' => 'fake_gallery', 'instance_key' => 'b',
        'is_published' => true, 'sort_order' => 1,
        'content' => [], 'style' => [],
    ]);

    $this->postJson("/visual-builder/testpage/{$page->id}/save", [
        'ordered_ids' => [$b->id, $a->id],
    ])->assertOk();

    expect($a->fresh()->sort_order)->toBe(1)
        ->and($b->fresh()->sort_order)->toBe(0);
});

it('POST /{type}/{id}/save rejects empty payloads with 422', function (): void {
    $page = makeTestPage();

    $this->postJson("/visual-builder/testpage/{$page->id}/save", [])
        ->assertStatus(422);
});

it('POST duplicate endpoint clones an existing section', function (): void {
    $page = makeTestPage();

    $section = BuilderSection::query()->create([
        'builder_type' => 'testpage', 'builder_id' => $page->id,
        'type' => 'fake_gallery', 'instance_key' => 'a',
        'is_published' => true, 'sort_order' => 0,
        'content' => ['title' => 'Original'],
        'style' => [],
    ]);

    $this->post("/visual-builder/testpage/{$page->id}/sections/{$section->id}/duplicate")
        ->assertRedirect();

    expect(BuilderSection::query()->count())->toBe(2);
});

it('DELETE destroys a section and dispatches SectionDeleted', function (): void {
    Event::fake();
    $page = makeTestPage();

    $section = BuilderSection::query()->create([
        'builder_type' => 'testpage', 'builder_id' => $page->id,
        'type' => 'fake_gallery', 'instance_key' => 'a',
        'is_published' => true, 'sort_order' => 0,
        'content' => [], 'style' => [],
    ]);

    $this->deleteJson("/visual-builder/testpage/{$page->id}/sections/{$section->id}")
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(BuilderSection::query()->find($section->id))->toBeNull();
    Event::assertDispatched(SectionDeleted::class);
});

it('DELETE refuses a section whose morph owner does not match the URL target', function (): void {
    $page = makeTestPage();
    $other = makeTestPage();

    $section = BuilderSection::query()->create([
        'builder_type' => 'testpage', 'builder_id' => $page->id,
        'type' => 'fake_gallery', 'instance_key' => 'a',
        'is_published' => true, 'sort_order' => 0,
        'content' => [], 'style' => [],
    ]);

    $this->deleteJson("/visual-builder/testpage/{$other->id}/sections/{$section->id}")
        ->assertStatus(403);

    expect(BuilderSection::query()->find($section->id))->not->toBeNull();
});

it('returns 404 for unknown morph target types', function (): void {
    $this->postJson('/visual-builder/notatype/1/save', [
        'sections' => [],
        'ordered_ids' => [1],
    ])->assertStatus(404);
});

it('uploads an image and returns its path + url', function (): void {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('cover.png', 600, 400);

    $response = $this->post('/visual-builder/upload-image', [
        'file' => $file,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['success', 'path', 'url']);

    $json = $response->json();
    expect($json['success'])->toBeTrue()
        ->and($json['path'])->toBeString()
        ->and($json['url'])->toBeString();

    Storage::disk('public')->assertExists($json['path']);
});

it('rejects uploads with disallowed MIME types', function (): void {
    Storage::fake('public');

    $file = UploadedFile::fake()->create('evil.exe', 100, 'application/octet-stream');

    $this->postJson('/visual-builder/upload-image', [
        'file' => $file,
    ])->assertStatus(422);
});
