<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\View\Components;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\View\Component;
use RuntimeException;
use Umutsevimcann\VisualBuilder\Contracts\BuilderRepositoryInterface;
use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeRegistry;
use Umutsevimcann\VisualBuilder\Domain\Services\DesignTokenService;

/**
 * Blade component — drops the full builder UI into any admin view.
 *
 * Usage in a host app Blade view:
 *
 *     <x-visual-builder::editor :target="$page" />
 *
 * The component takes any Eloquent model that uses the HasVisualBuilder
 * trait, resolves its morph alias, and renders the full editor shell
 * (block palette + iframe + traits panel) inside the host's layout.
 *
 * Host apps are NOT required to extend any specific layout — drop the
 * component wherever it fits: AdminLTE, Filament, Nova, custom admins all
 * work. The component outputs a self-contained UI shell that manages its
 * own CSS/JS via the published assets.
 */
final class Editor extends Component
{
    public readonly string $targetType;

    public readonly int $targetId;

    /**
     * @throws RuntimeException When the target's class is not in the morph map.
     */
    public function __construct(
        public readonly Model $target,
    ) {
        $this->targetType = $this->resolveMorphAlias($target);
        $this->targetId = (int) $target->getKey();
    }

    public function render(): View|Htmlable|string
    {
        /** @var SectionTypeRegistry $registry */
        $registry = app(SectionTypeRegistry::class);
        /** @var BuilderRepositoryInterface $repository */
        $repository = app(BuilderRepositoryInterface::class);
        /** @var DesignTokenService $tokens */
        $tokens = app(DesignTokenService::class);

        return view('visual-builder::components.editor', [
            'target' => $this->target,
            'targetType' => $this->targetType,
            'targetId' => $this->targetId,
            'sections' => $repository->forTarget($this->target),
            'types' => $registry->all(),
            'tokens' => $tokens->all(),
            'bootstrap' => $this->bootstrapPayload($repository, $registry, $tokens),
        ]);
    }

    /**
     * Resolve the target's morph alias from Laravel's morph map.
     * Falls back to the class name when no alias is registered — acceptable
     * for dev, but host apps should enforce a morph map in production so
     * URLs don't leak fully-qualified class names.
     */
    private function resolveMorphAlias(Model $target): string
    {
        $class = $target::class;
        $morphMap = Relation::morphMap();
        $alias = array_search($class, $morphMap, true);

        if ($alias === false) {
            return str_replace('\\', '_', strtolower($class));
        }

        return (string) $alias;
    }

    /**
     * Assemble the JSON bootstrap object the client-side editor reads on boot.
     *
     * Contains everything the UI needs in one pass: route URLs, CSRF token,
     * section type metadata, current sections, design tokens.
     *
     * @return array<string, mixed>
     */
    private function bootstrapPayload(
        BuilderRepositoryInterface $repository,
        SectionTypeRegistry $registry,
        DesignTokenService $tokens,
    ): array {
        $namePrefix = (string) config('visual-builder.routes.name_prefix', 'visual-builder.');
        $builderPrefix = (string) config('visual-builder.builder_query_param', 'builder');

        $typesPayload = [];
        foreach ($registry->all() as $key => $type) {
            $typesPayload[$key] = [
                'key' => $type->key(),
                'label' => $type->label(),
                'description' => $type->description(),
                'icon' => $type->icon(),
                'preview_image' => $type->previewImage(),
                'allows_multiple' => $type->allowsMultipleInstances(),
                'is_deletable' => $type->isDeletable(),
                'default_content' => $type->defaultContent(),
                'default_style' => $type->defaultStyle(),
                'fields' => array_map(
                    static fn ($field) => [
                        'key' => $field->key,
                        'label' => $field->label,
                        'help' => $field->help,
                        'required' => $field->required,
                        'translatable' => $field->translatable,
                        'toggleable' => $field->toggleable,
                        'placeholder' => $field->placeholder,
                        'input_type' => $field->adminInputType(),
                    ],
                    $type->fields(),
                ),
            ];
        }

        $sectionsPayload = $repository->forTarget($this->target)->map(static fn ($s) => [
            'id' => $s->id,
            'type' => $s->type,
            'instance_key' => $s->instance_key,
            'content' => $s->content ?? [],
            'style' => $s->style ?? [],
            'is_published' => (bool) $s->is_published,
            'sort_order' => (int) $s->sort_order,
        ])->all();

        return [
            'target' => [
                'type' => $this->targetType,
                'id' => $this->targetId,
            ],
            'sections' => $sectionsPayload,
            'types' => $typesPayload,
            'tokens' => $tokens->all(),
            'routes' => [
                'save' => route($namePrefix.'save', [$this->targetType, $this->targetId]),
                'store' => route($namePrefix.'sections.store', [$this->targetType, $this->targetId]),
                'destroy_template' => route($namePrefix.'sections.destroy', [$this->targetType, $this->targetId, 0]),
                'duplicate_template' => route($namePrefix.'sections.duplicate', [$this->targetType, $this->targetId, 0]),
                'upload' => route($namePrefix.'upload-image'),
                'design_tokens' => route($namePrefix.'design-tokens.update'),
            ],
            'csrf_token' => csrf_token(),
            'builder_query_param' => $builderPrefix,
            'locales' => (array) config('visual-builder.locales', config('app.locales', ['en'])),
            'fallback_locale' => (string) config('app.fallback_locale', 'en'),
        ];
    }
}
