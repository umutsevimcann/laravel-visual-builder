<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Saved section bundle — the unit the template library shelves.
 *
 * A template is a portable snapshot of one-or-more sections
 * (content + style + publish flag, plus children when a parent is
 * a container like Columns) serialized into a single `payload` JSON
 * column. Templates are NOT bound to any specific target: a template
 * authored on a Page can be applied to a Product, Post, or back to a
 * Page — the Apply action rehydrates each entry through the
 * SectionTypeRegistry so only registered types round-trip.
 *
 * Type discriminator:
 *  - 'section'  (v0.6.0)  — array of section snapshots, applied by
 *                           dropping onto a target as new rows.
 *  - 'page'     (future)  — includes design tokens / page-level meta.
 *  - 'kit'      (future)  — multiple templates bundled as a kit.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $type
 * @property string|null $thumbnail_path
 * @property array<int, mixed> $payload
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static self create(array $attributes = [])
 * @method static self findOrFail(mixed $id)
 * @method static \Illuminate\Database\Eloquent\Builder query()
 */
class BuilderTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'thumbnail_path',
        'payload',
    ];

    public function getTable(): string
    {
        return (string) config('visual-builder.tables.templates', 'builder_templates');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
