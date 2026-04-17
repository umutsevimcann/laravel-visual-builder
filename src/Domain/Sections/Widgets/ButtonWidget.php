<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Sections\Widgets;

use Umutsevimcann\VisualBuilder\Domain\Fields\SelectField;
use Umutsevimcann\VisualBuilder\Domain\Fields\TextField;
use Umutsevimcann\VisualBuilder\Domain\Fields\ToggleField;

/**
 * Button widget — a single call-to-action link styled as a button.
 *
 * Fields:
 *   - cta     (link, required)     — label (translatable) + URL + target.
 *   - variant (select)             — visual variant key; the rendering
 *     partial maps it to whatever the host app's button CSS expects
 *     (bootstrap `btn-primary`, tailwind `bg-blue-600`, etc.). The
 *     package ships a neutral default stylesheet the host can replace.
 *
 * Style fields on the section apply to the BUTTON itself (bg_color,
 * text_color, padding_*, alignment), not to the wrapper — so that
 * responsive styling still works per device without every template
 * needing to thread the style array into a nested element.
 */
final class ButtonWidget extends AbstractAtomicWidget
{
    public function key(): string
    {
        return 'button';
    }

    public function label(): string
    {
        return 'Button';
    }

    public function description(): string
    {
        return 'A single call-to-action link rendered as a button.';
    }

    public function icon(): string
    {
        return 'fa-solid fa-circle-play';
    }

    public function fields(): array
    {
        return [
            new TextField(
                key: 'label',
                label: 'Button text',
                help: 'The visible label on the button.',
                required: true,
                translatable: true,
            ),
            new TextField(
                key: 'url',
                label: 'URL',
                help: 'Relative (/en/contact) or absolute (https://…) URL the button links to.',
                required: true,
            ),
            new ToggleField(
                key: 'new_tab',
                label: 'Open in a new tab',
            ),
            new SelectField(
                key: 'variant',
                label: 'Variant',
                options: [
                    'primary' => 'Primary (brand colour)',
                    'secondary' => 'Secondary (neutral)',
                    'outline' => 'Outline (transparent)',
                    'link' => 'Text link (no button chrome)',
                ],
                help: 'Controls which preset visual style the button picks up.',
                defaultValue: 'primary',
            ),
            new SelectField(
                key: 'size',
                label: 'Size',
                options: [
                    'sm' => 'Small',
                    'md' => 'Medium (default)',
                    'lg' => 'Large',
                ],
                defaultValue: 'md',
            ),
        ];
    }

    public function defaultContent(): array
    {
        return [
            'label' => [],
            'url' => '#',
            'new_tab' => false,
            'variant' => 'primary',
            'size' => 'md',
            '_visibility' => ['label' => true, 'url' => true],
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
