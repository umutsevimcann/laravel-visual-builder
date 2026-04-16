<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Actions;

use Umutsevimcann\VisualBuilder\Contracts\BuilderRepositoryInterface;
use Umutsevimcann\VisualBuilder\Domain\Events\SectionUpdated;
use Umutsevimcann\VisualBuilder\Domain\Models\BuilderSection;

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
            if (! isset($style[$key])) {
                continue;
            }
            $value = $style[$key];
            if (! is_scalar($value) || (string) $value === '') {
                continue;
            }
            $sanitized[$key] = (string) $value;
        }

        $updated = $this->repository->update(
            $section,
            ['style' => $sanitized === [] ? null : $sanitized],
        );
        SectionUpdated::dispatch($updated, ['style']);

        return $updated;
    }
}
