<?php

declare(strict_types=1);

use Umutsevimcann\VisualBuilder\Domain\Fields\FieldDefinition;
use Umutsevimcann\VisualBuilder\Domain\Fields\HtmlField;
use Umutsevimcann\VisualBuilder\Domain\Fields\SelectField;
use Umutsevimcann\VisualBuilder\Domain\Fields\TextField;
use Umutsevimcann\VisualBuilder\Domain\Fields\ToggleField;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\ButtonWidget;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\DividerWidget;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\HeadingWidget;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\ParagraphWidget;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\SpacerWidget;

/*
 * Locks the public schema of every atomic widget: keys, field names,
 * default content shapes, view partial paths. Regression coverage so a
 * widget rename (e.g. changing `text` → `title`) shows up as a red test
 * before it desyncs the iframe's inline-edit handlers.
 */

describe('HeadingWidget', function (): void {
    it('has stable key, label and view partial', function (): void {
        $w = new HeadingWidget;
        expect($w->key())->toBe('heading')
            ->and($w->label())->toBe('Heading')
            ->and($w->viewPartial())->toBe('visual-builder::widgets.heading');
    });

    it('exposes text + level fields with correct types', function (): void {
        $w = new HeadingWidget;
        $fields = collect($w->fields());
        $byKey = $fields->keyBy(fn (FieldDefinition $f) => $f->key);

        expect($byKey)->toHaveKeys(['text', 'level'])
            ->and($byKey['text'])->toBeInstanceOf(TextField::class)
            ->and($byKey['text']->translatable)->toBeTrue()
            ->and($byKey['text']->required)->toBeTrue()
            ->and($byKey['level'])->toBeInstanceOf(SelectField::class);
    });

    it('defaults to h2 level in both content and field default', function (): void {
        $w = new HeadingWidget;
        $level = collect($w->fields())->firstWhere('key', 'level');

        expect($w->defaultContent()['level'])->toBe('h2')
            ->and($level->default())->toBe('h2');
    });

    it('accepts multiple instances and is deletable', function (): void {
        $w = new HeadingWidget;
        expect($w->allowsMultipleInstances())->toBeTrue()
            ->and($w->isDeletable())->toBeTrue()
            ->and($w->previewImage())->toBeNull();
    });
});

describe('ParagraphWidget', function (): void {
    it('exposes a single required translatable HtmlField', function (): void {
        $w = new ParagraphWidget;
        $fields = $w->fields();
        expect($fields)->toHaveCount(1)
            ->and($fields[0])->toBeInstanceOf(HtmlField::class)
            ->and($fields[0]->key)->toBe('body')
            ->and($fields[0]->required)->toBeTrue();
    });

    it('key routes to the paragraph Blade partial', function (): void {
        expect((new ParagraphWidget)->viewPartial())->toBe('visual-builder::widgets.paragraph');
    });
});

describe('ButtonWidget', function (): void {
    it('exposes label + url + new_tab + variant + size', function (): void {
        $w = new ButtonWidget;
        $byKey = collect($w->fields())->keyBy(fn (FieldDefinition $f) => $f->key);

        expect($byKey)->toHaveKeys(['label', 'url', 'new_tab', 'variant', 'size'])
            ->and($byKey['label'])->toBeInstanceOf(TextField::class)
            ->and($byKey['label']->translatable)->toBeTrue()
            ->and($byKey['url'])->toBeInstanceOf(TextField::class)
            ->and($byKey['url']->translatable)->toBeFalse()
            ->and($byKey['new_tab'])->toBeInstanceOf(ToggleField::class)
            ->and($byKey['variant'])->toBeInstanceOf(SelectField::class);
    });

    it('defaults to primary variant / md size', function (): void {
        $dc = (new ButtonWidget)->defaultContent();
        expect($dc['variant'])->toBe('primary')
            ->and($dc['size'])->toBe('md')
            ->and($dc['new_tab'])->toBeFalse();
    });
});

describe('SpacerWidget', function (): void {
    it('stores height as one of the preset select options', function (): void {
        $w = new SpacerWidget;
        $height = collect($w->fields())->firstWhere('key', 'height');
        expect($height)->toBeInstanceOf(SelectField::class)
            ->and($height->options)->toHaveKeys(['10px', '20px', '40px', '80px', '120px', '200px']);
    });

    it('defaults to 40px height and carries no style defaults', function (): void {
        $w = new SpacerWidget;
        expect($w->defaultContent()['height'])->toBe('40px')
            ->and($w->defaultStyle())->toBe([]);
    });
});

describe('DividerWidget', function (): void {
    it('exposes line_style, thickness and width', function (): void {
        $w = new DividerWidget;
        $byKey = collect($w->fields())->keyBy(fn (FieldDefinition $f) => $f->key);

        expect($byKey)->toHaveKeys(['line_style', 'thickness', 'width'])
            ->and($byKey['line_style'])->toBeInstanceOf(SelectField::class)
            ->and($byKey['thickness']->options)->toHaveKeys(['1px', '2px', '4px', '8px']);
    });

    it('defaults to solid 1px full-width', function (): void {
        $dc = (new DividerWidget)->defaultContent();
        expect($dc['line_style'])->toBe('solid')
            ->and($dc['thickness'])->toBe('1px')
            ->and($dc['width'])->toBe('100%');
    });
});
