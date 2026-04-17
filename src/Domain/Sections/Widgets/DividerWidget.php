<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Sections\Widgets;

use Umutsevimcann\VisualBuilder\Domain\Fields\SelectField;

/**
 * Divider widget — a horizontal rule between content blocks.
 *
 * Renders as a single `<hr>` with configurable line style, thickness
 * and colour. Prefer over typing `<hr>` into a paragraph widget because
 * the divider is a first-class selectable block: the host can restyle
 * every divider globally via a single CSS rule, and the section tree
 * lists it as a separate step.
 */
final class DividerWidget extends AbstractAtomicWidget
{
    public function key(): string
    {
        return 'divider';
    }

    public function category(): string
    {
        return 'layout';
    }

    public function label(): string
    {
        return 'Divider';
    }

    public function description(): string
    {
        return 'A horizontal rule that separates content blocks.';
    }

    public function icon(): string
    {
        return 'fa-solid fa-minus';
    }

    public function fields(): array
    {
        return [
            new SelectField(
                key: 'line_style',
                label: 'Line style',
                options: [
                    'solid' => 'Solid',
                    'dashed' => 'Dashed',
                    'dotted' => 'Dotted',
                    'double' => 'Double',
                ],
                defaultValue: 'solid',
            ),
            new SelectField(
                key: 'thickness',
                label: 'Thickness',
                options: [
                    '1px' => 'Hairline (1px)',
                    '2px' => 'Thin (2px)',
                    '4px' => 'Medium (4px)',
                    '8px' => 'Thick (8px)',
                ],
                defaultValue: '1px',
            ),
            new SelectField(
                key: 'width',
                label: 'Width',
                options: [
                    '100%' => 'Full width',
                    '75%' => 'Three quarters',
                    '50%' => 'Half',
                    '25%' => 'Quarter',
                ],
                defaultValue: '100%',
            ),
        ];
    }

    public function defaultContent(): array
    {
        return [
            'line_style' => 'solid',
            'thickness' => '1px',
            'width' => '100%',
        ];
    }

    public function defaultStyle(): array
    {
        return [
            'alignment' => 'center',
            'padding_top' => '16',
            'padding_bottom' => '16',
        ];
    }
}
