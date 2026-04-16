<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    // Design tokens are persisted to a key-value settings table. The host
    // app owns that table — the package only reads/writes into it. We
    // create a minimal one on the testbench connection so save() works.
    Schema::create('settings', function (Blueprint $table): void {
        $table->id();
        $table->string('key')->unique();
        $table->text('value')->nullable();
        $table->timestamp('updated_at')->nullable();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('settings');
});

it('saves global design tokens and returns the normalized payload', function (): void {
    $response = $this->postJson('/visual-builder/design-tokens', [
        'colors' => [
            ['id' => 'primary', 'label' => 'Primary', 'value' => '#2563eb'],
            ['id' => 'text', 'label' => 'Text', 'value' => '#1f2937'],
        ],
        'fonts' => [
            ['id' => 'heading', 'label' => 'Heading', 'family' => "'Roboto', sans-serif"],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('tokens.colors.0.value', '#2563eb')
        ->assertJsonPath('tokens.fonts.0.family', "'Roboto', sans-serif");
});

it('rejects color values that are not valid hex', function (): void {
    $this->postJson('/visual-builder/design-tokens', [
        'colors' => [
            ['id' => 'bad', 'label' => 'Bad', 'value' => 'javascript:alert(1)'],
        ],
    ])->assertStatus(422);
});

it('silently drops font families containing angle brackets during sanitize', function (): void {
    // Request validation allows any non-empty family (up to 150 chars) so
    // the FormRequest returns 200; the service-layer sanitize() strips
    // suspicious entries before persistence.
    $response = $this->postJson('/visual-builder/design-tokens', [
        'fonts' => [
            ['id' => 'ok', 'label' => 'OK', 'family' => 'Inter, sans-serif'],
            ['id' => 'evil', 'label' => 'Evil', 'family' => '<script>alert(1)</script>'],
        ],
    ]);

    $response->assertOk();

    $fonts = $response->json('tokens.fonts');
    expect($fonts)->toHaveCount(1)
        ->and($fonts[0]['id'])->toBe('ok');
});
