<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Actions;

use Umutsevimcann\VisualBuilder\Contracts\BuilderRepositoryInterface;
use Umutsevimcann\VisualBuilder\Domain\Events\SectionUpdated;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;
use Umutsevimcann\VisualBuilder\Domain\Services\BreakpointStyleResolver;

/**
 * Update the section-level style override JSON.
 *
 * Unlike per-field element styles (handled by UpdateSectionContent), these
 * are properties that apply to the WHOLE section wrapper — background, gap,
 * padding-y, alignment, entrance animation, etc.
 *
 * Whitelist enforced in the Action:
 *  - color/spacing keys: bg_color, text_color, padding_y, alignment,
 *    padding_top/right/bottom/left, margin_top/right/bottom/left
 *  - motion keys: animation, animation_delay
 *
 * Value shapes accepted per key:
 *  - Scalar (string|int|bool) — stored as a string, applies to every
 *    breakpoint (legacy shape, preserved verbatim for compatibility).
 *  - Per-breakpoint object — associative array keyed by `desktop`,
 *    `tablet`, `mobile`. Only known breakpoint slots with scalar leaves
 *    are kept; any other keys or shapes are dropped on sanitize. An
 *    object that ends up empty after filtering is removed entirely.
 *
 * Empty strings and nulls are removed; when no known keys remain, the
 * section's style column is set to NULL (saves a JSON row in the DB).
 */
final class UpdateSectionStyle
{
    private const ALLOWED_KEYS = [
        'bg_color', 'text_color', 'padding_y', 'alignment',
        'animation', 'animation_delay',
        'padding_top', 'padding_right', 'padding_bottom', 'padding_left',
        'margin_top', 'margin_right', 'margin_bottom', 'margin_left',
    ];

    public function __construct(
        private readonly BuilderRepositoryInterface $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $style  Raw style payload from the request.
     */
    public function execute(BuilderSection $section, array $style): BuilderSection
    {
        $sanitized = [];

        foreach (self::ALLOWED_KEYS as $key) {
            if (! array_key_exists($key, $style)) {
                continue;
            }
            $value = $this->sanitizeValue($style[$key]);
            if ($value === null) {
                continue;
            }
            $sanitized[$key] = $value;
        }

        $updated = $this->repository->update(
            $section,
            ['style' => $sanitized === [] ? null : $sanitized],
        );
        SectionUpdated::dispatch($updated, ['style']);

        return $updated;
    }

    /**
     * Normalize one property value to either a scalar string (legacy)
     * or a per-breakpoint object of scalar strings. Returns null when
     * the value is empty / unparseable so callers omit the key entirely.
     *
     * @return string|array<string, string>|null
     */
    private function sanitizeValue(mixed $value): null|string|array
    {
        if (is_scalar($value)) {
            $str = (string) $value;

            return $str === '' ? null : $str;
        }
        if (! is_array($value)) {
            return null;
        }

        $out = [];
        foreach (BreakpointStyleResolver::BREAKPOINTS as $bp) {
            if (! array_key_exists($bp, $value)) {
                continue;
            }
            $leaf = $value[$bp];
            if (! is_scalar($leaf)) {
                continue;
            }
            $leafStr = (string) $leaf;
            if ($leafStr === '') {
                continue;
            }
            $out[$bp] = $leafStr;
        }

        return $out === [] ? null : $out;
    }
}
