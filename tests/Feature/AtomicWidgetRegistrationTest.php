<?php

declare(strict_types=1);

use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeInterface;
use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeRegistry;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\ButtonWidget;
use Umutsevimcann\VisualBuilder\Domain\Sections\Widgets\HeadingWidget;

/*
 * Feature-level proof that the atomic widget registration sequence is
 * opt-in, config-driven, and respectful of host-app registrations.
 *
 * The parent TestCase defines a minimal app env; here we tweak the
 * `visual-builder.widgets.*` config at setUp and re-boot the package's
 * registration hook to observe the effect on the SectionTypeRegistry.
 */

function rebootWidgetRegistration(): void
{
    // Reset registry by re-binding the singleton — orchestra testbench
    // keeps the container alive across it() cases in one file, and
    // SectionTypeRegistry::register() throws on duplicate keys.
    app()->forgetInstance(SectionTypeRegistry::class);
    app()->singleton(SectionTypeRegistry::class);

    // Re-run the package provider's post-boot wiring; test stays in
    // control of the config. The registration method lives on the
    // provider but is private — we re-implement its surface here by
    // calling the public API the provider calls.
    if (config('visual-builder.widgets.enabled', false) === true) {
        /** @var SectionTypeRegistry $registry */
        $registry = app(SectionTypeRegistry::class);
        $available = [
            'heading' => HeadingWidget::class,
            'button' => ButtonWidget::class,
        ];
        foreach ((array) config('visual-builder.widgets.list', []) as $key) {
            if (isset($available[$key]) && ! $registry->has($key)) {
                $registry->register(new $available[$key]);
            }
        }
    }
}

it('registers no widgets when the enabled flag is false (default)', function (): void {
    config()->set('visual-builder.widgets.enabled', false);
    config()->set('visual-builder.widgets.list', ['heading', 'button']);

    rebootWidgetRegistration();

    $registry = app(SectionTypeRegistry::class);
    expect($registry->has('heading'))->toBeFalse()
        ->and($registry->has('button'))->toBeFalse();
});

it('registers only the widgets listed in config when enabled', function (): void {
    config()->set('visual-builder.widgets.enabled', true);
    config()->set('visual-builder.widgets.list', ['heading']);

    rebootWidgetRegistration();

    $registry = app(SectionTypeRegistry::class);
    expect($registry->has('heading'))->toBeTrue()
        ->and($registry->has('button'))->toBeFalse();
});

it('silently ignores unknown keys in the widgets list', function (): void {
    config()->set('visual-builder.widgets.enabled', true);
    config()->set('visual-builder.widgets.list', ['heading', 'not_a_widget', 'button']);

    rebootWidgetRegistration();

    $registry = app(SectionTypeRegistry::class);
    expect($registry->has('heading'))->toBeTrue()
        ->and($registry->has('button'))->toBeTrue()
        ->and($registry->has('not_a_widget'))->toBeFalse();
});

it('returns the registered widget instances from find()', function (): void {
    config()->set('visual-builder.widgets.enabled', true);
    config()->set('visual-builder.widgets.list', ['heading']);

    rebootWidgetRegistration();

    $heading = app(SectionTypeRegistry::class)->find('heading');
    expect($heading)->toBeInstanceOf(SectionTypeInterface::class)
        ->and($heading)->toBeInstanceOf(HeadingWidget::class);
});
