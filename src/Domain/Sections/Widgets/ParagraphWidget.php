<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Sections\Widgets;

use Umutsevimcann\VisualBuilder\Domain\Fields\HtmlField;

/**
 * Paragraph widget — rich-text block with inline formatting support.
 *
 * Fields:
 *   - body (translatable HTML, required) — sanitized through the
 *     configured SanitizerInterface (HTMLPurifier by default) before
 *     persistence. The admin UI inline-edits the body via the iframe
 *     contenteditable path.
 *
 * Rendering is the simplest possible: one `<div>` per widget with the
 * sanitized HTML inside. Text styling (font, alignment, color) flows
 * through the section's standard style block so the breakpoint CSS
 * cascade controls typography per device.
 *
 * v0.4.3 swaps the plain HtmlField for a TipTap-backed editor inside
 * the admin; storage / render stay the same so this widget does not
 * need changes at that boundary.
 */
final class ParagraphWidget extends AbstractAtomicWidget
{
    public function key(): string
    {
        return 'paragraph';
    }

    public function label(): string
    {
        return 'Paragraph';
    }

    public function description(): string
    {
        return 'A block of rich text. Inline edits in place; bold, italic and links supported.';
    }

    public function icon(): string
    {
        return 'fa-solid fa-paragraph';
    }

    public function fields(): array
    {
        return [
            new HtmlField(
                key: 'body',
                label: 'Body',
                help: 'The paragraph content. Keep sentences focused — one idea per widget.',
                required: true,
            ),
        ];
    }

    public function defaultContent(): array
    {
        return [
            'body' => [],
            '_visibility' => ['body' => true],
        ];
    }

    public function defaultStyle(): array
    {
        return [
            'alignment' => 'left',
            'padding_top' => '12',
            'padding_bottom' => '12',
        ];
    }
}
