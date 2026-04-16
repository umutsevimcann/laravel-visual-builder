<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Umutsevimcann\VisualBuilder\Domain\DTOs\BuilderLayoutData;

it('constructs with null fields when no data is provided', function (): void {
    $dto = new BuilderLayoutData(orderedIds: null, sections: null);

    expect($dto->orderedIds)->toBeNull()
        ->and($dto->sections)->toBeNull();
});

it('casts ordered_ids to integers', function (): void {
    $dto = BuilderLayoutData::fromRequest(Request::create('/', 'POST', [
        'ordered_ids' => ['3', '1', '2'],
    ]));

    expect($dto->orderedIds)->toBe([3, 1, 2]);
});

it('passes sections payload through unchanged', function (): void {
    $payload = [
        5 => ['content' => ['headline' => ['en' => 'Hello']]],
        7 => ['is_published' => false],
    ];
    $dto = BuilderLayoutData::fromRequest(Request::create('/', 'POST', ['sections' => $payload]));

    expect($dto->sections)->toBe($payload);
});

it('defaults both fields to null when request is empty', function (): void {
    $dto = BuilderLayoutData::fromRequest(Request::create('/', 'POST'));

    expect($dto->orderedIds)->toBeNull()
        ->and($dto->sections)->toBeNull();
});
