<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Contracts;

use Illuminate\Http\UploadedFile;

/**
 * Contract for file upload and retrieval used by Visual Builder image fields.
 *
 * Implementations decide where files live (local disk, S3, Spatie Media Library,
 * Cloudinary, etc.). The package ships a sensible default (local `public` disk)
 * but users typically bind their own implementation in a service provider:
 *
 *     $this->app->bind(
 *         \Umutsevimcann\VisualBuilder\Contracts\MediaServiceInterface::class,
 *         \App\Services\MyS3MediaService::class,
 *     );
 *
 * Contract guarantees:
 *  - upload() returns a relative, storage-independent path string that can be
 *    persisted in the database and later resolved via url().
 *  - delete() is idempotent — calling it on a missing path must NOT throw.
 *  - url() must return a browser-accessible URL for the stored asset.
 */
interface MediaServiceInterface
{
    /**
     * Persist an uploaded file and return the storage path.
     *
     * @param  UploadedFile  $file       The PHP upload (already validated by the caller).
     * @param  string        $directory  Sub-directory hint within the implementation's base location.
     * @return string                    Storage path (e.g. `visual-builder/abc123.jpg`).
     */
    public function upload(UploadedFile $file, string $directory = ''): string;

    /**
     * Delete a previously uploaded file by its storage path.
     *
     * Implementations MUST NOT throw if the file does not exist. Callers rely
     * on idempotent behavior when cleaning up orphaned references.
     *
     * @param  string  $path  Storage path returned by a previous upload().
     * @return bool           True on successful delete, false otherwise.
     */
    public function delete(string $path): bool;

    /**
     * Resolve a storage path to a browser-accessible URL.
     *
     * The path may be a relative storage path (e.g. `visual-builder/abc.jpg`),
     * an asset path starting with `assets/`, or an absolute URL. Implementations
     * should handle all three gracefully.
     *
     * @param  string  $path  Storage path, asset path, or absolute URL.
     * @return string         Browser-accessible URL.
     */
    public function url(string $path): string;
}
