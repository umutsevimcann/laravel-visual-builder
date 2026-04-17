<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Sections\Widgets;

use Umutsevimcann\VisualBuilder\Domain\Fields\SelectField;
use Umutsevimcann\VisualBuilder\Domain\Fields\TextField;

/**
 * Heading widget — a single translatable line of text rendered as one of
 * h1–h6 (or a plain div when the editor needs heading styling without the
 * semantic tag).
 *
 * Fields:
 *   - text  (translatable, required) — the visible string.
 *   - level (select)                  — h1..h6 or div. Drives the HTML tag.
 *
 * Rendering lives in `visual-builder::widgets.heading` — the partial
 * reads `$section->contentField('text')` and the resolved level, then
 * emits `<h{level}>{text}</h{level}>` with the standard
 * `data-vb-editable` attributes for inline editing.
 */
final class HeadingWidget extends AbstractAtomicWidget
{
    public function key(): string
    {
        return 'heading';
    }

    public function label(): string
    {
        return 'Heading';
    }

    public function description(): string
    {
        return 'A single line of large text. Choose any level from h1 to h6 or a plain div.';
    }

    public function icon(): string
    {
        return 'fa-solid fa-heading';
    }

    public function fields(): array
    {
        return [
            new TextField(
                key: 'text',
                label: 'Text',
                help: 'The headline shown on the page. Keep it short.',
                required: true,
                translatable: true,
            ),
            new SelectField(
                key: 'level',
                label: 'Level',
                options: [
                    'h1' => 'H1 — page title',
                    'h2' => 'H2 — section title',
                    'h3' => 'H3 — sub-section',
                    'h4' => 'H4',
                    'h5' => 'H5',
                    'h6' => 'H6',
                    'div' => 'Plain div (no heading semantics)',
                ],
                help: 'Pick the semantic tag. Only one h1 per page is recommended for SEO.',
                defaultValue: 'h2',
            ),
        ];
    }

    public function defaultContent(): array
    {
        return [
            'text' => [],
            'level' => 'h2',
            '_visibility' => ['text' => true],
        ];
    }

    public function defaultStyle(): array
    {
        return [
            'alignment' => 'left',
            'padding_top' => '16',
            'padding_bottom' => '16',
        ];
    }
}
