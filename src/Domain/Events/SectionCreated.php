<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;

/**
 * Fired when a new section is successfully created via the builder.
 *
 * Listeners typically use this to:
 *  - Invalidate page caches scoped to the parent builder model.
 *  - Log audit trails ("editor X added a Hero section to Page Y").
 *  - Warm search indices that reference section content.
 */
final class SectionCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly BuilderSection $section) {}
}
