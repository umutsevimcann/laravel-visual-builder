<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Services;

use InvalidArgumentException;

/**
 * Resolves breakpoint-aware section styles to flat, device-specific values
 * and emits browser-ready CSS with @media queries.
 *
 * Storage shape:
 *   A style JSON may hold two shapes per property:
 *     - Scalar (string|int|null): applies to every breakpoint
 *         "bg_color" => "#ff0000"
 *     - Object (keyed by breakpoint): per-device override map, partial allowed
 *         "padding_y" => ["desktop" => "80px", "tablet" => "60px", "mobile" => "40px"]
 *         "alignment" => ["desktop" => "left", "mobile" => "center"]
 *
 * Inheritance rule (Elementor-style):
 *     mobile ← tablet ← desktop
 *
 *     If the user only fills in "desktop", tablet and mobile inherit that
 *     value. If they only fill in "tablet", mobile inherits from tablet,
 *     desktop falls back to tablet as well (tablet → desktop is less
 *     idiomatic but prevents "undefined" output when desktop is missing).
 *
 * Backwards compatibility:
 *   Legacy flat values keep working verbatim — resolve() returns the scalar
 *   for every breakpoint, toCss() emits a single rule with no @media
 *   wrappers. Users adopt the responsive shape by simply swapping a scalar
 *   for an object; no data migration required.
 *
 * Responsibility boundaries:
 *   - This service KNOWS: inheritance rules, CSS property mapping, media
 *     query thresholds, compound properties (padding_y → top + bottom).
 *   - This service does NOT KNOW: which style keys are responsive (any key
 *     with an object value is treated as responsive), storage, validation,
 *     or http concerns.
 */
final class BreakpointStyleResolver
{
    /**
     * Ordered list of supported breakpoints, desktop-first.
     *
     * @var list<string>
     */
    public const BREAKPOINTS = ['desktop', 'tablet', 'mobile'];

    /**
     * Inheritance chain per breakpoint: first hit wins.
     *
     * Reading mobile consults mobile → tablet → desktop. Tablet consults
     * tablet → desktop. Desktop consults desktop → tablet (rare fallback
     * for when a user set only tablet).
     *
     * @var array<string, list<string>>
     */
    private const INHERITANCE = [
        'desktop' => ['desktop', 'tablet', 'mobile'],
        'tablet' => ['tablet', 'desktop', 'mobile'],
        'mobile' => ['mobile', 'tablet', 'desktop'],
    ];

    /**
     * Map internal style keys to CSS property names / compound expansions.
     *
     * Flat keys map one-to-one; compound keys (padding_y) expand to
     * multiple CSS declarations. Keys not listed here fall through to
     * kebab-case conversion (e.g. padding_top → padding-top).
     *
     * @var array<string, list<string>>
     */
    private const COMPOUND_KEYS = [
        'padding_y' => ['padding-top', 'padding-bottom'],
        'padding_x' => ['padding-left', 'padding-right'],
        'margin_y' => ['margin-top', 'margin-bottom'],
        'margin_x' => ['margin-left', 'margin-right'],
    ];

    /**
     * Style keys whose values may be stored as breakpoint objects.
     *
     * Used by the editor UI to decide whether to show the breakpoint
     * switcher next to a field. Backend resolve/toCss accept objects on
     * any key regardless — this list is advisory, not enforced.
     *
     * @var list<string>
     */
    public const RESPONSIVE_KEYS = [
        'padding_y', 'padding_x',
        'padding_top', 'padding_right', 'padding_bottom', 'padding_left',
        'margin_top', 'margin_right', 'margin_bottom', 'margin_left',
        'alignment',
    ];

    /**
     * Style keys that are stored but NOT emitted as CSS declarations.
     *
     * `animation` and `animation_delay` are consumed by host templates as
     * CSS CLASS names (e.g. `vb-anim-fadeIn`, `vb-anim-delay-500`) not as
     * CSS `animation:` shorthand values. Emitting `animation: fadeIn` on
     * the wrapper would also cascade through descendant attribute
     * selectors and clobber any in-flight animation on editable children
     * (concrete incident: IFEX's `.fadeDownShort` on h1 was replaced by
     * a zero-duration fadeIn that left the element at opacity:0).
     *
     * @var list<string>
     */
    private const CSS_SKIPPED_KEYS = ['animation', 'animation_delay'];

