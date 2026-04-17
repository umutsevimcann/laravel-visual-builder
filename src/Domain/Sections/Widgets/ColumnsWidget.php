<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Sections\Widgets;

use Umutsevimcann\VisualBuilder\Domain\Fields\SelectField;

/**
 * Columns widget — a nested container that renders its children across
 * 1 to 6 CSS-grid columns with a configurable gap.
 *
 * Container semantics:
 *   Children are BuilderSection rows whose `parent_id` references this
 *   section's id. Their `column_index` (0..count-1) tells the render
 *   partial which slot they belong to, letting two siblings with
 *   different column_indexes render side-by-side on desktop but
 *   stacked on mobile via the standard CSS grid behaviour.
 *
 * The container itself has no own content — only layout fields. A user
 * builds the actual page inside each column by nesting further widgets
 * (Heading, Paragraph, Image …). v0.4.2 ships the render + data model;
 * a forthcoming release adds the admin-side "+" inserter that creates
 * a child with the right parent_id / column_index pair so the content
 * editor never has to think about the underlying tree structure.
 *
 * Breakpoint behaviour on stack:
 *   CSS grid with `grid-template-columns: repeat(auto-fit, minmax(Xpx, 1fr))`
 *   causes columns to wrap when the viewport can't fit them. The `stack_on`
 *   select picks the minimum width threshold that triggers the wrap, so
 *   the same Columns widget renders four-across on desktop and one-per-row
 *   on mobile without a separate responsive JS path.
 */
final class ColumnsWidget extends AbstractAtomicWidget
{
    public function key(): string
    {
        return 'columns';
    }

    public function label(): string
    {
        return 'Columns';
    }

    public function description(): string
    {
        return 'A horizontal container — place other widgets inside its column slots.';
    }

    public function icon(): string
    {
        return 'fa-solid fa-table-columns';
    }

    public function fields(): array
    {
        return [
            new SelectField(
                key: 'count',
                label: 'Number of columns',
                // Word-keyed to keep PHP from auto-casting purely-numeric
                // string keys to int (PHPStan sees the int-keyed shape
                // and the SelectField options type-hint expects strings).
                // The column_blade partial maps these back to integers.
                options: [
                    'one' => '1 — full width',
                    'two' => '2 columns (default)',
                    'three' => '3 columns',
                    'four' => '4 columns',
                    'five' => '5 columns',
                    'six' => '6 columns',
                ],
                help: 'How many slots the widget exposes on desktop.',
                defaultValue: 'two',
            ),
            new SelectField(
                key: 'gap',
                label: 'Gap between columns',
                options: [
                    'none' => 'None',
                    'tight' => 'Tight (8px)',
                    'small' => 'Small (16px)',
                    'medium' => 'Medium (24px, default)',
                    'large' => 'Large (48px)',
                    'wide' => 'Wide (80px)',
                ],
                defaultValue: 'medium',
            ),
            new SelectField(
                key: 'stack_on',
                label: 'Stack on',
                options: [
                    'mobile' => 'Mobile only (<768px)',
                    'tablet' => 'Tablet and below (<1024px)',
                    'never' => 'Never — always side-by-side',
                ],
                help: 'At which breakpoint the columns collapse into a single stacked column.',
                defaultValue: 'mobile',
            ),
        ];
    }

    public function defaultContent(): array
    {
        return [
            'count' => 'two',
            'gap' => 'medium',
            'stack_on' => 'mobile',
        ];
    }

    public function defaultStyle(): array
    {
        return [
            'alignment' => 'left',
            'padding_top' => '24',
            'padding_bottom' => '24',
        ];
    }
}
