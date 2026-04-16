<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Actions;

use Umutsevimcann\VisualBuilder\Contracts\BuilderRepositoryInterface;
use Umutsevimcann\VisualBuilder\Domain\Events\SectionUpdated;
use Umutsevimcann\VisualBuilder\Domain\Fields\FieldDefinition;
use Umutsevimcann\VisualBuilder\Domain\Fields\LinkField;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;
use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeRegistry;

/**
 * Merge incoming content patch into a section's stored content JSON.
 *
 * Per-field whitelist + sanitize:
 *  - Only registered fields of the section's type are accepted.
 *  - Unknown keys in the input are silently discarded.
 *  - Each field's sanitize() method runs on its own value (HTML purifier,
 *    boolean coercion, repeater recursion, etc.).
 *
 * Three reserved meta keys are also accepted and whitelisted:
 *  - _visibility:      { field_key: bool } — eye-icon state per field
 *  - _element_styles:  { field_key: { color, font_size, ... } } — per-field
 *                      typography/color/spacing overrides
 *
 * Unknown top-level meta keys (anything starting with _ other than the two
 * documented ones) are rejected.
 */
final class UpdateSectionContent
{
    /**
     * Whitelist of CSS properties allowed in per-element style overrides.
     * Intentionally narrow — rejects arbitrary CSS to prevent injection.
     */
    private const ALLOWED_ELEMENT_STYLE_PROPS = [
        'color', 'background_color', 'font_family', 'font_size', 'font_weight',
        'letter_spacing', 'line_height', 'text_align', 'text_transform',
        'padding', 'margin', 'border_radius', 'opacity', 'box_shadow',
    ];

    public function __construct(
        private readonly BuilderRepositoryInterface $repository,
        private readonly SectionTypeRegistry $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $input  Raw patch from the request.
     */
    public function execute(BuilderSection $section, array $input): BuilderSection
    {
        $type = $this->registry->findOrFail($section->type);
        $current = $section->content ?? [];
        $new = $current;

        foreach ($type->fields() as $field) {
            $incoming = $this->collectFieldInput($field, $input);
            if ($incoming === null) {
                continue;
            }

            foreach ($incoming as $subKey => $value) {
                $new[$subKey] = $field->sanitize($value);
            }
        }

        $allowedFieldKeys = array_map(
            static fn (FieldDefinition $f) => $f->key,
            $type->fields(),
        );

        if (isset($input['_visibility']) && is_array($input['_visibility'])) {
            $new['_visibility'] = $this->mergeVisibility(
                $new['_visibility'] ?? [],
                $input['_visibility'],
                $allowedFieldKeys,
            );
        }

        if (isset($input['_element_styles']) && is_array($input['_element_styles'])) {
            $merged = $this->mergeElementStyles(
                $new['_element_styles'] ?? [],
                $input['_element_styles'],
                $allowedFieldKeys,
            );

            if ($merged === []) {
                unset($new['_element_styles']);
            } else {
                $new['_element_styles'] = $merged;
            }
        }

        $updated = $this->repository->update($section, ['content' => $new]);
        SectionUpdated::dispatch($updated, ['content']);

        return $updated;
    }

    /**
     * Extract a field's input slice from the raw request array.
     *
     * Handles the LinkField quirk (two sibling keys: {key}_url + {key}_label)
     * and the standard single-key case for all other field types.
     *
     * @return array<string, mixed>|null  null = field not present in input; skip.
     */
    private function collectFieldInput(FieldDefinition $field, array $input): null|array
    {
        if ($field instanceof LinkField) {
            $urlKey = "{$field->key}_url";
            $labelKey = "{$field->key}_label";

            if (! array_key_exists($urlKey, $input) && ! array_key_exists($labelKey, $input)) {
                return null;
            }

            $slice = [];
            if (array_key_exists($urlKey, $input)) {
                $slice[$urlKey] = $input[$urlKey];
            }
            if (array_key_exists($labelKey, $input)) {
                $slice[$labelKey] = $input[$labelKey];
            }

            return $slice;
        }

        if (! array_key_exists($field->key, $input)) {
            return null;
        }

        return [$field->key => $input[$field->key]];
    }

    /**
     * Merge incoming _visibility patch with existing state, keeping only keys
     * for registered fields.
     *
     * @param  array<string, bool>  $current
     * @param  array<string, mixed>  $incoming
     * @param  array<int, string>    $allowedKeys
     * @return array<string, bool>
     */
    private function mergeVisibility(array $current, array $incoming, array $allowedKeys): array
    {
        foreach ($incoming as $key => $visible) {
            if (in_array($key, $allowedKeys, true)) {
                $current[$key] = (bool) $visible;
            }
        }

        return $current;
    }

    /**
     * Merge incoming _element_styles patch, filtering by both allowed field
     * keys and allowed CSS property whitelist.
     *
     * @param  array<string, array<string, string>>  $current
     * @param  array<string, mixed>                  $incoming
     * @param  array<int, string>                    $allowedFieldKeys
     * @return array<string, array<string, string>>
     */
    private function mergeElementStyles(array $current, array $incoming, array $allowedFieldKeys): array
    {
        foreach ($incoming as $fieldKey => $styles) {
            if (! in_array($fieldKey, $allowedFieldKeys, true)) {
                continue;
            }
            if (! is_array($styles)) {
                continue;
            }

            $sanitized = [];
            foreach ($styles as $prop => $value) {
                if (! in_array($prop, self::ALLOWED_ELEMENT_STYLE_PROPS, true)) {
                    continue;
                }
                if (is_scalar($value) && (string) $value !== '') {
                    $sanitized[$prop] = (string) $value;
                }
            }

            if ($sanitized === []) {
                unset($current[$fieldKey]);
            } else {
                $current[$fieldKey] = $sanitized;
            }
        }

        return $current;
    }
}
