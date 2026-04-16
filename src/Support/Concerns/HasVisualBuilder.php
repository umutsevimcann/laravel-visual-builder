<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Support\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderRevision;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;

/**
 * Trait that makes any Eloquent model buildable with Visual Builder.
 *
 * Add to your model:
 *
 *     use Umutsevimcann\VisualBuilder\Support\Concerns\HasVisualBuilder;
 *
 *     class Page extends Model
 *     {
 *         use HasVisualBuilder;
 *     }
 *
 * The trait adds two relationships:
 *  - builderSections    — ALL sections, ordered by sort_order
 *  - builderRevisions   — persisted revision snapshots (when server-side
 *                         revisions are enabled in config)
 *
 * And a helper method:
 *  - visibleBuilderSections() — only sections that pass isVisibleNow()
 *
 * @mixin Model
 */
trait HasVisualBuilder
{
    /**
     * All builder sections attached to this model, ordered by sort_order.
     *
     * @return MorphMany<BuilderSection, $this>
     */
    public function builderSections(): MorphMany
    {
        return $this->morphMany(BuilderSection::class, 'builder')
            ->orderBy('sort_order');
    }

    /**
     * Persisted revision snapshots (only when server revisions are enabled).
     *
     * @return MorphMany<BuilderRevision, $this>
     */
    public function builderRevisions(): MorphMany
    {
        return $this->morphMany(BuilderRevision::class, 'builder')
            ->latest();
    }

    /**
     * Only the sections currently visible to public visitors.
     *
     * Applies BOTH the is_published flag AND the optional scheduling
     * window (starts_at / ends_at) via BuilderSection::isVisibleNow().
     *
     * Internally fetches once, filters in memory — safe for typical
     * page-sized section counts (usually <20). For pages with hundreds
     * of sections, add a scope-based query instead.
     *
     * @return Collection<int, BuilderSection>
     */
    public function visibleBuilderSections(): Collection
    {
        return $this->builderSections
            ->filter(static fn (BuilderSection $section) => $section->isVisibleNow())
            ->values();
    }

    /**
     * URL the builder iframe loads to preview this model.
     *
     * Host apps override this method on their buildable model to return
     * the public route that renders the target (e.g. a page's permalink).
     * The default derives a URL from the table name + primary key, which
     * works for apps where frontend routes follow `/{table}/{id}` pattern.
     *
     * Return an absolute or relative URL — the builder will append the
     * builder-mode query parameter automatically.
     */
    public function builderPreviewUrl(): string
    {
        return url(Str::snake($this->getTable()) . '/' . $this->getKey());
    }
}
