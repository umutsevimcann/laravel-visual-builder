<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Authorization;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Model;
use Umutsevimcann\VisualBuilder\Contracts\AuthorizationInterface;

/**
 * Default AuthorizationInterface implementation — delegates to Laravel's Gate.
 *
 * Behavior:
 *  - When `visual-builder.authorization_gate` is set in config, every check
 *    is forwarded to Gate::allows($gateName, $target).
 *  - When the config value is null (default), all checks return true —
 *    effectively a no-op. Route-level middleware (auth, admin) is expected
 *    to handle access control in that case.
 *
 * Override by binding a custom implementation:
 *
 *     $this->app->bind(
 *         AuthorizationInterface::class,
 *         \App\Authorization\MyAuthorization::class,
 *     );
 */
final class GateAuthorization implements AuthorizationInterface
{
    public function __construct(
        private readonly Gate $gate,
    ) {}

    public function check(string $ability, ?Model $target = null): bool
    {
        $gateName = config('visual-builder.authorization_gate');

        if (! is_string($gateName) || $gateName === '') {
            return true;
        }

        return $this->gate->allows($gateName, [$ability, $target]);
    }
}
