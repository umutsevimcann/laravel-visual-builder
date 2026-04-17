<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Sections\Widgets;

use Umutsevimcann\VisualBuilder\Domain\Fields\SelectField;
use Umutsevimcann\VisualBuilder\Domain\Fields\TextField;

/**
 * Icon widget — a single decorative icon rendered via a CSS class string.
 *
 * The package is icon-library-agnostic: the stored `class` value is
 * passed through as-is to the render partial, which emits
 * `<i class="{class}"></i>`. Host apps choose their library (Font
 * Awesome, Bootstrap Icons, Lucide) and include the stylesheet in
 * their layout. A size selector controls the CSS font-size so the
 * same class can render at different visual sizes without editing
 * the library's CSS.
 */
final class IconWidget extends AbstractAtomicWidget
{
    public function key(): string
    {
        return 'icon';
    }

    public function label(): string
    {
        return 'Icon';
    }

    public function description(): string
    {
        return 'A single icon rendered via a CSS class (Font Awesome, Bootstrap Icons, etc.).';
    }

    public function icon(): string
    {
        return 'fa-solid fa-icons';
    }

    public function fields(): array
    {
        return [
            new TextField(
                key: 'class',
                label: 'Icon class',
                help: 'Full class string — e.g. "fa-solid fa-star" for Font Awesome, "bi bi-star" for Bootstrap Icons.',
                required: true,
            ),
            new SelectField(
                key: 'size',
                label: 'Size',
                options: [
                    'sm' => 'Small (16px)',
                    'md' => 'Medium (24px, default)',
                    'lg' => 'Large (32px)',
                    'xl' => 'Extra large (48px)',
                    '2xl' => 'Huge (64px)',
                ],
                defaultValue: 'md',
            ),
            new TextField(
                key: 'url',
                label: 'Link target (optional)',
                help: 'If set, the icon is wrapped in a clickable link.',
            ),
        ];
    }

    public function defaultContent(): array
    {
        return [
            'class' => 'fa-solid fa-star',
            'size' => 'md',
            'url' => '',
        ];
    }

    public function defaultStyle(): array
    {
        return [
            'alignment' => 'center',
        ];
    }
}
