<?php

declare(strict_types=1);

use Umutsevimcann\VisualBuilder\Domain\Fields\TextField;

it('declares its admin input type as "text"', function (): void {
    $field = new TextField('headline', 'Headline');

    expect($field->adminInputType())->toBe('text');
});

it('preserves constructor-injected metadata as readonly properties', function (): void {
    $field = new TextField(
        key: 'subtitle',
        label: 'Subtitle',
        help: 'Short intro',
        required: true,
        translatable: true,
        placeholder: 'e.g. Welcome to our site',
        maxLength: 200,
        toggleable: false,
    );

    expect($field->key)->toBe('subtitle')
        ->and($field->label)->toBe('Subtitle')
        ->and($field->help)->toBe('Short intro')
        ->and($field->required)->toBeTrue()
        ->and($field->translatable)->toBeTrue()
        ->and($field->placeholder)->toBe('e.g. Welcome to our site')
        ->and($field->maxLength)->toBe(200)
        ->and($field->toggleable)->toBeFalse();
});

it('generates scalar validation rules for non-translatable fields', function (): void {
    $field = new TextField('slug', 'Slug', required: true, maxLength: 60);

    $rules = $field->validationRules();

    expect($rules)->toHaveKey('slug')
        ->and($rules['slug'])->toBe(['required', 'string', 'max:60']);
});

it('does not mutate its own state via sanitize()', function (): void {
    $field = new TextField('title', 'Title', maxLength: 10);

    expect($field->sanitize('Hello world'))->toBe('Hello world')
        ->and($field->maxLength)->toBe(10);
});
