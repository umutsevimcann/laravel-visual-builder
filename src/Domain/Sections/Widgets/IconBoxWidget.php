<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Sections\Widgets;

use Umutsevimcann\VisualBuilder\Domain\Fields\HtmlField;
use Umutsevimcann\VisualBuilder\Domain\Fields\SelectField;
use Umutsevimcann\VisualBuilder\Domain\Fields\TextField;

/**
 * IconBox widget — icon + heading + body, commonly used for "features"
 * sections. Elementor calls it an "Icon Box"; our rendering emits a
 * single block:
 *
 *     <div class="vb-widget-icon-box">
 *         <i class="{icon_class}"></i>
 *         <h3>{title}</h3>
 *         <div>{body}</div>
 *     </div>
 *
 * Fields:
 *   - icon_class (text, required)  — the icon class string (library-agnostic).
 *   - title      (translatable)    — the headline.
 *   - body       (translatable HTML) — the paragraph under the title.
 *   - layout     (select)          — icon placement: 'top' | 'left' | 'right'.
 *
 * Composite widget: encapsulates a common pattern so users don't need
 * to chain Icon + Heading + Paragraph inside a Column every time.
 */
final class IconBoxWidget extends AbstractAtomicWidget
{
    public function key(): string
    {
        return 'icon_box';
    }

    public function category(): string
    {
        return 'layout';
    }

    public function label(): string
    {
        return 'Icon Box';
    }

    public function description(): string
    {
        return 'Icon + heading + body — the standard "features" block.';
    }

    public function icon(): string
    {
        return 'fa-solid fa-box';
    }

    public function fields(): array
    {
        return [
            new TextField(
                key: 'icon_class',
                label: 'Icon class',
                help: 'Full class string (e.g. "fa-solid fa-shield-halved").',
                required: true,
            ),
            new TextField(
                key: 'title',
                label: 'Title',
                help: 'The headline shown next to / above the icon.',
                translatable: true,
            ),
            new HtmlField(
                key: 'body',
                label: 'Body',
                help: 'Short paragraph under the title.',
            ),
            new SelectField(
                key: 'layout',
                label: 'Icon placement',
                options: [
                    'top' => 'Top — icon above title',
                    'left' => 'Left — icon beside title',
                    'right' => 'Right — icon beside title (reversed)',
                ],
                defaultValue: 'top',
            ),
        ];
    }

    public function defaultContent(): array
    {
        return [
            'icon_class' => 'fa-solid fa-shield-halved',
            'title' => [],
            'body' => [],
            'layout' => 'top',
            '_visibility' => ['icon_class' => true, 'title' => true, 'body' => true],
        ];
    }

    public function defaultStyle(): array
    {
        return [
            'alignment' => 'center',
            'padding_top' => '24',
            'padding_bottom' => '24',
        ];
    }
}
