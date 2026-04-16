<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when the editor reorders sections on a buildable target.
 *
 * The event carries the target model (page, post, etc.) and the new
 * ordered list of section IDs. Listeners can invalidate caches, log
 * the reorder action, or trigger any downstream sort-dependent work.
 */
final class SectionsReordered
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  Model            $target       The buildable model whose sections were reordered.
     * @param  array<int, int>  $orderedIds   Section IDs in their new order.
     */
    public function __construct(
        public readonly Model $target,
        public readonly array $orderedIds,
    ) {}
}
