<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Sections\Widgets;

use Umutsevimcann\VisualBuilder\Domain\Fields\ImageField;
use Umutsevimcann\VisualBuilder\Domain\Fields\SelectField;
use Umutsevimcann\VisualBuilder\Domain\Fields\TextField;

/**
 * Image widget — a single image with optional alt text and link.
 *
 * Fields:
 *   - src   (image, required)  — storage path / asset / absolute URL.
 *   - alt   (translatable)     — alt text; omitted from markup when empty.
 *   - url   (text, non-translatable) — when set, the image is wrapped in
 *     an <a>; otherwise it renders as a plain <img>.
 *   - fit   (select)           — object-fit behaviour (cover / contain / fill).
 *
 * The admin upload flow reuses the existing media route; this widget
 * only declares the field schema. See ImageField for the storage
 * shape and render-time URL resolution rules.
 */
final class ImageWidget extends AbstractAtomicWidget
{
    public function key(): string
    {
        return 'image';
    }

    public function label(): string
    {
        return 'Image';
    }

    public function description(): string
    {
        return 'A single image with optional alt text and link target.';
    }

    public function icon(): string
    {
        return 'fa-solid fa-image';
    }

    public function fields(): array
    {
        return [
            new ImageField(
                key: 'src',
                label: 'Image',
                help: 'Upload or pick the image shown here.',
                required: true,
            ),
            new TextField(
                key: 'alt',
                label: 'Alt text',
                help: 'Screen-reader / SEO text. Describe what the image shows.',
                translatable: true,
            ),
            new TextField(
                key: 'url',
                label: 'Link target (optional)',
                help: 'If set, the image is wrapped in a clickable link.',
            ),
            new SelectField(
                key: 'fit',
                label: 'Object fit',
                options: [
                    'cover' => 'Cover — crop to fill container',
                    'contain' => 'Contain — fit inside, letterbox',
                    'fill' => 'Fill — stretch to container',
                    'none' => 'None — native size',
                ],
                help: 'Controls how the image sizes itself inside the widget.',
                defaultValue: 'cover',
            ),
        ];
    }

    public function defaultContent(): array
    {
        return [
            'src' => '',
            'alt' => [],
            'url' => '',
            'fit' => 'cover',
            '_visibility' => ['src' => true, 'alt' => true],
        ];
    }

    public function defaultStyle(): array
    {
        return [
            'alignment' => 'center',
        ];
    }
}
