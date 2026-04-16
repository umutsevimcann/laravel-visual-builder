<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Examples;

use Umutsevimcann\VisualBuilder\Domain\Fields\HtmlField;
use Umutsevimcann\VisualBuilder\Domain\Fields\ImageField;
use Umutsevimcann\VisualBuilder\Domain\Fields\LinkField;
use Umutsevimcann\VisualBuilder\Domain\Fields\SelectField;
use Umutsevimcann\VisualBuilder\Domain\Fields\TextField;
use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeInterface;

/**
 * Example — classic image + text two-column layout.
 *
 * Demonstrates the most common marketing page pattern: supporting imagery
 * on one side, heading + body + CTA on the other. The `layout` field lets
 * editors flip which side the image appears on per instance.
 *
 * Copy this file into your app as a starting template for your own
 * two-column variations. Key, label, and view partial should change.
 */
final class TwoColumnSectionType implements SectionTypeInterface
{
    public function key(): string
    {
        return 'two_column';
    }

    public function label(): string
    {
        return 'Two-Column';
    }

    public function description(): string
    {
        return 'Image on one side, heading + body + button on the other. Works for intro sections, feature highlights, and product blocks.';
    }

    public function icon(): string
    {
        return 'fa-solid fa-columns';
    }

    public function previewImage(): null|string
    {
        return null;
    }

    public function fields(): array
    {
        return [
            new TextField(
                key: 'heading',
                label: 'Heading',
                help: 'Main headline — usually the largest text on the section.',
                required: true,
                translatable: true,
                maxLength: 120,
            ),
            new HtmlField(
                key: 'body',
                label: 'Body',
                help: 'Supporting copy below the heading.',
            ),
            new ImageField(
                key: 'image',
                label: 'Image',
                help: 'Supporting image. Rendered on the opposite side from the text.',
            ),
            new LinkField(
                key: 'cta',
                label: 'Call-to-action Button',
                help: 'Optional button below the body. Leave URL blank to hide.',
            ),
            new SelectField(
                key: 'layout',
                label: 'Layout',
                options: [
                    'image_left' => 'Image on the left',
                    'image_right' => 'Image on the right',
                ],
                defaultValue: 'image_left',
            ),
        ];
    }

    public function defaultContent(): array
    {
        return [
            'heading' => [],
            'body' => [],
            'image' => null,
            'cta_url' => null,
            'cta_label' => [],
            'layout' => 'image_left',
        ];
    }

    public function defaultStyle(): array
    {
        return [
            'padding_y' => '80px',
            'alignment' => 'left',
        ];
    }

    public function viewPartial(): string
    {
        return 'visual-builder::examples.two-column';
    }

    public function allowsMultipleInstances(): bool
    {
        return true;
    }

    public function isDeletable(): bool
    {
        return true;
    }
}
