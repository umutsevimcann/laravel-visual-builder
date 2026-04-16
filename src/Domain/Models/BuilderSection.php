<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeInterface;
use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeRegistry;

/**
 * A single section of a page built with Visual Builder.
 *
 * Polymorphic by design — a section belongs to a `builder` parent which may
 * be any Eloquent model the host app chooses to make buildable (Page, Post,
 * Product, Category, etc.). The parent model adds the HasVisualBuilder
 * trait to expose its sections via `$model->builderSections`.
 *
 * Translatable field values live INSIDE the `content` JSON as per-field
 * locale maps:
 *
 *     [
 *       "headline" => ["en" => "Welcome", "de" => "Willkommen"],
 *       "cta_url"  => "/contact",
 *     ]
 *
 * Two reserved meta keys inside content JSON:
 *  - "_visibility":       per-field show/hide map (eye icon state)
 *  - "_element_styles":   per-field inline style overrides (color, font, etc.)
 *
 * @property int $id
 * @property int $builder_id
 * @property string $builder_type
 * @property string $type
 * @property string $instance_key
 * @property bool $is_published
 * @property int $sort_order
 * @property array|null $content
 * @property array|null $style
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BuilderSection extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'builder_id',
        'builder_type',
        'type',
        'instance_key',
        'is_published',
        'sort_order',
        'content',
        'style',
        'starts_at',
        'ends_at',
    ];

    public function getTable(): string
    {
        return (string) config('visual-builder.tables.sections', 'builder_sections');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'sort_order' => 'integer',
            'content' => 'array',
            'style' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Owner relationship — the Page/Post/etc. this section belongs to.
     */
    public function builder(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Whether the section is currently visible on the public frontend.
     *
     * Visibility = is_published flag AND (now is within scheduling window
     * OR no window defined).
     */
    public function isVisibleNow(null|Carbon $now = null): bool
    {
        if (! $this->is_published) {
            return false;
        }

        $now ??= Carbon::now();

        if ($this->starts_at !== null && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at !== null && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    /**
     * Read a content field value, respecting locale fallback for translatable fields.
     *
     * Behavior:
     *  - Missing key → returns null.
     *  - Plain scalar → returns as-is.
     *  - Locale map → returns the requested locale, falling back to the
     *    configured fallback locale, then an empty string.
     *
     * @param  string  $key  Field key defined by a SectionType field.
     * @param  string|null  $locale  Specific locale; null uses the current app locale.
     * @return mixed Scalar for non-translatable; string for translatable.
     */
    public function contentField(string $key, null|string $locale = null): mixed
    {
        $content = $this->content ?? [];
        $value = $content[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (is_array($value) && $this->looksLikeLocaleMap($value)) {
            $locale ??= app()->getLocale();
            $fallback = (string) config('app.fallback_locale', 'en');

            return $value[$locale] ?? $value[$fallback] ?? '';
        }

        return $value;
    }

    /**
     * Read the full locale map for a translatable content field.
     *
     * Returns an empty array when the field does not exist or is not stored
     * as a locale map. Admin forms that edit all locales at once use this
     * method to populate their per-locale inputs.
     *
     * @return array<string, string>
     */
    public function contentTranslations(string $key): array
    {
        $content = $this->content ?? [];
        $value = $content[$key] ?? [];

        if (! is_array($value) || ! $this->looksLikeLocaleMap($value)) {
            return [];
        }

        return $value;
    }

    /**
     * Whether a specific field is visible on the frontend.
     *
     * The admin UI maintains a per-field eye-icon state in the content
     * JSON under the reserved `_visibility` key. Missing/legacy keys are
     * treated as visible for backward compatibility.
     */
    public function isFieldVisible(string $key): bool
    {
        $visibility = $this->content['_visibility'] ?? [];

        if (! is_array($visibility) || ! array_key_exists($key, $visibility)) {
            return true;
        }

        return (bool) $visibility[$key];
    }

    /**
     * Merge per-field style overrides with the section type's default style.
     *
     * User-provided values win; empty strings and nulls fall through to the
     * default. Returns a plain array suitable for converting to inline CSS.
     *
     * @return array<string, mixed>
     */
    public function resolvedStyle(): array
    {
        $override = $this->style ?? [];

        $registry = app(SectionTypeRegistry::class);
        $type = $registry->find($this->type);
        $default = $type instanceof SectionTypeInterface ? $type->defaultStyle() : [];

        return array_merge(
            $default,
            array_filter($override, static fn ($v) => $v !== null && $v !== ''),
        );
    }

    /**
     * Heuristic: determine whether an array value is a locale map.
     *
     * Treats it as a locale map only if ALL keys look like locale codes
     * (two-letter language with optional region: en, de, tr, en_US, zh_CN).
     * Rejects numeric-indexed arrays, mixed keys, and empty arrays.
     *
     * @param  array<int|string, mixed>  $arr
     */
    private function looksLikeLocaleMap(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }

        foreach (array_keys($arr) as $key) {
            if (! is_string($key) || preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $key) !== 1) {
                return false;
            }
        }

        return true;
    }
}
