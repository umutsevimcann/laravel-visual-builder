<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;
use Umutsevimcann\VisualBuilder\VisualBuilder;

/*
 * Covers the @vbSectionStyles Blade directive and the
 * VisualBuilder::sectionStylesTag() helper it delegates to. Focuses on
 * the HTML-shape contract the production IFEX render pipeline depends
 * on, not on the underlying CSS content (BreakpointStyleResolverTest
 * covers that).
 */

it('returns an empty HtmlString when the section has no style values', function (): void {
    $section = ['id' => 1, 'style' => []];

    expect((string) VisualBuilder::sectionStylesTag($section))->toBe('');
});

it('returns an empty HtmlString when style is missing entirely from the array shape', function (): void {
    expect((string) VisualBuilder::sectionStylesTag(['id' => 42]))->toBe('');
});

it('wraps CSS in a <style> tag tagged with the section id', function (): void {
    $html = (string) VisualBuilder::sectionStylesTag([
        'id' => 7,
        'style' => ['bg_color' => '#ff0000'],
    ]);

    expect($html)
        ->toStartWith('<style data-vb-section-styles="7">')
        ->toEndWith('</style>')
        ->toContain('[data-vb-section-id="7"]:not([data-vb-editable])')
        ->toContain('background-color: #ff0000');
});

it('emits @media queries for per-breakpoint overrides', function (): void {
    $html = (string) VisualBuilder::sectionStylesTag([
        'id' => 3,
        'style' => [
            'padding_y' => ['desktop' => '80px', 'tablet' => '60px', 'mobile' => '40px'],
        ],
    ]);

    expect($html)
        ->toContain('@media (max-width: 1023px)')
        ->toContain('@media (max-width: 767px)')
        ->toContain('padding-top: 60px')
        ->toContain('padding-top: 40px');
});

it('accepts a BuilderSection model instance directly', function (): void {
    $section = new BuilderSection([
        'id' => 9,
        'style' => ['bg_color' => '#00ff00'],
    ]);
    $section->id = 9;

    $html = (string) VisualBuilder::sectionStylesTag($section);

    expect($html)
        ->toContain('data-vb-section-styles="9"')
        ->toContain('background-color: #00ff00');
});

it('tolerates a non-array style attribute on the model without throwing', function (): void {
    $section = new BuilderSection;
    $section->id = 11;
    // Simulate a malformed DB row — style ended up as a string somehow.
    $section->setRawAttributes(['id' => 11, 'style' => 'not-json'], sync: true);

    expect((string) VisualBuilder::sectionStylesTag($section))->toBe('');
});

it('is reachable via the @vbSectionStyles Blade directive', function (): void {
    $rendered = Blade::render(
        '@vbSectionStyles($section)',
        ['section' => ['id' => 5, 'style' => ['bg_color' => '#abcdef']]],
    );

    expect($rendered)
        ->toContain('<style data-vb-section-styles="5">')
        ->toContain('background-color: #abcdef');
});
