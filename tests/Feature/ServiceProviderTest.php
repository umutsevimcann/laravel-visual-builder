<?php

declare(strict_types=1);

use Umutsevimcann\VisualBuilder\Authorization\GateAuthorization;
use Umutsevimcann\VisualBuilder\Contracts\AuthorizationInterface;
use Umutsevimcann\VisualBuilder\Contracts\BuilderRepositoryInterface;
use Umutsevimcann\VisualBuilder\Contracts\MediaServiceInterface;
use Umutsevimcann\VisualBuilder\Contracts\SanitizerInterface;
use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeRegistry;
use Umutsevimcann\VisualBuilder\Infrastructure\Media\StorageMediaService;
use Umutsevimcann\VisualBuilder\Infrastructure\Repositories\EloquentBuilderRepository;
use Umutsevimcann\VisualBuilder\Infrastructure\Sanitization\PurifierSanitizer;

it('boots the service provider under Orchestra Testbench', function (): void {
    expect($this->app)->not->toBeNull();
});

it('registers SectionTypeRegistry as a singleton', function (): void {
    $first = $this->app->make(SectionTypeRegistry::class);
    $second = $this->app->make(SectionTypeRegistry::class);

    expect($first)->toBe($second);
});

it('binds BuilderRepositoryInterface to the Eloquent default', function (): void {
    $impl = $this->app->make(BuilderRepositoryInterface::class);

    expect($impl)->toBeInstanceOf(EloquentBuilderRepository::class);
});

it('binds MediaServiceInterface to the Storage default', function (): void {
    $impl = $this->app->make(MediaServiceInterface::class);

    expect($impl)->toBeInstanceOf(StorageMediaService::class);
});

it('binds SanitizerInterface to the Purifier default', function (): void {
    $impl = $this->app->make(SanitizerInterface::class);

    expect($impl)->toBeInstanceOf(PurifierSanitizer::class);
});

it('binds AuthorizationInterface to the Gate default', function (): void {
    $impl = $this->app->make(AuthorizationInterface::class);

    expect($impl)->toBeInstanceOf(GateAuthorization::class);
});

it('loads the package config under the visual-builder namespace', function (): void {
    expect(config('visual-builder.routes.prefix'))->toBe('visual-builder')
        ->and(config('visual-builder.tables.sections'))->toBe('builder_sections')
        ->and(config('visual-builder.tables.revisions'))->toBe('builder_revisions');
});

it('runs package migrations on fresh test database', function (): void {
    // Package migrations are loaded by TestCase::setUp via
    // $this->loadMigrationsFrom. We verify the two core tables exist
    // by running a harmless SELECT COUNT against each.
    $sectionsCount = DB::table(config('visual-builder.tables.sections'))->count();
    $revisionsCount = DB::table(config('visual-builder.tables.revisions'))->count();

    expect($sectionsCount)->toBe(0)
        ->and($revisionsCount)->toBe(0);
});
