<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;

/**
 * Fired immediately after a section row is removed from persistence.
 *
 * The BuilderSection instance passed is the in-memory model — its
 * relationships and attributes are still queryable, but any database
 * lookup by ID will return null.
 *
 * Listeners use this to:
 *  - Invalidate caches.
 *  - Clean up associated media (orphaned uploaded images, etc.) via the
 *    bound MediaServiceInterface.
 *  - Record deletion in an audit log.
 */
final class SectionDeleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly BuilderSection $section) {}
}
