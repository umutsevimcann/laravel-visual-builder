<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Infrastructure\Sanitization;

use Umutsevimcann\VisualBuilder\Contracts\SanitizerInterface;

/**
 * Default SanitizerInterface — delegates to mews/purifier if available,
 * otherwise falls back to a conservative strip_tags allow-list.
 *
 * mews/purifier is a common Laravel package that wraps HTMLPurifier with
 * sensible defaults. It is NOT a hard dependency of this package — users
 * who already have it installed get high-quality sanitization automatically;
 * users who do not install it still get a safe (if blunt) fallback.
 *
 * To use mews/purifier in a host app:
 *     composer require mews/purifier
 *
 * Power users who need tighter control should implement SanitizerInterface
 * themselves and bind their implementation in AppServiceProvider — e.g. to
 * allow specific <iframe> sources for embeds.
 */
final class PurifierSanitizer implements SanitizerInterface
{
    /**
     * Conservative tag allow-list for the fallback path.
     *
     * Omits: script, iframe, object, embed, form, input, style, link, meta
     * Includes: typical body content and inline formatting.
     */
    private const FALLBACK_ALLOWED_TAGS = '<p><br><strong><em><u><s><code><pre><blockquote>'
        . '<h1><h2><h3><h4><h5><h6><ul><ol><li><a><img><hr><span><div>'
        . '<table><thead><tbody><tr><th><td><mark><small><sub><sup>';

    public function purify(string $html): string
    {
        if ($html === '') {
            return '';
        }

        // Prefer mews/purifier if it's installed in the host app.
        if (function_exists('clean')) {
            /** @var string $cleaned */
            $cleaned = clean($html);

            return $cleaned;
        }

        // Fallback: strip disallowed tags + scrub javascript: URIs.
        $stripped = strip_tags($html, self::FALLBACK_ALLOWED_TAGS);

        return preg_replace(
            '#(href|src)\s*=\s*(["\'])\s*(javascript|vbscript|data)\s*:#i',
            '$1=$2#blocked:',
            $stripped,
        ) ?? '';
    }
}
