<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Collection;
use Umutsevimcann\VisualBuilder\Domain\Actions\ApplyTemplate;
use Umutsevimcann\VisualBuilder\Domain\Actions\SaveAsTemplate;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderTemplate;

/**
 * HTTP surface for the template library (v0.6.0).
 *
 * Endpoints:
 *   GET    /visual-builder/templates
 *          → list all saved templates (newest-first; name + description
 *          + thumbnail only — the `payload` is left OUT of the list
 *          response to keep the library grid light).
 *
 *   POST   /visual-builder/templates/{targetType}/{targetId}
 *          → snapshot the target's current sections as a new template.
 *          Request: { name: string, description?: string }.
 *
 *   POST   /visual-builder/templates/{id}/apply/{targetType}/{targetId}
 *          → apply the template to the given target. Request:
 *          { mode: 'replace' | 'append' }.
 *
 *   DELETE /visual-builder/templates/{id}
 *          → remove a template from the library. Existing sections
 *          created from it stay untouched.
 *
 * Same target-resolution convention as BuilderController — morph-map
 * lookup, unknown types yield 404. No arbitrary class names run.
 */
final class TemplateController extends BaseController
{
    public function index(): JsonResponse
    {
        /** @var Collection<int, BuilderTemplate> $rows */
        $rows = BuilderTemplate::query()
            ->orderByDesc('created_at')
            ->get();

        $templates = $rows->map(static function (BuilderTemplate $t): array {
            return [
                'id' => (int) $t->id,
                'name' => (string) $t->name,
                'description' => $t->description,
                'type' => (string) $t->type,
                'thumbnail_path' => $t->thumbnail_path,
                'section_count' => is_array($t->payload) ? count($t->payload) : 0,
                'created_at' => $t->created_at->toIso8601String(),
            ];
        })->all();

        return response()->json(['templates' => $templates]);
    }

    public function store(
        Request $request,
        SaveAsTemplate $action,
        string $targetType,
        int $targetId,
    ): JsonResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $target = $this->resolveTarget($targetType, $targetId);
        $template = $action->execute(
            $target,
            $validated['name'],
            $validated['description'] ?? null,
        );

        return response()->json([
            'success' => true,
            'template' => [
                'id' => (int) $template->id,
                'name' => (string) $template->name,
                'description' => $template->description,
                'type' => (string) $template->type,
                'section_count' => is_array($template->payload) ? count($template->payload) : 0,
            ],
        ], 201);
    }

    public function apply(
        Request $request,
        ApplyTemplate $action,
        int $id,
        string $targetType,
        int $targetId,
    ): JsonResponse {
        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:replace,append'],
        ]);

        /** @var BuilderTemplate $template */
        $template = BuilderTemplate::query()->findOrFail($id);
        $target = $this->resolveTarget($targetType, $targetId);

        $result = $action->execute($target, $template, $validated['mode']);

        return response()->json([
            'success' => true,
            'inserted' => $result['inserted'],
            'skipped' => $result['skipped'],
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        /** @var BuilderTemplate $template */
        $template = BuilderTemplate::query()->findOrFail($id);
        $template->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Morph-map target resolver — mirrors BuilderController::resolveTarget()
     * so the two endpoints enforce the same whitelist semantics.
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
}
