<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Sections\Widgets;

use Umutsevimcann\VisualBuilder\Domain\Fields\SelectField;

/**
 * Spacer widget — vertical whitespace between other widgets.
 *
 * Renders as an empty block with a configured min-height. Does NOT draw
 * any visible border or background; its only job is to push adjacent
 * widgets apart. Preferred over margin hackery because the spacer
 * itself is selectable, reorderable and responsive — users can collapse
 * spacing on mobile by editing the height for that breakpoint without
 * touching surrounding widgets.
 *
 * Style field `padding_y` is NOT used here — spacer height comes from a
 * dedicated `height` content field so the intent is explicit in the
 * section tree (user sees a "120px spacer" in the navigator rather than
 * a zero-content block with padding).
 */
final class SpacerWidget extends AbstractAtomicWidget
{
    public function key(): string
    {
        return 'spacer';
    }

    public function category(): string
    {
        return 'layout';
    }

    public function label(): string
    {
        return 'Spacer';
    }

    public function description(): string
    {
        return 'A reserved block of vertical whitespace between widgets.';
    }

    public function icon(): string
    {
        return 'fa-solid fa-up-down';
    }

    public function fields(): array
    {
        return [
            new SelectField(
                key: 'height',
                label: 'Height',
                options: [
                    '10px' => 'Tiny (10px)',
                    '20px' => 'Small (20px)',
                    '40px' => 'Medium (40px)',
                    '80px' => 'Large (80px)',
                    '120px' => 'Extra large (120px)',
                    '200px' => 'Huge (200px)',
                ],
                help: 'Pick a preset height. Switch device to tablet/mobile to override per breakpoint.',
                defaultValue: '40px',
            ),
        ];
    }

    public function defaultContent(): array
    {
        return [
            'height' => '40px',
        ];
    }

    public function defaultStyle(): array
    {
        return [];
    }
}
