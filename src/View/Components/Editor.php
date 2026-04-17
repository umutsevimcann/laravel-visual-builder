<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use RuntimeException;
use Umutsevimcann\VisualBuilder\Contracts\BuilderRepositoryInterface;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;
use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeInterface;
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
 *
 * All view data is exposed as public readonly properties — Laravel's Blade
 * Component system passes them into the view automatically, so the view
 * can reference $target, $sections, $types, $tokens, $bootstrap directly.
 */
final class Editor extends Component
{
    public readonly string $targetType;

    public readonly int $targetId;

    /** @var Collection<int, BuilderSection> */
    public readonly Collection $sections;

    /** @var array<string, SectionTypeInterface> */
    public readonly array $types;

    /** @var array<string, mixed> */
    public readonly array $tokens;

    /** @var array<string, mixed> */
    public readonly array $bootstrap;

    /**
     * @throws RuntimeException When the target's class is not in the morph map.
     */
    public function __construct(
        public readonly Model $target,
    ) {
        $this->targetType = $this->resolveMorphAlias($target);
        $this->targetId = (int) $target->getKey();

        /** @var SectionTypeRegistry $registry */
        $registry = app(SectionTypeRegistry::class);
        /** @var BuilderRepositoryInterface $repository */
        $repository = app(BuilderRepositoryInterface::class);
        /** @var DesignTokenService $tokenService */
        $tokenService = app(DesignTokenService::class);

        $this->sections = $repository->forTarget($target);
        $this->types = $registry->all();
        $this->tokens = $tokenService->all();
        $this->bootstrap = $this->buildBootstrapPayload($registry, $tokenService);
    }

    public function render(): View
    {
        // Explicit data pass — Blade Component::data() extraction can be
        // finicky with readonly promoted/assigned properties on some
        // Laravel versions. Passing data() result directly avoids edge
        // cases and keeps the view-variable contract obvious.
        return view('visual-builder::editor', [
            'target' => $this->target,
            'targetType' => $this->targetType,
            'targetId' => $this->targetId,
            'sections' => $this->sections,
            'types' => $this->types,
            'tokens' => $this->tokens,
            'bootstrap' => $this->bootstrap,
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
    private function buildBootstrapPayload(
        SectionTypeRegistry $registry,
        DesignTokenService $tokenService,
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
                // Optional grouping key for the v0.5 block palette. Host
                // apps on older section-type implementations that predate
                // category() keep working — we default to 'general' so
                // every type still renders a palette tile.
                'category' => method_exists($type, 'category') ? (string) $type->category() : 'general',
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

        $sectionsPayload = $this->sections->map(static fn ($s) => [
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
            'tokens' => $tokenService->all(),
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
