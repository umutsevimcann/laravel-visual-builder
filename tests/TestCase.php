<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Umutsevimcann\VisualBuilder\VisualBuilderServiceProvider;

/**
 * Base TestCase that boots a minimal Laravel app with the package registered.
 *
 * Uses orchestra/testbench to provide a lightweight Laravel kernel — tests
 * get the container, config system, database, and full service providers
 * without requiring a real host application.
 *
 * Individual test cases extend this class. Pest tests use the Pest base
 * class mapping in Pest.php (auto-uses this TestCase in tests/Feature/).
 */
abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Run package migrations against the testbench sqlite memory DB.
        $this->loadMigrationsFrom(__DIR__.'/stubs/migrations');
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [VisualBuilderServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
