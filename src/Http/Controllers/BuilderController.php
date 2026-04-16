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
            // Fresh sections list — reorder'dan sonra sort_order'lar
            // degismis olabilir, client local state'i bu response ile
            // sync'lesin (window.location.reload yerine).
            'sections' => $this->serializeSections($target),
        ]);
    }

    /**
     * Create a new section on the target. Accepts the type key, an optional
     * instance key for multi-instance section types, and an optional
     * `after_section_id` for position-aware insertion (Elementor-style
     * `+` inserter between existing sections).
     *
     * Response is JSON when the client asks for JSON — the inline inserter
     * uses that to trigger an iframe reload without a full page redirect.
     */
    public function store(
        Request $request,
        CreateSection $action,
        string $targetType,
        int $targetId,
    ): RedirectResponse|JsonResponse {
        $validated = $request->validate([
            'type' => ['required', 'string'],
            'instance_key' => ['nullable', 'string', 'max:60'],
            // Optional: insert right after the given sibling section's
            // sort_order. Absent = append to end (original behaviour).
            'after_section_id' => ['nullable', 'integer'],
        ]);

        $target = $this->resolveTarget($targetType, $targetId);

        $afterSortOrder = null;
        if (! empty($validated['after_section_id'])) {
            $afterSection = BuilderSection::query()->find($validated['after_section_id']);
            // Only honour the hint when the sibling actually belongs to
            // this target — silently fall back to append otherwise. A
            // mismatched ID isn't a reason to 500 the editor.
            if ($afterSection !== null && (int) $afterSection->builder_id === $targetId) {
                $afterSortOrder = (int) $afterSection->sort_order;
            }
        }

        $section = $action->execute(
            $target,
            $validated['type'],
            $validated['instance_key'] ?? '__default__',
            $afterSortOrder,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'section_id' => $section->id,
                'sort_order' => $section->sort_order,
                // Full fresh list — client replaces its local state with this,
                // no need for an extra round-trip GET. Includes the bump of
                // sort_order'lari of sections shifted by the position-aware insert.
                'sections' => $this->serializeSections($target),
            ]);
        }

        return redirect()->back();
    }

    /**
     * Duplicate an existing section. Returns JSON for AJAX callers
     * (inline hover toolbar), redirect for form-POST callers.
     */
    public function duplicate(
        DuplicateSection $action,
        string $targetType,
        int $targetId,
        BuilderSection $section,
    ): RedirectResponse|JsonResponse {
        // Guard: verify the route's target matches the section's stored target.
        // Prevents cross-target manipulation via mismatched route params.
        $this->assertSectionBelongsTo($section, $targetType, $targetId);

        $clone = $action->execute($section);
        $target = $this->resolveTarget($targetType, $targetId);

        return request()->expectsJson()
            ? response()->json([
                'success' => true,
                'section_id' => $clone->id,
                'sort_order' => $clone->sort_order,
                'sections' => $this->serializeSections($target),
            ])
            : redirect()->back();
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

        if (request()->expectsJson()) {
            $target = $this->resolveTarget($targetType, $targetId);

            return response()->json([
                'success' => true,
                'sections' => $this->serializeSections($target),
            ]);
        }

        return redirect()->back();
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
     * Serialize the target's sections to the shape the client
     * `state.config.sections` expects. After every mutation we include
     * this in the response so the client replaces its local state in
     * one step — no extra GET round-trip, no `window.location.reload()`.
     *
     * @return array<int, array<string, mixed>>
     */
    private function serializeSections(Model $target): array
    {
        return $this->repository->forTarget($target)
            ->map(static fn (BuilderSection $s): array => [
                'id' => $s->id,
                'type' => $s->type,
                'instance_key' => $s->instance_key,
                'content' => $s->content ?? [],
                'style' => $s->style ?? [],
                'is_published' => (bool) $s->is_published,
                'sort_order' => (int) $s->sort_order,
            ])
            ->values()
            ->all();
    }

    /**
     * Guard: ensure a section's stored morph owner matches the route's target.
     *
     * `builder_type` is written by Eloquent as `Model::getMorphClass()` —
     * that's the alias when a morph map is registered, the FQCN otherwise.
     * Accept either form against the route's `targetType` alias so the guard
     * works regardless of map presence.
     */
    private function assertSectionBelongsTo(
        BuilderSection $section,
        string $targetType,
        int $targetId,
    ): void {
        if ($section->builder_id !== $targetId) {
            abort(403, 'Section does not belong to the requested target.');
        }

        $morphMap = Relation::morphMap();
        $expectedClass = $morphMap[$targetType] ?? $targetType;
        $sectionClass = $morphMap[$section->builder_type] ?? $section->builder_type;

        if ($sectionClass !== $expectedClass) {
            abort(403, 'Section does not belong to the requested target.');
        }
    }
}
