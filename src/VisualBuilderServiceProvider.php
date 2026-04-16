<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder;

use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Umutsevimcann\VisualBuilder\Authorization\GateAuthorization;
use Umutsevimcann\VisualBuilder\Contracts\AuthorizationInterface;
use Umutsevimcann\VisualBuilder\Contracts\BuilderRepositoryInterface;
use Umutsevimcann\VisualBuilder\Contracts\MediaServiceInterface;
use Umutsevimcann\VisualBuilder\Contracts\SanitizerInterface;
use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeRegistry;
use Umutsevimcann\VisualBuilder\Domain\Services\DesignTokenService;
use Umutsevimcann\VisualBuilder\Infrastructure\Media\StorageMediaService;
use Umutsevimcann\VisualBuilder\Infrastructure\Repositories\EloquentBuilderRepository;
use Umutsevimcann\VisualBuilder\Infrastructure\Sanitization\PurifierSanitizer;

/**
 * Service provider — wires the package into Laravel.
 *
 * Declarative configuration via spatie/laravel-package-tools:
 *   - config file (publishable + auto-merged)
 *   - views namespace (visual-builder::*)
 *   - publishable assets (CSS/JS)
 *   - migrations (publishable; users opt-in to run)
 *   - install command (php artisan visual-builder:install)
 *   - web routes (auto-registered when config.routes.enabled = true)
 *
 * Runtime container bindings in packageRegistered():
 *   - SectionTypeRegistry: singleton — shared registration across the request.
 *   - BuilderRepositoryInterface → EloquentBuilderRepository
 *   - MediaServiceInterface → StorageMediaService
 *   - SanitizerInterface → PurifierSanitizer
 *   - AuthorizationInterface → GateAuthorization
 *   - DesignTokenService: resolved via DI (its own deps are container-bound).
 *
 * Host apps override any binding in their own service provider AFTER this
 * one runs — Laravel's later-binding-wins semantics make overrides trivial.
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
            ->hasRoute('web')
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
     * own AppServiceProvider::register() — their provider runs after ours
     * so their bindings win.
     */
    public function packageRegistered(): void
    {
        // Singletons (shared state across the request)
        $this->app->singleton(SectionTypeRegistry::class);

        // Contract → default implementation bindings
        $this->app->bind(BuilderRepositoryInterface::class, EloquentBuilderRepository::class);
        $this->app->bind(MediaServiceInterface::class, StorageMediaService::class);
        $this->app->bind(SanitizerInterface::class, PurifierSanitizer::class);
        $this->app->bind(AuthorizationInterface::class, GateAuthorization::class);
    }

    /**
     * Conditional route registration. Skips route file loading when the
     * host app disables routes in config — they can still register their
     * own routes pointing to package controllers.
     */
    public function packageBooted(): void
    {
        if (config('visual-builder.routes.enabled', true) === false) {
            return;
        }

        $prefix = (string) config('visual-builder.routes.prefix', 'visual-builder');
        $middleware = (array) config('visual-builder.routes.middleware', ['web']);
        $namePrefix = (string) config('visual-builder.routes.name_prefix', 'visual-builder.');

        $this->app['router']
            ->prefix($prefix)
            ->middleware($middleware)
            ->name($namePrefix)
            ->group(__DIR__ . '/../routes/web.php');
    }
}
