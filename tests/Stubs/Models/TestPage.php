<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Model;
use Umutsevimcann\VisualBuilder\Support\Concerns\HasVisualBuilder;

/**
 * Buildable stub model used by controller feature tests.
 *
 * Mirrors what a host-app page model would look like: an Eloquent model
 * with the HasVisualBuilder trait, backed by a `test_pages` table defined
 * in the feature-test migration stub.
 */
final class TestPage extends Model
{
    use HasVisualBuilder;

    protected $table = 'test_pages';

    protected $guarded = [];

    public $timestamps = false;
}
