<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder;

use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * VisualBuilderServiceProvider — package wiring.
 *
 * Uses spatie/laravel-package-tools to declaratively register:
 *   - Config (publishable, auto-merged)
 *   - Migrations (publishable)
 *   - Views (namespaced 'visual-builder::')
 *   - Assets (JS + CSS, publishable)
 *   - Install command (php artisan visual-builder:install)
 *
 * Binding of contracts → default implementations happens in registerBindings().
 * Users override by re-binding their own implementations in AppServiceProvider.
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
     * Bind contracts to default implementations.
     * User apps override any of these by re-binding in their own
     * service provider AFTER this one runs.
     */
    public function packageRegistered(): void
    {
        // Contracts → default implementations (registered in Phase B/C commits)
        // Example:
        // $this->app->bind(
        //     Contracts\BuilderRepositoryInterface::class,
        //     Infrastructure\Repositories\EloquentBuilderRepository::class
        // );
    }

    /**
     * Boot-time setup after all services are registered.
     */
    public function packageBooted(): void
    {
        // Register route files, blade components, event listeners, etc.
        // Details added in later phases.
    }
}
