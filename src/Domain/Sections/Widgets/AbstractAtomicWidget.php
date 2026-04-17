<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Sections\Widgets;

use Umutsevimcann\VisualBuilder\Domain\Sections\SectionTypeInterface;

/**
 * Shared base for the package's built-in atomic widgets.
 *
 * "Atomic" in the Elementor / WordPress Gutenberg sense: small, single-
 * purpose building blocks (Heading, Paragraph, Button, etc.) that the
 * content editor composes into larger layouts rather than picking a
 * pre-assembled "Hero" or "About Box" section.
 *
 * Contract:
 *   Concrete subclasses declare their own key(), label(), description(),
 *   icon(), fields(), defaultContent() and defaultStyle(). Everything
 *   else is handled here with widget-sensible defaults:
 *
 *     - allowsMultipleInstances()  → true   (users drop many per page)
 *     - isDeletable()              → true   (no widget is mandatory)
 *     - previewImage()             → null   (rely on the icon)
 *     - viewPartial()              → `visual-builder::widgets.{key}`
 *
 * Registration is OPT-IN via `visual-builder.widgets.enabled` in the
 * host config. Host apps that do not want atomic widgets in their
 * block palette leave the flag false (the default) and nothing in the
 * package ships registered section types on their behalf.
 */
abstract class AbstractAtomicWidget implements SectionTypeInterface
{
    /**
     * Atomic widgets are never singletons — the user typically drops
     * many Headings or Buttons on a single page. Override in a subclass
     * if a specific widget really must be one-per-target.
     */
    public function allowsMultipleInstances(): bool
    {
        return true;
    }

    /**
     * Atomic widgets are always deletable. They carry no data the host
     * cannot recreate by dragging a fresh instance.
     */
    public function isDeletable(): bool
    {
        return true;
    }

    /**
     * Atomic widgets rely on their icon in the block palette and do not
     * ship per-widget preview thumbnails. A concrete widget can override
     * this to return a URL or storage path when a custom preview helps.
     */
    public function previewImage(): null|string
    {
        return null;
    }

    /**
     * Convention: `visual-builder::widgets.{key}`.
     *
     * The view namespace is fixed (`visual-builder` is the package's
     * publish-safe namespace; published views override package views),
     * and `key()` must be snake_case which doubles as a filesystem-safe
     * Blade view name.
     */
    public function viewPartial(): string
    {
        return 'visual-builder::widgets.'.$this->key();
    }
}
