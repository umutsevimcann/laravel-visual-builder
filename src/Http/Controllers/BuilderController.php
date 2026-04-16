<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use RuntimeException;
use Umutsevimcann\VisualBuilder\Contracts\BuilderRepositoryInterface;
use Umutsevimcann\VisualBuilder\Contracts\MediaServiceInterface;
use Umutsevimcann\VisualBuilder\Domain\Actions\ApplyBuilderLayout;
use Umutsevimcann\VisualBuilder\Domain\Actions\CreateSection;
use Umutsevimcann\VisualBuilder\Domain\Actions\DeleteSection;
use Umutsevimcann\VisualBuilder\Domain\Actions\DuplicateSection;
use Umutsevimcann\VisualBuilder\Domain\DTOs\BuilderLayoutData;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;
use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeRegistry;
use Umutsevimcann\VisualBuilder\Domain\Services\DesignTokenService;
use Umutsevimcann\VisualBuilder\Http\Requests\BuilderSaveRequest;
use Umutsevimcann\VisualBuilder\Http\Requests\UploadImageRequest;

/**
 * Thin HTTP controller for the Visual Builder admin endpoints.
 *
 * All business logic lives in Actions — this controller is purely an
 * orchestration layer:
 *   1. Resolve the target model from the route (class name + id morph).
 *   2. Invoke the appropriate Action.
 *   3. Shape the response (Blade view, JSON, or redirect).
 *
 * Target resolution convention:
 *   URL:   /visual-builder/{targetType}/{targetId}/...
 *   where `targetType` is the morph alias for a HasVisualBuilder model.
 *
 * The host app controls which targets are exposed by binding the morph map
 * in its AppServiceProvider — the builder never instantiates arbitrary
 * class names from URL input.
 */
final class BuilderController extends BaseController
{
    public function __construct(
        private readonly BuilderRepositoryInterface $repository,
        private readonly SectionTypeRegistry $registry,
        private readonly DesignTokenService $tokens,
    ) {}

    /**
     * Render the editor shell with bootstrapped state.
     */
    public function show(Request $request, string $targetType, int $targetId): View
    {
        $target = $this->resolveTarget($targetType, $targetId);

        $sections = $this->repository->forTarget($target);

        return view('visual-builder::builder', [
            'target' => $target,
            'sections' => $sections,
            'types' => $this->registry->all(),
            'tokens' => $this->tokens->all(),
        ]);
    }

    /**
     * Bulk save: accepts a BuilderLayoutData payload and applies all changes
     * in a single transaction.
     */
    public function save(
        BuilderSaveRequest $request,
        ApplyBuilderLayout $action,
        string $targetType,
        int $targetId,
    ): JsonResponse {
        $target = $this->resolveTarget($targetType, $targetId);
        $dto = BuilderLayoutData::fromRequest($request);
        $result = $action->execute($target, $dto);

        return response()->json([
            'success' => true,
            'updated' => $result['updated'],
            'reordered' => $result['reordered'],
        ]);
    }

    /**
     * Create a new section on the target. Accepts the type key and an
     * optional instance key for multi-instance section types.
     */
    public function store(
        Request $request,
        CreateSection $action,
        string $targetType,
        int $targetId,
    ): RedirectResponse {
        $validated = $request->validate([
            'type' => ['required', 'string'],
            'instance_key' => ['nullable', 'string', 'max:60'],
        ]);

        $target = $this->resolveTarget($targetType, $targetId);

        $action->execute(
            $target,
            $validated['type'],
            $validated['instance_key'] ?? '__default__',
        );

        return redirect()->back();
    }

    /**
     * Duplicate an existing section.
     */
    public function duplicate(
        DuplicateSection $action,
        string $targetType,
        int $targetId,
        BuilderSection $section,
    ): RedirectResponse {
        // Guard: verify the route's target matches the section's stored target.
        // Prevents cross-target manipulation via mismatched route params.
        $this->assertSectionBelongsTo($section, $targetType, $targetId);

        $action->execute($section);

        return redirect()->back();
    }

    /**
     * Delete a section (non-singleton types only — enforced in Action).
     */
    public function destroy(
        DeleteSection $action,
        string $targetType,
        int $targetId,
        BuilderSection $section,
    ): JsonResponse|RedirectResponse {
        $this->assertSectionBelongsTo($section, $targetType, $targetId);

        $action->execute($section);

        return request()->expectsJson()
            ? response()->json(['success' => true])
            : redirect()->back();
    }

    /**
     * Upload an image, returning its storage path and public URL.
     */
    public function uploadImage(
        UploadImageRequest $request,
        MediaServiceInterface $media,
    ): JsonResponse {
        $path = $media->upload(
            $request->file('file'),
            (string) $request->input('directory', ''),
        );

        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => $media->url($path),
        ]);
    }

    /**
     * Resolve the buildable target model from route parameters.
     *
     * Uses Laravel's morph map — the host app registers the allowed types
     * via Relation::enforceMorphMap([...]). Unregistered types yield 404.
     *
     * @throws RuntimeException When the target cannot be resolved.
     */
    private function resolveTarget(string $targetType, int $targetId): Model
    {
        $morphMap = Relation::morphMap();

        $class = $morphMap[$targetType] ?? null;
        if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
            abort(404, "Unknown builder target type '{$targetType}'.");
        }

        /** @var Model|null $model */
        $model = $class::query()->find($targetId);
        if ($model === null) {
            abort(404, "Builder target '{$targetType}#{$targetId}' not found.");
        }

        return $model;
    }

    /**
     * Guard: ensure a section's stored morph owner matches the route's target.
     */
    private function assertSectionBelongsTo(
        BuilderSection $section,
        string $targetType,
        int $targetId,
    ): void {
        $morphMap = Relation::morphMap();
        $expected = $morphMap[$targetType] ?? null;

        if ($section->builder_type !== $expected || $section->builder_id !== $targetId) {
            abort(403, 'Section does not belong to the requested target.');
        }
    }
}
