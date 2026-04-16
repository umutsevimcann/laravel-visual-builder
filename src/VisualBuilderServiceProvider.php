<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder;

use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Umutsevimcann\VisualBuilder\Authorization\GateAuthorization;
use Umutsevimcann\VisualBuilder\Contracts\AuthorizationInterface;
use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeRegistry;

/**
 * Service provider — wires the package into Laravel.
 *
 * Declarative configuration via spatie/laravel-package-tools:
 *   - config file (publishable + auto-merged)
 *   - views namespace (visual-builder::*)
 *   - publishable assets (CSS/JS)
 *   - migrations (publishable; users opt-in to run)
 *   - install command (php artisan visual-builder:install)
 *
 * Runtime container bindings in packageRegistered():
 *   - SectionTypeRegistry: singleton so all users of the registry see the
 *     same registered type list across the request.
 *   - AuthorizationInterface: default bound to GateAuthorization, which
 *     respects the optional `visual-builder.authorization_gate` config.
 *
 * User apps override any contract binding in their own service provider
 * AFTER this one runs (order of provider registration). Repositories,
 * controllers, actions, and routes are registered in Phase C.
 */
final class VisualBuilderServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-visual-builder')
            ->hasConfigFile('visual-builder')
            ->hasViews('visual-builder')
            ->hasAssets()
            ->hasMigrations([
                'create_builder_sections_table',
                'create_builder_revisions_table',
            ])
            ->hasInstallCommand(static function (InstallCommand $command): void {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->publishAssets()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('umutsevimcann/laravel-visual-builder');
            });
    }

    /**
     * Bind package services in the IoC container.
     *
     * Users override any of these by re-binding the same abstract in their
     * AppServiceProvider::register() — their provider runs after ours so
     * their bindings win.
     */
    public function packageRegistered(): void
    {
        $this->app->singleton(SectionTypeRegistry::class);

        $this->app->bind(AuthorizationInterface::class, GateAuthorization::class);
    }
}
