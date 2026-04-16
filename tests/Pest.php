<?php

declare(strict_types=1);

/**
 * Pest configuration — maps test directories to base TestCases.
 *
 * Unit tests (tests/Unit) run without a Laravel kernel — plain PHP,
 * no container, no DB. Feature tests (tests/Feature) boot the package
 * via Orchestra Testbench through our custom TestCase.
 */

use Umutsevimcann\VisualBuilder\Tests\TestCase;

uses(TestCase::class)->in('Feature');
