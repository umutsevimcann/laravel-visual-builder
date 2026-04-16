<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Sections;

use RuntimeException;

/**
 * In-memory registry of all SectionTypeInterface implementations.
 *
 * Registered as a singleton in the service container. User apps call
 * register() in their AppServiceProvider to add their section types:
 *
 *     public function boot(): void
 *     {
 *         $this->app->make(SectionTypeRegistry::class)
 *             ->register(new HeroSection())
 *             ->register(new GallerySection())
 *             ->register(new FaqSection());
 *     }
 *
 * The registry is the single source of truth consulted by:
 *  - Admin controllers (which types can be added?)
 *  - Form requests (dynamic validation rules from field definitions)
 *  - Frontend renderers (which Blade partial to include?)
 *  - Migration tooling (how to build content from legacy data)
 *
 * Double registration of the same key throws — catches copy-paste errors
 * early. Use has() to probe before registering if you must allow idempotent
 * boot cycles (hot reloads, etc.).
 *
 * @final For stability — extend by composition, not inheritance.
 */
final class SectionTypeRegistry
{
    /** @var array<string, SectionTypeInterface> */
    private array $types = [];

    /**
     * Register a section type. Returns $this for fluent chaining.
     *
     * @throws RuntimeException When a type with the same key() is already registered.
     */
    public function register(SectionTypeInterface $type): self
    {
        $key = $type->key();

        if (isset($this->types[$key])) {
            throw new RuntimeException(
                "Section type '{$key}' is already registered. Keys must be unique.",
            );
        }

        $this->types[$key] = $type;

        return $this;
    }

    /**
     * Find a registered type by key; null if not found.
     */
    public function find(string $key): ?SectionTypeInterface
    {
        return $this->types[$key] ?? null;
    }

    /**
     * Find a registered type or throw — use when the caller KNOWS the key
     * must exist (e.g. when loading an already-persisted section's type).
     *
     * @throws RuntimeException When the type is not registered.
     */
    public function findOrFail(string $key): SectionTypeInterface
    {
        $type = $this->find($key);

        if ($type === null) {
            throw new RuntimeException(
                "Section type '{$key}' is not registered. Did you forget to call register() in your AppServiceProvider?",
            );
        }

        return $type;
    }

    /**
     * All registered types, keyed by type key. Preserves registration order.
     *
     * @return array<string, SectionTypeInterface>
     */
    public function all(): array
    {
        return $this->types;
    }

    /**
     * All registered type keys, in registration order.
     *
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->types);
    }

    /**
     * Check whether a key is registered. Non-throwing alternative to find().
     */
    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }
}
