<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Contract for authorization checks in Visual Builder controllers.
 *
 * Decouples the package from any specific authorization system. Implementations
 * may wrap Laravel Gates/Policies, Spatie Permission, custom roles, or simply
 * check an `is_admin` flag.
 *
 * The default implementation calls `Gate::allows()` with the gate name from
 * config (`visual-builder.authorization_gate`) when set, otherwise returns
 * true — letting the route middleware stack do all the work.
 *
 * User apps override by binding:
 *
 *     $this->app->bind(
 *         \Umutsevimcann\VisualBuilder\Contracts\AuthorizationInterface::class,
 *         \App\Builder\MyAuthorization::class,
 *     );
 */
interface AuthorizationInterface
{
    /**
     * Determine whether the current user may perform an action on a target.
     *
     * @param  string      $ability  Semantic action name: view, edit, delete, publish, etc.
     * @param  Model|null  $target   The model being acted upon (the builder's parent page,
     *                               post, product, etc.) or null for type-wide checks.
     * @return bool                  True if authorized, false to deny.
     */
    public function check(string $ability, ?Model $target = null): bool;
}
