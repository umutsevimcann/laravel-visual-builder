<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Sections\Widgets;

use Umutsevimcann\VisualBuilder\Domain\Fields\SelectField;
use Umutsevimcann\VisualBuilder\Domain\Fields\TextField;
use Umutsevimcann\VisualBuilder\Domain\Fields\ToggleField;

/**
 * Video widget — embeds either a direct video URL (served via <video>)
 * or an oEmbed-compatible provider (YouTube, Vimeo, etc. via <iframe>).
 *
 * Fields:
 *   - url          (text, required) — direct .mp4 URL OR oEmbed provider link.
 *   - provider     (select)         — 'file' | 'youtube' | 'vimeo'. Drives the
 *                                     render pattern (native player vs iframe).
 *   - autoplay     (toggle)         — autoplay on viewport entry (muted).
 *   - loop         (toggle)         — loop playback when done.
 *   - controls     (toggle)         — show native controls overlay.
 *   - aspect_ratio (select)         — '16/9' | '4/3' | '1/1' | '21/9'.
 *
 * The render partial picks `<video>` vs `<iframe>` from `provider` and
 * appends the query-string flags YouTube/Vimeo expect for autoplay / loop.
 */
final class VideoWidget extends AbstractAtomicWidget
{
    public function key(): string
    {
        return 'video';
    }

    public function category(): string
    {
        return 'media';
    }

    public function label(): string
    {
        return 'Video';
    }

    public function description(): string
    {
        return 'Embed a self-hosted .mp4 or a YouTube / Vimeo video.';
    }

    public function icon(): string
    {
        return 'fa-solid fa-film';
    }

    public function fields(): array
    {
        return [
            new TextField(
                key: 'url',
                label: 'Video URL',
                help: 'Direct .mp4 link or a YouTube / Vimeo page URL.',
                required: true,
            ),
            new SelectField(
                key: 'provider',
                label: 'Provider',
                options: [
                    'file' => 'Direct file (.mp4, .webm)',
                    'youtube' => 'YouTube',
                    'vimeo' => 'Vimeo',
                ],
                help: 'How to embed the URL. File → native <video>; others → <iframe>.',
                defaultValue: 'file',
            ),
            new SelectField(
                key: 'aspect_ratio',
                label: 'Aspect ratio',
                options: [
                    '16/9' => '16:9 — widescreen (default)',
                    '4/3' => '4:3 — classic TV',
                    '1/1' => '1:1 — square',
                    '21/9' => '21:9 — ultrawide',
                ],
                defaultValue: '16/9',
            ),
            new ToggleField(
                key: 'autoplay',
                label: 'Autoplay (muted)',
            ),
            new ToggleField(
                key: 'loop',
                label: 'Loop playback',
            ),
            new ToggleField(
                key: 'controls',
                label: 'Show player controls',
            ),
        ];
    }

    public function defaultContent(): array
    {
        return [
            'url' => '',
            'provider' => 'file',
            'aspect_ratio' => '16/9',
            'autoplay' => false,
            'loop' => false,
            'controls' => true,
        ];
    }

    public function defaultStyle(): array
    {
        return [
            'alignment' => 'center',
        ];
    }
}
