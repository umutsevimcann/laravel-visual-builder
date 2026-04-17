<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;
use Umutsevimcann\VisualBuilder\Domain\Services\BreakpointStyleResolver;

/**
 * Static helper surface for common view-side operations.
 *
 * Kept intentionally tiny — every method is a one-liner that resolves a
 * service from the container and delegates. The indirection exists so
 * host app templates can call `VisualBuilder::sectionStylesTag($section)`
 * without ever injecting a service, while the underlying resolver stays
 * a normal DI-friendly class that is easy to test in isolation.
 *
 * Not a Laravel facade — a plain static class with explicit container
 * resolution. Simpler than registering a facade, and the test suite
 * does not need facade mocking to exercise consumers.
 */
final class VisualBuilder
{
    /**
     * Build a <style> block scoped to one section containing its resolved
     * CSS declarations plus any @media queries required by per-breakpoint
     * overrides. Returns an empty HtmlString when the section has no
     * non-empty style values so template authors can safely embed the
     * result without generating an empty `<style></style>` pair.
     *
     * Selector format: `[data-vb-section-id="{id}"]` — stable across the
     * editor iframe (where sections are already wrapped with this data
     * attribute by the iframe injector) and production (where host apps
     * are expected to mirror the same wrapper attribute).
     *
     * @param  BuilderSection|array{id: int|string, style?: array<string, mixed>}  $section
     */
    public static function sectionStylesTag(BuilderSection|array $section): Htmlable
    {
        [$id, $style] = self::extractIdAndStyle($section);

        if ($style === []) {
            return new HtmlString('');
        }

        /** @var BreakpointStyleResolver $resolver */
        $resolver = app(BreakpointStyleResolver::class);
        $selector = sprintf('[data-vb-section-id="%s"]', $id);
        $css = $resolver->toCss($style, $selector);

        if ($css === '') {
            return new HtmlString('');
        }

        // The CSS produced by toCss() contains only ASCII selectors and
        // values previously sanitized by UpdateSectionStyle — no user
        // HTML enters this path. Escaping would double-encode the @media
        // symbol so it is omitted intentionally.
        return new HtmlString(sprintf(
            '<style data-vb-section-styles="%s">%s</style>',
            (string) $id,
            $css,
        ));
    }

    /**
     * Normalize the two acceptable input shapes to a pair of scalars.
     *
     * @param  BuilderSection|array{id: int|string, style?: array<string, mixed>}  $section
     * @return array{0: int|string, 1: array<string, mixed>}
     */
    private static function extractIdAndStyle(BuilderSection|array $section): array
    {
        if ($section instanceof BuilderSection) {
            $style = is_array($section->style) ? $section->style : [];

            return [(int) $section->id, $style];
        }

        $id = $section['id'] ?? 0;
        $style = $section['style'] ?? [];
        if (! is_array($style)) {
            $style = [];
        }

        return [$id, $style];
    }
}
