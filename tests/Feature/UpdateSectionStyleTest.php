<?php

declare(strict_types=1);

use Umutsevimcann\VisualBuilder\Domain\Actions\UpdateSectionStyle;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;
use Umutsevimcann\VisualBuilder\Tests\Stubs\Models\TestPage;

/*
 * Covers the end-to-end contract of UpdateSectionStyle — the action
 * the bulk save endpoint delegates to for every section's style patch.
 * Regression coverage for v0.3.2: object-shape (per-breakpoint) values
 * were silently dropped by the old `is_scalar()` guard, so responsive
 * edits appeared to save but never reached the DB.
 */

function makeSection(): BuilderSection
{
    $page = TestPage::create(['title' => 'Style sanitization page']);

    return BuilderSection::create([
        'builder_type' => $page->getMorphClass(),
        'builder_id' => $page->id,
        'type' => 'generic',
        'instance_key' => '__default__',
        'content' => [],
        'style' => [],
        'is_published' => true,
        'sort_order' => 0,
    ]);
}

it('accepts flat scalar values (legacy shape) verbatim', function (): void {
    $section = makeSection();
    $action = app(UpdateSectionStyle::class);

    $updated = $action->execute($section, [
        'bg_color' => '#ff0000',
        'padding_y' => '80px',
        'alignment' => 'center',
    ]);

    expect($updated->style)->toBe([
        'bg_color' => '#ff0000',
        'padding_y' => '80px',
        'alignment' => 'center',
    ]);
});

it('accepts per-breakpoint object values and persists them through the repository', function (): void {
    $section = makeSection();
    $action = app(UpdateSectionStyle::class);

    $updated = $action->execute($section, [
        'padding_y' => ['desktop' => '80px', 'tablet' => '60px', 'mobile' => '40px'],
    ]);

    expect($updated->style)->toBe([
        'padding_y' => ['desktop' => '80px', 'tablet' => '60px', 'mobile' => '40px'],
    ]);
});

it('accepts a partially-filled breakpoint object', function (): void {
    $section = makeSection();
    $action = app(UpdateSectionStyle::class);

    // Only desktop + mobile — tablet intentionally omitted.
    $updated = $action->execute($section, [
        'padding_y' => ['desktop' => '80px', 'mobile' => '40px'],
    ]);

    expect($updated->style['padding_y'])->toBe([
        'desktop' => '80px',
        'mobile' => '40px',
    ]);
});

it('keeps a scalar key alongside an object key in the same payload', function (): void {
    $section = makeSection();
    $action = app(UpdateSectionStyle::class);

    $updated = $action->execute($section, [
        'bg_color' => '#ff0000',
        'padding_y' => ['desktop' => '80px', 'tablet' => '60px'],
    ]);

    expect($updated->style)->toBe([
        'bg_color' => '#ff0000',
        'padding_y' => ['desktop' => '80px', 'tablet' => '60px'],
    ]);
});

it('drops unknown breakpoint keys from an object value', function (): void {
    $section = makeSection();
    $action = app(UpdateSectionStyle::class);

    $updated = $action->execute($section, [
        'padding_y' => [
            'desktop' => '80px',
            'watch' => '20px',    // not a known breakpoint — must be dropped
            'tablet' => '60px',
        ],
    ]);

    expect($updated->style['padding_y'])->toBe([
        'desktop' => '80px',
        'tablet' => '60px',
    ]);
});

it('drops empty strings and nulls inside an object value', function (): void {
    $section = makeSection();
    $action = app(UpdateSectionStyle::class);

    $updated = $action->execute($section, [
        'padding_y' => ['desktop' => '80px', 'tablet' => '', 'mobile' => null],
    ]);

    expect($updated->style['padding_y'])->toBe(['desktop' => '80px']);
});

it('omits a key when its object becomes empty after sanitization', function (): void {
    $section = makeSection();
    $action = app(UpdateSectionStyle::class);

    $updated = $action->execute($section, [
        'padding_y' => ['tablet' => '', 'mobile' => null],
    ]);

    // padding_y gone — empty after filter. Whole style is null because
    // no other keys remain.
    expect($updated->style)->toBeNull();
});

it('rejects unknown top-level style keys', function (): void {
    $section = makeSection();
    $action = app(UpdateSectionStyle::class);

    $updated = $action->execute($section, [
        'bg_color' => '#ff0000',
        'evil_key' => '<script>alert(1)</script>',
    ]);

    expect($updated->style)->toBe(['bg_color' => '#ff0000']);
});

it('sets style to null when every key is dropped', function (): void {
    $section = makeSection();
    $section->style = ['bg_color' => '#ff0000']; // previous value in DB
    $section->save();
    $action = app(UpdateSectionStyle::class);

    $updated = $action->execute($section, [
        'bg_color' => '',
        'padding_y' => null,
    ]);

    expect($updated->style)->toBeNull();
});