    /**
     * Spacing keys where a bare numeric value gets `px` appended on
     * emission. Legacy seeded data stored values like `"40"` without a
     * unit; browsers reject `padding-top: 40` as invalid and fall back to
     * the default (0px), which silently loses the spacing override. We
     * auto-append `px` only for these keys — color / alignment / font
     * family values stay verbatim.
     *
     * @var list<string>
     */
    private const UNITLESS_PX_KEYS = [
        'padding_y', 'padding_x',
        'padding_top', 'padding_right', 'padding_bottom', 'padding_left',
        'margin_y', 'margin_x',
        'margin_top', 'margin_right', 'margin_bottom', 'margin_left',
    ];

    /**
     * Maximum viewport width (inclusive) at which the tablet breakpoint
     * applies. Default matches Bootstrap 5's `lg` boundary — 1023px.
     */
    private readonly int $tabletMaxPx;

    /**
     * Maximum viewport width (inclusive) at which the mobile breakpoint
     * applies. Default 767px matches Bootstrap 5's `md` boundary.
     */
    private readonly int $mobileMaxPx;

    public function __construct(int $tabletMaxPx = 1023, int $mobileMaxPx = 767)
    {
        if ($tabletMaxPx <= $mobileMaxPx) {
            throw new InvalidArgumentException(
                'tablet_max must be greater than mobile_max; got '
                ."tablet={$tabletMaxPx}, mobile={$mobileMaxPx}"
            );
        }
        $this->tabletMaxPx = $tabletMaxPx;
        $this->mobileMaxPx = $mobileMaxPx;
    }

    /**
     * Reduce a mixed style array to the flat values for one breakpoint.
     *
     * For scalar values, the same value is returned verbatim. For object
     * values, inheritance is walked per {@see self::INHERITANCE} until a
     * non-empty value is found.
     *
     * @param  array<string, mixed>  $style  Raw style JSON from BuilderSection.
     * @param  string  $breakpoint  One of 'desktop' | 'tablet' | 'mobile'.
     * @return array<string, string> Flat key → resolved CSS-compatible string.
     *
     * @throws InvalidArgumentException When an unknown breakpoint is requested.
     */
    public function resolve(array $style, string $breakpoint = 'desktop'): array
    {
        if (! in_array($breakpoint, self::BREAKPOINTS, true)) {
            throw new InvalidArgumentException(
                "Unknown breakpoint '{$breakpoint}'. Expected one of: "
                .implode(', ', self::BREAKPOINTS)
            );
        }

        $resolved = [];
        foreach ($style as $key => $value) {
            $flat = $this->resolveValue($value, $breakpoint);
            if ($flat === null || $flat === '') {
                continue;
            }
            $resolved[$key] = $flat;
        }

        return $resolved;
    }

    /**
     * Emit a CSS <style>-ready string with media queries for every
     * breakpoint that yields a resolved value different from desktop.
     *
     * Output shape (literal `at-media` stands for the `@` prefix — kept
     * escaped in this docblock so PHPStan does not treat the symbol as
     * a tag):
     *   .sel { prop: desktop-value; ... }
     *   at-media (max-width: 1023px) { .sel { prop: tablet-value; ... } }
     *   at-media (max-width: 767px)  { .sel { prop: mobile-value; ... } }
     *
     * Sections with no responsive overrides emit only the desktop block.
     * Empty input (no declarations at all) returns an empty string so
     * callers can safely `{!! $css !!}` without emitting an empty tag.
     *
     * @param  array<string, mixed>  $style  Raw style JSON.
     * @param  string  $selector  CSS selector to scope the rules to.
     */
    public function toCss(array $style, string $selector): string
    {
        $desktop = $this->resolve($style, 'desktop');
        $tablet = $this->resolve($style, 'tablet');
        $mobile = $this->resolve($style, 'mobile');

        $desktopCss = $this->declarationsFor($desktop);
        $blocks = [];

        if ($desktopCss !== '') {
            $blocks[] = sprintf('%s { %s }', $selector, $desktopCss);
        }

        // Only emit @media blocks for properties that actually DIFFER from
        // desktop at that breakpoint — duplicating identical rules bloats
        // the payload and makes cascade debugging harder.
        $tabletDiff = $this->diff($desktop, $tablet);
        if ($tabletDiff !== []) {
            $blocks[] = sprintf(
                '@media (max-width: %dpx) { %s { %s } }',
                $this->tabletMaxPx,
                $selector,
                $this->declarationsFor($tabletDiff),
            );
        }

        $mobileDiff = $this->diff($tablet !== [] ? $tablet : $desktop, $mobile);
        if ($mobileDiff !== []) {
            $blocks[] = sprintf(
                '@media (max-width: %dpx) { %s { %s } }',
                $this->mobileMaxPx,
                $selector,
                $this->declarationsFor($mobileDiff),
            );
        }

        return implode(' ', $blocks);
    }

