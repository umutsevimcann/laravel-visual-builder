<?php

declare(strict_types=1);

use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\AbstractAtomicWidget;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\ButtonWidget;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\ColumnsWidget;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\DividerWidget;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\HeadingWidget;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\IconBoxWidget;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\IconWidget;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\ImageWidget;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\ParagraphWidget;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\SpacerWidget;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\VideoWidget;

/*
 * Locks the category classifications shipped with v0.5.0 palette.
 * A category rename here is a potential visible regression (widget
 * moves between palette tabs); test catches that before release.
 *
 * AbstractAtomicWidget provides a 'basic' default so authors who
 * forget to override still get a sensible placement.
 */

it('AbstractAtomicWidget defaults category to basic', function (): void {
    $anon = new class extends AbstractAtomicWidget
    {
        public function key(): string
        {
            return 'anon';
        }

        public function label(): string
        {
            return 'Anonymous';
        }

        public function description(): string
        {
            return '';
        }

        public function icon(): string
        {
            return '';
        }

        public function fields(): array
        {
            return [];
        }

        public function defaultContent(): array
        {
            return [];
        }

        public function defaultStyle(): array
        {
            return [];
        }
    };

    expect($anon->category())->toBe('basic');
});

it('routes text + interaction primitives to basic', function (): void {
    expect((new HeadingWidget)->category())->toBe('basic')
        ->and((new ParagraphWidget)->category())->toBe('basic')
        ->and((new ButtonWidget)->category())->toBe('basic')
        ->and((new IconWidget)->category())->toBe('basic');
});

it('routes image + video to media', function (): void {
    expect((new ImageWidget)->category())->toBe('media')
        ->and((new VideoWidget)->category())->toBe('media');
});

it('routes spacer + divider + columns + icon_box to layout', function (): void {
    expect((new SpacerWidget)->category())->toBe('layout')
        ->and((new DividerWidget)->category())->toBe('layout')
        ->and((new ColumnsWidget)->category())->toBe('layout')
        ->and((new IconBoxWidget)->category())->toBe('layout');
});

it('every shipped widget returns a non-empty string category', function (): void {
    $widgets = [
        new HeadingWidget,
        new ParagraphWidget,
        new ButtonWidget,
        new SpacerWidget,
        new DividerWidget,
        new ImageWidget,
        new VideoWidget,
        new IconWidget,
        new IconBoxWidget,
        new ColumnsWidget,
    ];

    foreach ($widgets as $w) {
        expect($w->category())->toBeString()->not->toBe('');
    }
});
