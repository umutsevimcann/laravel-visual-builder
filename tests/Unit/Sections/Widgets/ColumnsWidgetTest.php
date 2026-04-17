<?php

declare(strict_types=1);

use Umutsevimcann\VisualBuilder\Domain\Fields\FieldDefinition;
use Umutsevimcann\VisualBuilder\Domain\Fields\SelectField;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\ColumnsWidget;

/*
 * ColumnsWidget is a nested container — the schema contract is the only
 * part that lives in the domain layer (render logic is in Blade).
 * These tests lock field identities and the defaults the admin UI
 * seeds on first insertion.
 */

it('has the stable key, label and view partial', function (): void {
    $w = new ColumnsWidget;
    expect($w->key())->toBe('columns')
        ->and($w->label())->toBe('Columns')
        ->and($w->viewPartial())->toBe('visual-builder::widgets.columns');
});

it('exposes count, gap and stack_on as SelectFields', function (): void {
    $w = new ColumnsWidget;
    $byKey = collect($w->fields())->keyBy(fn (FieldDefinition $f) => $f->key);

    expect($byKey)->toHaveKeys(['count', 'gap', 'stack_on'])
        ->and($byKey['count'])->toBeInstanceOf(SelectField::class)
        ->and($byKey['count']->options)->toHaveKeys(['one', 'two', 'three', 'four', 'five', 'six'])
        ->and($byKey['gap'])->toBeInstanceOf(SelectField::class)
        ->and($byKey['gap']->options)->toHaveKeys(['none', 'tight', 'small', 'medium', 'large', 'wide'])
        ->and($byKey['stack_on'])->toBeInstanceOf(SelectField::class)
        ->and($byKey['stack_on']->options)->toHaveKeys(['mobile', 'tablet', 'never']);
});

it('defaults to two columns, medium gap, mobile stack', function (): void {
    $dc = (new ColumnsWidget)->defaultContent();
    expect($dc['count'])->toBe('two')
        ->and($dc['gap'])->toBe('medium')
        ->and($dc['stack_on'])->toBe('mobile');
});

it('is a deletable multi-instance widget', function (): void {
    $w = new ColumnsWidget;
    expect($w->allowsMultipleInstances())->toBeTrue()
        ->and($w->isDeletable())->toBeTrue();
});