    /**
     * Breakpoint thresholds expressed as an array for bootstrap payload.
     * The editor JS uses these numbers to mirror the server's idea of
     * tablet/mobile cutoffs when resolving live-preview styles.
     *
     * @return array{tablet_max: int, mobile_max: int}
     */
    public function thresholds(): array
    {
        return [
            'tablet_max' => $this->tabletMaxPx,
            'mobile_max' => $this->mobileMaxPx,
        ];
    }

    /**
     * Walk one value through the inheritance chain for the requested
     * breakpoint. Null/empty leaves at each step cause the walker to
     * continue — the first non-empty leaf wins.
     */
    private function resolveValue(mixed $value, string $breakpoint): null|string
    {
        if ($value === null) {
            return null;
        }
        if (is_scalar($value)) {
            $str = (string) $value;

            return $str === '' ? null : $str;
        }
        if (! is_array($value)) {
            return null; // Unsupported shape — reject quietly, do not fail render
        }

        foreach (self::INHERITANCE[$breakpoint] as $bp) {
            if (! array_key_exists($bp, $value)) {
                continue;
            }
            $leaf = $value[$bp];
            if ($leaf === null || $leaf === '') {
                continue;
            }
            if (is_scalar($leaf)) {
                return (string) $leaf;
            }
        }

        return null;
    }

    /**
     * Produce `prop: value !important; prop2: value2 !important;` string
     * from flat resolved keys. Expands compound keys and converts
     * snake_case to kebab-case.
     *
     * `!important` is applied to every declaration by design — host
     * section partials often carry legacy inline `style=` attributes
     * from pre-builder templates; without the bump, a stale inline
     * `padding-top: 40px` on the `<section>` would quietly defeat the
     * builder's desktop/tablet/mobile settings. This mirrors the editor
     * iframe inject script's behaviour so live preview and production
     * stay cascade-equivalent.
     *
     * @param  array<string, string>  $resolved
     */
    private function declarationsFor(array $resolved): string
    {
        $out = [];
        foreach ($resolved as $key => $value) {
            if (in_array($key, self::CSS_SKIPPED_KEYS, true)) {
                continue;
            }
            $cssValue = $this->cssValueFor($key, $value);
            foreach ($this->cssPropertiesFor($key) as $cssProp) {
                $out[] = sprintf('%s: %s !important', $cssProp, $cssValue);
            }
        }

        return implode('; ', $out);
    }

    /**
     * Normalize a resolved value before it hits the CSS declaration. The
     * only current transformation is appending `px` to bare numeric values
     * for spacing keys; everything else passes through verbatim. Keeping
     * the transformation localized means colour / text / font-family
     * values never get accidentally rewritten.
     */
    private function cssValueFor(string $key, string $value): string
    {
        if (in_array($key, self::UNITLESS_PX_KEYS, true) && is_numeric($value)) {
            return $value.'px';
        }

        return $value;
    }

    /**
     * Return the CSS property names an internal style key expands to.
     * Compound keys map to multiple (padding_y → top + bottom). Regular
     * keys are converted to kebab-case (padding_top → padding-top) and
     * returned as a one-element list.
     *
     * Special case: `alignment` → `text-align` because the key name
     * predates this service and we preserve storage compatibility.
     *
     * @return list<string>
     */
    private function cssPropertiesFor(string $key): array
    {
        if (isset(self::COMPOUND_KEYS[$key])) {
            return self::COMPOUND_KEYS[$key];
        }
        if ($key === 'alignment') {
            return ['text-align'];
        }
        if ($key === 'bg_color') {
            return ['background-color'];
        }
        if ($key === 'text_color') {
            return ['color'];
        }

        return [str_replace('_', '-', $key)];
    }

    /**
     * Return only entries in $override that differ from $base. Keys
     * missing from $override are omitted (they inherit from $base).
     *
     * @param  array<string, string>  $base
     * @param  array<string, string>  $override
     * @return array<string, string>
     */
    private function diff(array $base, array $override): array
    {
        $out = [];
        foreach ($override as $k => $v) {
            if (($base[$k] ?? null) !== $v) {
                $out[$k] = $v;
            }
        }

        return $out;
    }
}
