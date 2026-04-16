<?php

declare(strict_types=1);

use Umutsevimcann\VisualBuilder\Domain\Fields\ToggleField;

it('coerces truthy and falsy inputs into strict booleans', function (mixed $input, bool $expected): void {
    $field = new ToggleField('show_badge', 'Show Badge');

    expect($field->sanitize($input))->toBe($expected);
})->with([
    'string "1"' => ['1', true],
    'string "0"' => ['0', false],
    'string "true"' => ['true', true],
    'int 1' => [1, true],
    'int 0' => [0, false],
    'real true' => [true, true],
    'real false' => [false, false],
    'null' => [null, false],
    'empty string' => ['', false],
]);

it('is not translatable and not toggleable — the value IS the toggle', function (): void {
    $field = new ToggleField('enabled', 'Enabled');

    expect($field->translatable)->toBeFalse()
        ->and($field->toggleable)->toBeFalse();
});

it('exposes its defaultValue via default()', function (): void {
    $on = new ToggleField('a', 'A', defaultValue: true);
    $off = new ToggleField('b', 'B', defaultValue: false);

    expect($on->default())->toBeTrue()
        ->and($off->default())->toBeFalse();
});
