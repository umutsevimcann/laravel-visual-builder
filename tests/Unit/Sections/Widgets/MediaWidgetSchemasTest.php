<?php

declare(strict_types=1);

use Umutsevimcann\VisualBuilder\Domain\Fields\FieldDefinition;
use Umutsevimcann\VisualBuilder\Domain\Fields\HtmlField;
use Umutsevimcann\VisualBuilder\Domain\Fields\ImageField;
use Umutsevimcann\VisualBuilder\Domain\Fields\SelectField;
use Umutsevimcann\VisualBuilder\Domain\Fields\TextField;
use Umutsevimcann\VisualBuilder\Domain\Fields\ToggleField;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\IconBoxWidget;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\IconWidget;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\ImageWidget;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\VideoWidget;

/*
 * Locks the schemas of the four media widgets shipped in v0.4.1.
 * Same guardrail as WidgetSchemasTest — regressions on a field rename
 * become a red test before they break inline editing in the iframe.
 */

describe('ImageWidget', function (): void {
    it('key + view partial follow the atomic-widget convention', function (): void {
        $w = new ImageWidget;
        expect($w->key())->toBe('image')
            ->and($w->viewPartial())->toBe('visual-builder::widgets.image');
    });

    it('exposes src (ImageField, required) + alt + url + fit', function (): void {
        $w = new ImageWidget;
        $byKey = collect($w->fields())->keyBy(fn (FieldDefinition $f) => $f->key);

        expect($byKey)->toHaveKeys(['src', 'alt', 'url', 'fit'])
            ->and($byKey['src'])->toBeInstanceOf(ImageField::class)
            ->and($byKey['src']->required)->toBeTrue()
            ->and($byKey['alt'])->toBeInstanceOf(TextField::class)
            ->and($byKey['alt']->translatable)->toBeTrue()
            ->and($byKey['url']->translatable)->toBeFalse()
            ->and($byKey['fit'])->toBeInstanceOf(SelectField::class);
    });

    it('defaults to cover object-fit', function (): void {
        expect((new ImageWidget)->defaultContent()['fit'])->toBe('cover');
    });
});

describe('VideoWidget', function (): void {
    it('exposes url + provider + aspect_ratio + toggles', function (): void {
        $w = new VideoWidget;
        $byKey = collect($w->fields())->keyBy(fn (FieldDefinition $f) => $f->key);

        expect($byKey)->toHaveKeys(['url', 'provider', 'aspect_ratio', 'autoplay', 'loop', 'controls'])
            ->and($byKey['url'])->toBeInstanceOf(TextField::class)
            ->and($byKey['url']->required)->toBeTrue()
            ->and($byKey['provider'])->toBeInstanceOf(SelectField::class)
            ->and($byKey['provider']->options)->toHaveKeys(['file', 'youtube', 'vimeo'])
            ->and($byKey['autoplay'])->toBeInstanceOf(ToggleField::class)
            ->and($byKey['loop'])->toBeInstanceOf(ToggleField::class)
            ->and($byKey['controls'])->toBeInstanceOf(ToggleField::class);
    });

    it('defaults to file provider, 16/9 ratio, controls on, autoplay off', function (): void {
        $dc = (new VideoWidget)->defaultContent();
        expect($dc['provider'])->toBe('file')
            ->and($dc['aspect_ratio'])->toBe('16/9')
            ->and($dc['controls'])->toBeTrue()
            ->and($dc['autoplay'])->toBeFalse()
            ->and($dc['loop'])->toBeFalse();
    });
});

describe('IconWidget', function (): void {
    it('exposes class + size + url', function (): void {
        $w = new IconWidget;
        $byKey = collect($w->fields())->keyBy(fn (FieldDefinition $f) => $f->key);

        expect($byKey)->toHaveKeys(['class', 'size', 'url'])
            ->and($byKey['class'])->toBeInstanceOf(TextField::class)
            ->and($byKey['class']->required)->toBeTrue()
            ->and($byKey['size']->options)->toHaveKeys(['sm', 'md', 'lg', 'xl', '2xl']);
    });

    it('defaults to fa-solid fa-star at md size', function (): void {
        $dc = (new IconWidget)->defaultContent();
        expect($dc['class'])->toBe('fa-solid fa-star')
            ->and($dc['size'])->toBe('md');
    });
});

describe('IconBoxWidget', function (): void {
    it('composes icon_class + title + body + layout', function (): void {
        $w = new IconBoxWidget;
        $byKey = collect($w->fields())->keyBy(fn (FieldDefinition $f) => $f->key);

        expect($byKey)->toHaveKeys(['icon_class', 'title', 'body', 'layout'])
            ->and($byKey['icon_class'])->toBeInstanceOf(TextField::class)
            ->and($byKey['icon_class']->required)->toBeTrue()
            ->and($byKey['title'])->toBeInstanceOf(TextField::class)
            ->and($byKey['title']->translatable)->toBeTrue()
            ->and($byKey['body'])->toBeInstanceOf(HtmlField::class)
            ->and($byKey['layout'])->toBeInstanceOf(SelectField::class)
            ->and($byKey['layout']->options)->toHaveKeys(['top', 'left', 'right']);
    });

    it('key uses snake_case underscore', function (): void {
        expect((new IconBoxWidget)->key())->toBe('icon_box')
            ->and((new IconBoxWidget)->viewPartial())->toBe('visual-builder::widgets.icon_box');
    });

    it('defaults to top layout with a shield icon', function (): void {
        $dc = (new IconBoxWidget)->defaultContent();
        expect($dc['layout'])->toBe('top')
            ->and($dc['icon_class'])->toBe('fa-solid fa-shield-halved');
    });
});
