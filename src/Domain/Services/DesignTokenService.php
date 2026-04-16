<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\ConnectionInterface;

/**
 * Global design tokens service — site-wide colors + fonts as CSS variables.
 *
 * Tokens are stored in the generic settings table (defaults) or in a custom
 * location chosen by the host app. This service abstracts over a key-value
 * settings table and exposes:
 *
 *   all()              — read tokens (cached, TTL from config)
 *   save($tokens)      — persist + invalidate cache
 *   toCssVariables()   — render `:root { --vb-color-primary: ...; ... }`
 *
 * The configured settings table + key column is read from config so the
 * host app can point to whichever settings store it uses (settings, options,
 * kv_store, etc.) as long as it has a `key` + `value` column.
 *
 * Security: input tokens pass through a whitelist + hex color regex + font
 * family blacklist (no angle brackets or braces) before persistence. No CSS
 * injection surface even if the persisted value is later inlined.
 */
final class DesignTokenService
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly CacheRepository $cache,
        private readonly ConfigRepository $config,
    ) {}

    /**
     * Read all tokens (colors + fonts), cached.
     *
     * @return array{colors: array<int, array{id: string, label: string, value: string}>, fonts: array<int, array{id: string, label: string, family: string}>}
     */
    public function all(): array
    {
        $ttl = (int) $this->config->get('visual-builder.design_tokens.cache_ttl', 600);

        return $this->cache->remember($this->cacheKey(), $ttl, function (): array {
            $row = $this->db->table($this->settingsTable())
                ->where('key', $this->settingsKey())
                ->value('value');

            if (! is_string($row) || $row === '') {
                return $this->defaults();
            }

            $decoded = json_decode($row, true);

            return is_array($decoded) ? $this->normalize($decoded) : $this->defaults();
        });
    }

    /**
     * Persist the given tokens after validation + sanitization.
     * Invalidates the tokens cache on success.
     *
     * @param  array{colors?: array<int, array{id?: string, label?: string, value?: string}>, fonts?: array<int, array{id?: string, label?: string, family?: string}>}  $tokens
     */
    public function save(array $tokens): void
    {
        $clean = $this->sanitize($tokens);

        $this->db->table($this->settingsTable())->updateOrInsert(
            ['key' => $this->settingsKey()],
            [
                'value' => json_encode($clean, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ],
        );

        $this->cache->forget($this->cacheKey());
    }

    /**
     * Render tokens as a CSS :root declaration ready to inline in the page head.
     *
     * Example output:
     *
     *     :root {
     *         --vb-color-primary: #2563eb;
     *         --vb-color-text: #1f2937;
     *         --vb-font-heading: 'Roboto', sans-serif;
     *     }
     */
    public function toCssVariables(): string
    {
        $tokens = $this->all();
        $lines = [];

        foreach ($tokens['colors'] as $color) {
            $lines[] = sprintf('    --vb-color-%s: %s;', e($color['id']), e($color['value']));
        }
        foreach ($tokens['fonts'] as $font) {
            $lines[] = sprintf('    --vb-font-%s: %s;', e($font['id']), e($font['family']));
        }

        if ($lines === []) {
            return '';
        }

        return ":root {\n".implode("\n", $lines)."\n}";
    }

    /**
     * Whitelist + sanitize an incoming token payload.
     *
     * Security:
     *  - color.value: must match /^#[0-9a-fA-F]{3,8}$/ (rejects any CSS injection)
     *  - font.family: must not contain '<', '>', '{', '}' (blocks style/script)
     *  - All id/label fields stripped of tags
     */
    private function sanitize(array $tokens): array
    {
        $clean = ['colors' => [], 'fonts' => []];

        foreach ($tokens['colors'] ?? [] as $color) {
            if (! is_array($color)) {
                continue;
            }
            if (! isset($color['id'], $color['label'], $color['value'])) {
                continue;
            }
            if (preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $color['value']) !== 1) {
                continue;
            }
            $clean['colors'][] = [
                'id' => $this->slugify((string) $color['id']),
                'label' => strip_tags((string) $color['label']),
                'value' => (string) $color['value'],
            ];
        }

        foreach ($tokens['fonts'] ?? [] as $font) {
            if (! is_array($font)) {
                continue;
            }
            if (! isset($font['id'], $font['label'], $font['family'])) {
                continue;
            }
            $family = (string) $font['family'];
            if (preg_match('/[<>{}]/', $family) === 1) {
                continue;
            }
            $clean['fonts'][] = [
                'id' => $this->slugify((string) $font['id']),
                'label' => strip_tags((string) $font['label']),
                'family' => $family,
            ];
        }

        return $clean;
    }

    /**
     * Normalize a decoded JSON payload from storage to the public shape.
     */
    private function normalize(array $data): array
    {
        return [
            'colors' => is_array($data['colors'] ?? null) ? array_values($data['colors']) : [],
            'fonts' => is_array($data['fonts'] ?? null) ? array_values($data['fonts']) : [],
        ];
    }

    /**
     * Default tokens used when the settings row is missing.
     * Configurable via config/visual-builder.php → design_tokens.defaults.
     */
    private function defaults(): array
    {
        $defaults = $this->config->get('visual-builder.design_tokens.defaults', []);

        return is_array($defaults) ? $this->normalize($defaults) : ['colors' => [], 'fonts' => []];
    }

    private function slugify(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9_-]/i', '', $value);
    }

    private function cacheKey(): string
    {
        return 'visual-builder.design-tokens';
    }

    private function settingsKey(): string
    {
        return (string) $this->config->get(
            'visual-builder.design_tokens.settings_key',
            'visual_builder_design_tokens',
        );
    }

    /**
     * Table name used for key-value storage.
     * Users with a non-standard settings table override this via config.
     */
    private function settingsTable(): string
    {
        return (string) $this->config->get('visual-builder.design_tokens.settings_table', 'settings');
    }
}
