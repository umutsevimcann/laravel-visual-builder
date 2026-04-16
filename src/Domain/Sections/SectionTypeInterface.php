<?php

declare(strict_types=1);

namespace Umutsevimcann\VisualBuilder\Domain\Sections;

use Umutsevimcann\VisualBuilder\Domain\Fields\FieldDefinition;

/**
 * Contract for a registered section type.
 *
 * A section type is a blueprint: it declares what fields make up a section
 * of this kind, what its defaults are, which Blade partial renders it on
 * the frontend, and whether it behaves as a singleton or multi-instance
 * building block.
 *
 * Users implement this interface for each section they want in their builder
 * (Hero, About, FAQ, ContactForm, etc.) and register instances with
 * SectionTypeRegistry — usually in their AppServiceProvider::boot().
 *
 * Two lightweight example implementations ship with the package:
 *  - BlankContainerSection  (generic wrapper for free-form HTML content)
 *  - TwoColumnSection       (heading + image + text template)
 *
 * Design intent: the package provides the framework, user apps provide the
 * schema. No section type definitions are hardcoded in the package core.
 */
interface SectionTypeInterface
{
    /**
     * Stable type key persisted in the database. Snake_case, URL-safe.
     *
     * This value appears as `BuilderSection::type` in the DB and in admin
     * URLs — once chosen, renaming requires a data migration. Keep it short
     * and canonical (e.g. 'hero', 'testimonials', 'pricing_table').
     */
    public function key(): string;

    /**
     * Human-readable display name shown in the admin UI.
     * Example: "Hero Banner", "Customer Testimonials".
     */
    public function label(): string;

    /**
     * One-line description shown in the block palette.
     * Used to help content editors decide which section to add.
     */
    public function description(): string;

    /**
     * Font Awesome icon class used in the block palette and navigator.
     * Example: "fa-solid fa-star".
     */
    public function icon(): string;

    /**
     * Optional preview thumbnail asset path (relative to public/ or a full URL).
     * Null means fall back to the icon.
     */
    public function previewImage(): null|string;

    /**
     * Ordered list of field definitions making up this section's schema.
     *
     * @return array<int, FieldDefinition>
     */
    public function fields(): array;

    /**
     * Content JSON to store when a new instance is first created.
     *
     * Should mirror the field schema — keys are field keys, values follow
     * each field's shape (scalars, locale maps, arrays). Used to seed the
     * "Add Section" action with sensible starter content.
     *
     * @return array<string, mixed>
     */
    public function defaultContent(): array;

    /**
     * Style JSON to store when a new instance is first created.
     *
     * Known keys (all optional): bg_color, text_color, padding_y, alignment,
     * padding_top/right/bottom/left, margin_*, animation, animation_delay.
     *
     * @return array<string, mixed>
     */
    public function defaultStyle(): array;

    /**
     * Blade partial view name responsible for rendering this section on the
     * frontend (e.g. "pages.sections.hero").
     *
     * Resolved via Laravel's view finder — users place their partials under
     * resources/views matching this path.
     */
    public function viewPartial(): string;

    /**
     * Whether the same target model may hold multiple instances of this type.
     *
     * Singletons (hero, header) return false and use the default instance
     * key; multi-instance types (gallery, sidebar widget) return true and
     * distinguish siblings via a caller-provided instance_key.
     */
    public function allowsMultipleInstances(): bool;

    /**
     * Whether this section can be deleted by content editors.
     *
     * Set to false for mandatory sections (e.g. a homepage hero) — the UI
     * hides the delete button. Users can still hide mandatory sections via
     * the publish toggle.
     */
    public function isDeletable(): bool;
}
