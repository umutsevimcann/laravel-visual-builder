<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Tests\Stubs\Sections;

use Umutsevimcann\VisualBuilder\Domain\Fields\TextField;
use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeInterface;

/**
 * Minimal SectionType used to exercise the controller endpoints.
 *
 * Singleton-by-default with a single translatable TextField. A second
 * `FakeMultiSectionType` below flips allowsMultipleInstances() so tests
 * can verify both code paths.
 */
final class FakeSectionType implements SectionTypeInterface
{
    public function key(): string
    {
        return 'fake_hero';
    }

    public function label(): string
    {
        return 'Fake Hero';
    }

    public function description(): string
    {
        return 'Test-only section.';
    }

    public function icon(): string
    {
        return 'fa-solid fa-flask';
    }

    public function previewImage(): null|string
    {
        return null;
    }

    public function fields(): array
    {
        return [
            new TextField(
                key: 'headline',
                label: 'Headline',
                required: false,
                translatable: true,
                maxLength: 120,
            ),
        ];
    }

    public function defaultContent(): array
    {
        return ['headline' => ['en' => 'Default headline']];
    }

    public function defaultStyle(): array
    {
        return ['padding_y' => '48px'];
    }

    public function viewPartial(): string
    {
        return 'stubs.fake-hero';
    }

    public function allowsMultipleInstances(): bool
    {
        return false;
    }

    public function isDeletable(): bool
    {
        return true;
    }
}
