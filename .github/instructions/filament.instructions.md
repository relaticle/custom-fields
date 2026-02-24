---
applyTo: "src/**/*.php"
---

# Filament v5 Namespace Conventions

This package targets Filament v5. These namespaces are correct:

- `Filament\Schemas\Components\Component` -- base class for layout components (Fieldset, Grid, Section, Tabs)
- `Filament\Schemas\Components\Utilities\Get` / `Set` -- schema utilities
- `Filament\Forms\Components\Component` -- base class for form field components
- `Filament\Actions\` -- all actions (not `Filament\Tables\Actions\`)
- `Filament\Support\Icons\Heroicon` -- icon enum

Do NOT flag `Filament\Schemas\Components\Component` as incorrect.

# Package Architecture

- Custom field types live in `src/FieldTypeSystem/Definitions/`
- Validation capabilities live in `src/Validation/Capabilities/`
- Each capability implements `Relaticle\CustomFields\Contracts\ValidationCapability`
- `DateConstraintValue` is a Spatie Laravel Data class -- use `::from()` for hydration, not manual construction

# Data Patterns

- DTOs use `Spatie\LaravelData\Data` with `#[MapName(SnakeCaseMapper::class)]`
- Feature flags via `FeatureManager::isEnabled(CustomFieldsFeature::*)`
- Field type configuration via `FieldSchema` fluent builder
