<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Examples;

use Umutsevimcann\VisualBuilder\Domain\Fields\HtmlField;
use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeInterface;

/**
 * Example — generic HTML container.
 *
 * A single rich-text body. Minimum viable section type — ideal for landing
 * pages, announcements, and one-off content blocks that don't deserve a
 * dedicated schema.
 *
 * Copy this file into your app, rename the class and key, and register:
 *
 *     app(SectionTypeRegistry::class)->register(new BlankContainerSectionType());
 *
 * This file lives under src/Examples/ so host apps that run `composer
 * require` don't pick it up automatically. Register it in their
 * AppServiceProvider to opt in.
 */
final class BlankContainerSectionType implements SectionTypeInterface
{
    public function key(): string
    {
        return 'blank_container';
    }

    public function label(): string
    {
        return 'Blank Container';
    }

    public function description(): string
    {
        return 'A single rich-text body with no additional structure — use for free-form HTML content.';
    }

    public function icon(): string
    {
        return 'fa-solid fa-square';
    }

    public function previewImage(): null|string
    {
        return null;
    }

    public function fields(): array
    {
        return [
            new HtmlField(
                key: 'body',
                label: 'Body',
                help: 'The container\'s HTML content. Supports rich text and inline formatting.',
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
            'padding_y' => '40px',
            'alignment' => 'left',
        ];
    }

    public function viewPartial(): string
    {
        return 'visual-builder::examples.blank-container';
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
