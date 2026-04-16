<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;

/**
 * Contract for the BuilderSection persistence layer.
 *
 * The default implementation is EloquentBuilderRepository which uses the
 * shipped BuilderSection Eloquent model. Users with specialized data stores
 * (MongoDB, a legacy API, an in-memory cache for tests, etc.) swap it by
 * binding their own implementation in a service provider.
 *
 * All methods are synchronous — async/queued work belongs in Actions.
 */
interface BuilderRepositoryInterface
{
    /**
     * Find a single section by primary key.
     *
     * @param  int  $id  BuilderSection primary key.
     * @return BuilderSection|null  The section or null if not found.
     */
    public function find(int $id): ?BuilderSection;

    /**
     * Retrieve all sections for the given buildable model, ordered by sort_order.
     *
     * Includes published AND unpublished sections — callers that need only
     * visible ones should use visibleForTarget() instead.
     *
     * @param  Model  $target  Any model using the HasVisualBuilder trait.
     * @return Collection<int, BuilderSection>
     */
    public function forTarget(Model $target): Collection;

    /**
     * Retrieve only the sections currently visible on the frontend.
     *
     * "Visible" means is_published = true AND the current time falls within
     * the section's optional starts_at/ends_at scheduling window.
     *
     * @param  Model  $target  Any model using the HasVisualBuilder trait.
     * @return Collection<int, BuilderSection>
     */
    public function visibleForTarget(Model $target): Collection;

    /**
     * Find a specific instance of a section type for a target.
     *
     * Useful for singleton section types (e.g. "hero") where only one instance
     * exists per target. Multi-instance types distinguish siblings via the
     * instance_key column.
     *
     * @param  Model   $target        Any model using the HasVisualBuilder trait.
     * @param  string  $type          Section type key (e.g. 'hero').
     * @param  string  $instanceKey   Instance identifier; defaults to '__default__'.
     * @return BuilderSection|null
     */
    public function findByTypeInstance(Model $target, string $type, string $instanceKey = '__default__'): ?BuilderSection;

    /**
     * Persist a brand-new section.
     *
     * Callers are responsible for placing `builder_id`/`builder_type` in the
     * provided data array so the morph relationship is set correctly.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): BuilderSection;

    /**
     * Update the given section with the provided attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(BuilderSection $section, array $data): BuilderSection;

    /**
     * Delete a section. Returns false if deletion failed (rare).
     */
    public function delete(BuilderSection $section): bool;

    /**
     * Bulk-update sort_order for the given IDs in the provided sequence.
     *
     * Sort order is assigned starting at 0 in the order of the input array.
     * IDs that do not exist are silently ignored — UI callers frequently
     * send stale IDs after concurrent edits.
     *
     * @param  array<int, int>  $orderedIds
     */
    public function reorder(array $orderedIds): void;
}
