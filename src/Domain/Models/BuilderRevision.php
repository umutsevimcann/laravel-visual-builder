<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Server-side snapshot of a buildable target's sections at a moment in time.
 *
 * Optional persistence layer for revisions — enabled via config:
 *     'revisions' => ['server_enabled' => true]
 *
 * When enabled, a revision row is written every time an editor presses Save.
 * Users can browse timestamps and restore any previous version from the
 * admin UI.
 *
 * When disabled (default), the UI still offers local revisions in the
 * editor's browser via localStorage — fast, zero database footprint, but
 * lost when cache is cleared or on another device.
 *
 * @property int $id
 * @property int $builder_id
 * @property string $builder_type
 * @property int|null $user_id
 * @property string|null $label
 * @property array $snapshot
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BuilderRevision extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'builder_id',
        'builder_type',
        'user_id',
        'label',
        'snapshot',
    ];

    public function getTable(): string
    {
        return (string) config('visual-builder.tables.revisions', 'builder_revisions');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
        ];
    }

    /**
     * Owner relationship — same morph target as BuilderSection::builder().
     */
    public function builder(): MorphTo
    {
        return $this->morphTo();
    }
}
