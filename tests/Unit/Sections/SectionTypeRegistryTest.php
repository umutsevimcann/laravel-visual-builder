<?php

declare(strict_types=1);

use Umutsevimcann\VisualBuilder\Domain\Fields\FieldDefinition;
use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeInterface;
use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeRegistry;

/**
 * Minimal SectionTypeInterface stub for registry tests.
 * Inline anonymous class avoids polluting the package namespace.
 */
function makeStubType(string $key): SectionTypeInterface
{
    return new class($key) implements SectionTypeInterface
    {
        public function __construct(private readonly string $k) {}

        public function key(): string
        {
            return $this->k;
        }

        public function label(): string
        {
            return 'Stub '.$this->k;
        }

        public function description(): string
        {
            return '';
        }

        public function icon(): string
        {
            return 'fa';
        }

        public function previewImage(): null|string
        {
            return null;
        }

        /** @return array<int, FieldDefinition> */
        public function fields(): array
        {
            return [];
        }

        public function defaultContent(): array
        {
            return [];
        }

        public function defaultStyle(): array
        {
            return [];
        }

        public function viewPartial(): string
        {
            return 'stub';
        }

        public function allowsMultipleInstances(): bool
        {
            return false;
        }

        public function isDeletable(): bool
        {
            return true;
        }
    };
}

it('registers a section type and retrieves it by key', function (): void {
    $registry = new SectionTypeRegistry;
    $type = makeStubType('hero');

    $registry->register($type);

    expect($registry->find('hero'))->toBe($type)
        ->and($registry->has('hero'))->toBeTrue()
        ->and($registry->keys())->toBe(['hero']);
});

it('returns null from find() for unregistered keys', function (): void {
    $registry = new SectionTypeRegistry;

    expect($registry->find('nonexistent'))->toBeNull()
        ->and($registry->has('nonexistent'))->toBeFalse();
});

it('throws when findOrFail() is called with an unregistered key', function (): void {
    $registry = new SectionTypeRegistry;

    expect(fn () => $registry->findOrFail('ghost'))
        ->toThrow(RuntimeException::class, "'ghost' is not registered");
});

it('rejects double registration of the same key', function (): void {
    $registry = new SectionTypeRegistry;
    $registry->register(makeStubType('hero'));

    expect(fn () => $registry->register(makeStubType('hero')))
        ->toThrow(RuntimeException::class, "'hero' is already registered");
});

it('preserves registration order via keys()', function (): void {
    $registry = new SectionTypeRegistry;
    $registry->register(makeStubType('hero'));
    $registry->register(makeStubType('about'));
    $registry->register(makeStubType('faq'));

    expect($registry->keys())->toBe(['hero', 'about', 'faq']);
});

it('returns a fluent interface from register()', function (): void {
    $registry = new SectionTypeRegistry;

    $result = $registry
        ->register(makeStubType('a'))
        ->register(makeStubType('b'));

    expect($result)->toBe($registry);
});
