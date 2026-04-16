<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Umutsevimcann\VisualBuilder\Contracts\BuilderRepositoryInterface;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;

/**
 * Default BuilderRepositoryInterface implementation backed by Eloquent.
 *
 * Users swap this out by binding a custom implementation to the interface
 * in their AppServiceProvider — for MongoDB, a legacy API facade, or an
 * in-memory store for testing.
 *
 * Query shape notes:
 *  - All target-scoped queries filter on builder_type + builder_id (morph key)
 *  - visibleForTarget adds is_published = true AND scheduling window check
 *  - reorder() runs in a single transaction; unknown IDs are silently skipped
 *    to tolerate stale UI state from concurrent editors
 */
final class EloquentBuilderRepository implements BuilderRepositoryInterface
{
    public function find(int $id): null|BuilderSection
    {
        return BuilderSection::query()->find($id);
    }

    public function forTarget(Model $target): Collection
    {
        return BuilderSection::query()
            ->where('builder_type', $target->getMorphClass())
            ->where('builder_id', $target->getKey())
            ->orderBy('sort_order')
            ->get();
    }

    public function visibleForTarget(Model $target): Collection
    {
        $now = Carbon::now();

        return BuilderSection::query()
            ->where('builder_type', $target->getMorphClass())
            ->where('builder_id', $target->getKey())
            ->where('is_published', true)
            ->where(static function ($q) use ($now): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(static function ($q) use ($now): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->orderBy('sort_order')
            ->get();
    }

    public function findByTypeInstance(
        Model $target,
        string $type,
        string $instanceKey = '__default__',
    ): null|BuilderSection {
        return BuilderSection::query()
            ->where('builder_type', $target->getMorphClass())
            ->where('builder_id', $target->getKey())
            ->where('type', $type)
            ->where('instance_key', $instanceKey)
            ->first();
    }

    public function create(array $data): BuilderSection
    {
        return BuilderSection::query()->create($data);
    }

    public function update(BuilderSection $section, array $data): BuilderSection
    {
        $section->fill($data)->save();

        return $section->fresh() ?? $section;
    }

    public function delete(BuilderSection $section): bool
    {
        return (bool) $section->delete();
    }

    public function reorder(array $orderedIds): void
    {
        if ($orderedIds === []) {
            return;
        }

        DB::transaction(static function () use ($orderedIds): void {
            foreach ($orderedIds as $position => $id) {
                BuilderSection::query()
                    ->where('id', $id)
                    ->update(['sort_order' => $position]);
            }
        });
    }
}
