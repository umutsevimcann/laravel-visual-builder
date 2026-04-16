<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Contracts;

/**
 * Contract for HTML sanitization used when persisting rich-text content.
 *
 * HtmlField values are passed through this sanitizer before being stored
 * in the database. Implementations should strip dangerous tags, attributes,
 * and protocols (script, onclick, javascript:) while preserving safe
 * formatting markup.
 *
 * Default implementation delegates to mews/purifier. Users can swap in
 * HTMLPurifier directly, DOMPurify-PHP, or any custom filter by binding
 * their implementation in a service provider.
 */
interface SanitizerInterface
{
    /**
     * Sanitize a potentially untrusted HTML string and return a safe version.
     *
     * Implementations MUST:
     *  - Remove <script>, <iframe>, <object>, <embed>, and similar tags.
     *  - Remove all event handler attributes (onclick, onload, etc.).
     *  - Strip javascript:, data:, and vbscript: URI schemes.
     *
     * Implementations MAY preserve:
     *  - Semantic tags (p, h1-h6, ul/ol/li, blockquote, code, pre).
     *  - Inline formatting (strong, em, u, s, mark).
     *  - Links with http(s):// and mailto: schemes.
     *  - Images with http(s):// and relative paths.
     *
     * Empty and non-string inputs should return an empty string, not null.
     *
     * @param  string  $html  Untrusted HTML input.
     * @return string         Sanitized HTML safe for rendering.
     */
    public function purify(string $html): string;
}
