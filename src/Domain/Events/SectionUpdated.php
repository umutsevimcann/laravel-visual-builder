<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;

/**
 * Fired after a section's content, style, or visibility is updated.
 *
 * Note: this is a combined event covering all forms of mutation. Listeners
 * that need finer granularity can inspect $changes — the array of attribute
 * names that actually changed in this update (as reported by Eloquent's
 * dirty attribute tracking at persistence time).
 *
 * @property-read array<int, string> $changes List of changed attribute names.
 */
final class SectionUpdated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<int, string>  $changes  Attribute names that actually changed.
     */
    public function __construct(
        public readonly BuilderSection $section,
        public readonly array $changes = [],
    ) {}
}
