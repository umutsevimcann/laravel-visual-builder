<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Umutsevimcann\VisualBuilder\Http\Controllers\BuilderController;
use Umutsevimcann\VisualBuilder\Http\Controllers\DesignTokenController;
use Umutsevimcann\VisualBuilder\Http\Controllers\TemplateController;

/*
|--------------------------------------------------------------------------
| Visual Builder Web Routes
|--------------------------------------------------------------------------
|
| Auto-registered by VisualBuilderServiceProvider when
| `visual-builder.routes.enabled` is true (default). Host apps that need
| custom prefixes, middleware, or bindings can disable auto-registration
| in config and wire their own routes pointing to the same controllers.
|
| URL pattern:  {prefix}/{targetType}/{targetId}/...
|   targetType  — morph alias registered in Relation::enforceMorphMap
|   targetId    — primary key of a HasVisualBuilder model
|
*/

Route::controller(BuilderController::class)->group(static function (): void {
    Route::get('{targetType}/{targetId}', 'show')
        ->name('show')
        ->whereAlpha('targetType')
        ->whereNumber('targetId');

    Route::post('{targetType}/{targetId}/save', 'save')
        ->name('save')
        ->whereAlpha('targetType')
        ->whereNumber('targetId');

    Route::post('{targetType}/{targetId}/sections', 'store')
        ->name('sections.store')
        ->whereAlpha('targetType')
        ->whereNumber('targetId');

    Route::post('{targetType}/{targetId}/sections/{section}/duplicate', 'duplicate')
        ->name('sections.duplicate')
        ->whereAlpha('targetType')
        ->whereNumber('targetId');

    Route::delete('{targetType}/{targetId}/sections/{section}', 'destroy')
        ->name('sections.destroy')
        ->whereAlpha('targetType')
        ->whereNumber('targetId');

    Route::post('upload-image', 'uploadImage')->name('upload-image');
});

Route::post('design-tokens', [DesignTokenController::class, 'update'])
    ->name('design-tokens.update');

/*
 * Template library — v0.6.0. Shared across every target; the admin
 * UI fetches the flat list once on open and then per-target save /
 * apply calls use the library row id + the current target's morph
 * type + id.
 */
Route::controller(TemplateController::class)->prefix('templates')->group(static function (): void {
    Route::get('', 'index')->name('templates.index');
    Route::delete('{id}', 'destroy')
        ->name('templates.destroy')
        ->whereNumber('id');
    Route::post('{targetType}/{targetId}', 'store')
        ->name('templates.store')
        ->whereAlpha('targetType')
        ->whereNumber('targetId');
    Route::post('{id}/apply/{targetType}/{targetId}', 'apply')
        ->name('templates.apply')
        ->whereNumber('id')
        ->whereAlpha('targetType')
        ->whereNumber('targetId');
});
