<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Tests\Stubs\Sections;

use Umutsevimcann\VisualBuilder\Domain\Fields\TextField;
use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeInterface;

/**
 * Multi-instance variant of {@see FakeSectionType}. Exercises the code
 * paths where `allowsMultipleInstances() === true`.
 */
final class FakeMultiSectionType implements SectionTypeInterface
{
    public function key(): string
    {
        return 'fake_gallery';
    }

    public function label(): string
    {
        return 'Fake Gallery';
    }

    public function description(): string
    {
        return 'Multi-instance test section.';
    }

    public function icon(): string
    {
        return 'fa-solid fa-images';
    }

    public function previewImage(): null|string
    {
        return null;
    }

    public function fields(): array
    {
        return [
            new TextField(
                key: 'title',
                label: 'Title',
                required: false,
                translatable: false,
                maxLength: 80,
            ),
        ];
    }

    public function defaultContent(): array
    {
        return ['title' => 'Gallery'];
    }

    public function defaultStyle(): array
    {
        return [];
    }

    public function viewPartial(): string
    {
        return 'stubs.fake-gallery';
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
