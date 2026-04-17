<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder;

use Illuminate\Support\Facades\Blade;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Umutsevimcann\VisualBuilder\Authorization\GateAuthorization;
use Umutsevimcann\VisualBuilder\Contracts\AuthorizationInterface;
use Umutsevimcann\VisualBuilder\Contracts\BuilderRepositoryInterface;
use Umutsevimcann\VisualBuilder\Contracts\MediaServiceInterface;
use Umutsevimcann\VisualBuilder\Contracts\SanitizerInterface;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;
use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeRegistry;
use Umutsevimcann\VisualBuilder\Domain\Services\BreakpointStyleResolver;
use Umutsevimcann\VisualBuilder\Domain\Services\DesignTokenService;
use Umutsevimcann\VisualBuilder\Infrastructure\Media\StorageMediaService;
use Umutsevimcann\VisualBuilder\Infrastructure\Repositories\EloquentBuilderRepository;
use Umutsevimcann\VisualBuilder\Infrastructure\Sanitization\PurifierSanitizer;
use Umutsevimcann\VisualBuilder\View\Components\Editor;

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
        // Routes are NOT registered via hasRoute() — they would load with
        // no prefix/middleware. Instead packageBooted() sets up the route
        // group with config-driven prefix + middleware + name prefix, so
        // host apps can customize every aspect without publishing the
        // route file.
        // View components are registered via Blade::componentNamespace() in
        // packageBooted() — that method produces <x-visual-builder::editor>
        // (double-colon) syntax, matching the documented component tag.
        // Spatie's hasViewComponent() would register as visual-builder-editor
        // (hyphen) which conflicts with convention.
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
     * own AppServiceProvider::register() — their provider runs after ours
     * so their bindings win.
     */
    public function packageRegistered(): void
    {
        // Singletons (shared state across the request)
        $this->app->singleton(SectionTypeRegistry::class);

        // Breakpoint thresholds come from config/visual-builder.php and
        // are singleton so every consumer (Blade directive, editor
        // bootstrap, iframe inject) sees the same numbers. Resolver
        // throws on construction if tablet_max <= mobile_max — callers
        // never observe an invalid config.
        $this->app->singleton(BreakpointStyleResolver::class, static function ($app): BreakpointStyleResolver {
            $config = (array) $app['config']->get('visual-builder.breakpoints', []);

            return new BreakpointStyleResolver(
                tabletMaxPx: (int) ($config['tablet_max'] ?? 1023),
                mobileMaxPx: (int) ($config['mobile_max'] ?? 767),
            );
        });

        // Contract → default implementation bindings
        $this->app->bind(BuilderRepositoryInterface::class, EloquentBuilderRepository::class);
        $this->app->bind(MediaServiceInterface::class, StorageMediaService::class);
        $this->app->bind(SanitizerInterface::class, PurifierSanitizer::class);
        $this->app->bind(AuthorizationInterface::class, GateAuthorization::class);
    }

    /**
     * Conditional route registration + Blade component namespace.
     * Registers <x-visual-builder::editor> via Blade::componentNamespace
     * so the double-colon syntax matches README documentation.
     */
    public function packageBooted(): void
    {
        Blade::componentNamespace(
            'Umutsevimcann\\VisualBuilder\\View\\Components',
            'visual-builder',
        );

        // @vbSectionStyles($section) — emits a <style> block with the
        // section's resolved CSS including @media queries for responsive
        // overrides. Host section partials call this once near the top
        // so the cascade applies before any inline styles in the markup.
        //
        // Accepts either a BuilderSection model (reads ->id + ->style)
        // or a raw array with 'id' and 'style' keys — that second shape
        // keeps the directive usable from array-serialized sections (e.g.
        // in iframe bootstrap payloads) without forcing a model hydrate.
        Blade::directive('vbSectionStyles', static function (string $expression): string {
            return "<?php echo \\Umutsevimcann\\VisualBuilder\\VisualBuilder::sectionStylesTag({$expression}); ?>";
        });

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
            ->group(__DIR__.'/../routes/web.php');
    }
}
