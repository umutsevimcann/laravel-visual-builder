# Changelog

All notable changes to `laravel-visual-builder` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial scaffolding (Spatie package structure)

## [0.1.0] — TBD

### Added
- First public release
- Core framework: `SectionTypeInterface`, `FieldDefinition` abstract + 8 concrete fields (Text, Html, Toggle, Select, Link, Image, Reference, Repeater)
- Polymorphic `BuilderSection` model — attach to any Eloquent model via `HasVisualBuilder` trait
- Extension contracts: `BuilderRepositoryInterface`, `MediaServiceInterface`, `SanitizerInterface`, `AuthorizationInterface`
- Default Eloquent repository implementation
- Actions: `CreateSection`, `UpdateSectionContent`, `UpdateSectionStyle`, `UpdateSectionVisibility`, `DeleteSection`, `DuplicateSection`, `ReorderSections`, `ApplyBuilderLayout`
- `DesignTokenService` — global colors + fonts via CSS variables
- Admin UI: Blade component `<x-visual-builder::editor>` with 3-tab panel (Content/Style/Advanced)
- Live iframe preview with click-to-edit, postMessage protocol
- Style features: typography sliders, color pickers, 4-side padding/margin, alignment
- Element-level style override (per-field typography, colors, spacing)
- Motion effects: 10 entrance animations (IntersectionObserver-based)
- Copy/paste, right-click context menu, Navigator panel, local Revisions
- Undo/redo with 50-step history, keyboard shortcuts (Ctrl+S/Z/Y)
- Responsive viewport modes (desktop/tablet/mobile)
- 2 example section types: `BlankContainerSection`, `TwoColumnSection`
- Full test coverage with Pest + Orchestra Testbench
