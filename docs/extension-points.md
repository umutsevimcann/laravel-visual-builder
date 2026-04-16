# Extension Points

The package is designed around four contracts — every runtime behavior that might vary between apps sits behind an interface you can swap via a one-line binding in your `AppServiceProvider`. This document lists each contract, when you'd swap it, and how.

---

## `BuilderRepositoryInterface`

The persistence layer for `BuilderSection`.

**Default:** `EloquentBuilderRepository` — uses the shipped Eloquent model.

**When to swap:**

- You persist sections in MongoDB, DynamoDB, a legacy REST API, or a content delivery service.
- You want to add caching/tagging behavior wrapping every read and write.
- You're writing integration tests and want an in-memory stub.

**How:**

```php
// app/Providers/AppServiceProvider.php
use Umutsevimcann\VisualBuilder\Contracts\BuilderRepositoryInterface;
use App\Builder\CachedEloquentRepository;

public function register(): void
{
    $this->app->bind(
        BuilderRepositoryInterface::class,
        CachedEloquentRepository::class,
    );
}
```

Your implementation receives target models via the interface's method signatures. You do not need to reuse the package's `BuilderSection` Eloquent model — return any object shape that mirrors its public properties.

---

## `MediaServiceInterface`

File upload, URL resolution, deletion.

**Default:** `StorageMediaService` — uses the disk configured in `visual-builder.media.disk` (default `public`).

**When to swap:**

- You use Spatie Media Library or another file management package.
- Your uploads need to hit a private S3 bucket with pre-signed URLs.
- You want to run images through an image optimizer before storage.

**How:**

```php
use Umutsevimcann\VisualBuilder\Contracts\MediaServiceInterface;

$this->app->bind(MediaServiceInterface::class, MyCdnMediaService::class);
```

The interface has three methods: `upload(UploadedFile, string $directory): string`, `delete(string $path): bool`, `url(string $path): string`. `upload()` MUST return a string that `url()` can later resolve to a public URL. `delete()` MUST be idempotent.

---

## `SanitizerInterface`

HTML purification for rich-text fields before persistence.

**Default:** `PurifierSanitizer` — delegates to `mews/purifier` when installed, otherwise a `strip_tags` allow-list.

**When to swap:**

- You allow a wider tag set (e.g. embedded iframes from specific domains).
- You need HTMLPurifier directly with a custom configuration.
- You want to plug in a third-party content safety API.

**How:**

```php
use Umutsevimcann\VisualBuilder\Contracts\SanitizerInterface;

$this->app->bind(SanitizerInterface::class, MyHtmlPurifier::class);
```

---

## `AuthorizationInterface`

Permission check for every write action in the builder controller.

**Default:** `GateAuthorization` — respects `visual-builder.authorization_gate` if set, otherwise permissive.

**When to swap:**

- You use Spatie Permission or a custom role system.
- You want per-section authorization (editor X may only edit pages they own).
- You need to audit every authorization decision.

**How:**

```php
use Umutsevimcann\VisualBuilder\Contracts\AuthorizationInterface;
use Illuminate\Database\Eloquent\Model;

final class MyAuthorization implements AuthorizationInterface
{
    public function check(string $ability, null|Model $target = null): bool
    {
        // Examples:
        //   $ability = 'update'            — bulk save on any section
        //   $ability = 'upload-media'      — image upload endpoint
        //   $ability = 'manage-design-system' — site settings write
        return auth()->user()?->can($ability, $target) ?? false;
    }
}
```

```php
$this->app->bind(AuthorizationInterface::class, MyAuthorization::class);
```

---

## Publishable assets

Publish the package's views, CSS, JS, migrations, or config to your app — then edit them freely. Package updates won't overwrite your changes.

```bash
php artisan vendor:publish --tag=visual-builder-config     # config/visual-builder.php
php artisan vendor:publish --tag=visual-builder-views      # resources/views/vendor/visual-builder/*
php artisan vendor:publish --tag=visual-builder-migrations # database/migrations/*
php artisan vendor:publish --tag=visual-builder-assets     # public/vendor/visual-builder/*
```

Once published, Blade resolves your copy before the package's — useful for swapping the whole editor shell view without writing a class.

---

## Routes

The package auto-registers routes under `config('visual-builder.routes')`. Disable and re-register with your own middleware stack:

```php
// config/visual-builder.php
return [
    'routes' => [
        'enabled' => false,   // auto-registration off
        // ... unused keys ignored
    ],
];
```

```php
// routes/web.php
use Umutsevimcann\VisualBuilder\Http\Controllers\BuilderController;

Route::prefix('admin/builder')
    ->middleware(['web', 'auth', 'role:editor'])
    ->controller(BuilderController::class)
    ->name('admin.builder.')
    ->group(function () {
        Route::get('/{targetType}/{targetId}', 'show')->name('show');
        // ...
    });
```

Your routes can reuse the controller methods with a different prefix, middleware chain, or name scheme.

---

## Events

The package dispatches four events. Listen in your `AppServiceProvider` or an `EventServiceProvider`:

| Event | Dispatched when | Listener use case |
|---|---|---|
| `SectionCreated` | After a new section row is inserted | Invalidate page cache; log audit trail |
| `SectionUpdated` | After content/style/visibility mutation | Invalidate cache; warm search index |
| `SectionDeleted` | After a section is deleted | Clean up orphaned media; log audit |
| `SectionsReordered` | After a bulk reorder | Invalidate cache; update sitemap |

```php
// AppServiceProvider::boot()
use Umutsevimcann\VisualBuilder\Domain\Events\SectionUpdated;

Event::listen(SectionUpdated::class, function (SectionUpdated $event) {
    Cache::tags(['homepage'])->flush();
});
```

All events use `Dispatchable + SerializesModels` and are queue-compatible.

---

## Morph map

Required for every host app. The builder's route `{targetType}/{targetId}` resolves `targetType` against Laravel's morph map — unmapped types return 404.

```php
// AppServiceProvider::boot()
use Illuminate\Database\Eloquent\Relations\Relation;

Relation::enforceMorphMap([
    'page' => \App\Models\Page::class,
    'post' => \App\Models\Post::class,
    'product' => \App\Models\Product::class,
]);
```

The alias is what appears in builder URLs (`/visual-builder/page/5`), so keep it short and stable.

---

## Section type registration

Register your section types at boot:

```php
use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeRegistry;
use App\Builder\HeroSection;
use App\Builder\FaqSection;

public function boot(): void
{
    $this->app->make(SectionTypeRegistry::class)
        ->register(new HeroSection())
        ->register(new FaqSection());
}
```

The registry is a singleton — every part of the system (editor UI, save controller, frontend renderer) reads from the same source of truth.

See [field-types.md](field-types.md) for the field DSL and [architecture.md](architecture.md) for how these pieces fit together.
