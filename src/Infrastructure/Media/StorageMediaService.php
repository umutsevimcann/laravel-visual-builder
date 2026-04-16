<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Infrastructure\Media;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Umutsevimcann\VisualBuilder\Contracts\MediaServiceInterface;

/**
 * Default MediaServiceInterface — stores on a Laravel filesystem disk.
 *
 * Configuration (config/visual-builder.php):
 *   'media' => [
 *     'disk'      => 'public',           // Any disk defined in filesystems.php
 *     'directory' => 'visual-builder',   // Prefix within the disk
 *   ]
 *
 * Upload path pattern:  {configured_dir}/{optional_subdir}/{uuid}.{ext}
 * URL resolution uses the disk's url() for relative paths; asset() for
 * paths under "assets/"; pass-through for absolute http(s):// URLs.
 *
 * Why a factory instead of Storage facade?
 *   Easier to unit-test (inject a fake) and sidesteps issues when running
 *   without a fully-booted app context (package-only analysis).
 */
final class StorageMediaService implements MediaServiceInterface
{
    public function __construct(
        private readonly FilesystemFactory $storage,
    ) {}

    public function upload(UploadedFile $file, string $directory = ''): string
    {
        $disk = $this->diskName();
        $base = trim((string) config('visual-builder.media.directory', 'visual-builder'), '/');
        $sub = trim($directory, '/');

        $targetDir = $base;
        if ($sub !== '') {
            $targetDir .= '/' . $sub;
        }

        $name = Str::uuid()->toString() . '.' . $this->safeExtension($file);
        $path = $targetDir . '/' . $name;

        $this->storage->disk($disk)->putFileAs($targetDir, $file, $name);

        return $path;
    }

    public function delete(string $path): bool
    {
        $disk = $this->diskName();

        // Idempotent — exists() check prevents "file not found" exceptions
        // from bubbling up when listeners clean up sections whose media
        // was already removed.
        if (! $this->storage->disk($disk)->exists($path)) {
            return false;
        }

        return $this->storage->disk($disk)->delete($path);
    }

    public function url(string $path): string
    {
        if ($path === '') {
            return '';
        }

        // Absolute URLs pass through unchanged.
        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        // Pre-shipped assets (package or app-bundled) resolve via asset().
        if (Str::startsWith($path, 'assets/')) {
            return asset($path);
        }

        return $this->storage->disk($this->diskName())->url($path);
    }

    private function diskName(): string
    {
        return (string) config('visual-builder.media.disk', 'public');
    }

    /**
     * Normalize the uploaded file's extension.
     * Falls back to "bin" when the client did not send a recognized extension.
     */
    private function safeExtension(UploadedFile $file): string
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());

        return $ext !== '' ? $ext : 'bin';
    }
}
