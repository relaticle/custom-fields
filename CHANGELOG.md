# Changelog

All notable changes to `custom-fields` will be documented in this file.

## v3.5.1 - 2026-06-30

<!-- Release notes generated using configuration in .github/release.yml at v3.5.1 -->
### What's Changed

#### Other Changes

* fix: ignore validation for conditionally hidden custom fields by @ManukMinasyan in https://github.com/relaticle/custom-fields/pull/176

**Full Changelog**: https://github.com/relaticle/custom-fields/compare/v3.5.0...v3.5.1

## v3.5.0 - 2026-06-27

<!-- Release notes generated using configuration in .github/release.yml at 3.x -->
### What's Changed

#### Other Changes

* chore(deps): bump actions/checkout from 6 to 7 by @dependabot[bot] in https://github.com/relaticle/custom-fields/pull/174
* fix(i18n): replace hardcoded UI strings with translation keys by @erikpach in https://github.com/relaticle/custom-fields/pull/173
* feat: consumer hooks to scope field uniqueness + visibility picker by @ManukMinasyan in https://github.com/relaticle/custom-fields/pull/175

**Full Changelog**: https://github.com/relaticle/custom-fields/compare/v3.4.1...v3.5.0

## v3.4.1 - 2026-06-18

### Fixed

- **Date validation "After another field" reference dropdown now populates when creating a field.** The reference-field selector showed "No options available" while creating a new date custom field — it only worked when editing an existing one. The options closure resolved the entity type through the relative state path `$get('../../../entity_type')`, which inside the field-management action modal (`mountedActions.0.data.*`) climbed past the form-data scope to the Livewire root and returned `null`, short-circuiting to an empty list. It now resolves `entity_type`/`code` via absolute data-scope paths, correct in both the action-modal and page-form contexts (#172).

**Full Changelog**: https://github.com/relaticle/custom-fields/compare/v3.4.0...v3.4.1

## v3.4.0 - 2026-06-17

### Fixed

- **Visibility condition value no longer wiped when only the operator changes.** Editing a saved section/field visibility condition and changing its operator (e.g. `Is in` → `Is not in`) reset the stored value to `null`, silently turning the condition into an "is in / is not in nothing" match. The value is now preserved whenever the new operator still takes one, and only cleared for value-less operators (`is empty` / `is not empty`) (#170).

### Added

- **Configurable section modal width.** The add/edit section modal width is now resolved through `CustomFieldsPlugin::make()->sectionModalWidth(...)` (a `Width` case or closure) or the `custom-fields.management.section_modal_width` config key. The default widens to `screen-lg` when section conditional visibility is enabled — previously the add-section modal stayed at `2xl`, cramping the conditions row — and the create/edit modals now resolve to the same width (#170).

**Full Changelog**: https://github.com/relaticle/custom-fields/compare/v3.3.0...v3.4.0

## v3.3.0 - 2026-06-14

### Added

- **`SectionForm::extendSchemaUsing()`** — extend the custom-field section management form from application code (e.g. a service provider), with no subclassing or vendor patching. A single registration applies to both the Add and Edit section modals, and the callback receives the current schema plus the section's entity type, so extensions can be scoped per entity (#165).
- **Free-form `extra` bag on `CustomFieldSectionSettingsData`** — consumer-defined section settings bound under `settings.extra.*` now round-trip through the typed settings DTO (and through config import/export). Enables, for example, flagging a section to render as its own tab (#165).

**Full Changelog**: https://github.com/relaticle/custom-fields/compare/v3.2.1...v3.3.0

## v3.2.1 - 2026-06-10

### Fixed

- Apply section-level conditional visibility on infolists (#162). Section-level visibility conditions (including cross-record relation conditions) were only honored on forms; on infolists a section rendered for every record whenever it had any visible field. `InfolistBuilder` now evaluates each section's visibility against the record, mirroring the form path. Sections without conditions and field-level visibility are unaffected.

## v3.2.0 - 2026-06-09

<!-- Release notes generated using configuration in .github/release.yml at v3.2.0 -->
### What's Changed

#### Other Changes

* chore(deps): bump fast-uri from 3.1.0 to 3.1.2 in /docs in the npm_and_yarn group across 1 directory by @dependabot[bot] in https://github.com/relaticle/custom-fields/pull/152
* chore(deps): bump devalue from 5.8.0 to 5.8.1 in /docs in the npm_and_yarn group across 1 directory by @dependabot[bot] in https://github.com/relaticle/custom-fields/pull/153
* chore(deps): bump the npm_and_yarn group across 1 directory with 2 updates by @dependabot[bot] in https://github.com/relaticle/custom-fields/pull/156
* chore(deps): bump brace-expansion from 2.1.0 to 5.0.6 in /docs in the npm_and_yarn group across 1 directory by @dependabot[bot] in https://github.com/relaticle/custom-fields/pull/157
* chore(deps): bump the npm_and_yarn group across 1 directory with 2 updates by @dependabot[bot] in https://github.com/relaticle/custom-fields/pull/158
* chore(deps): bump hono from 4.12.18 to 4.12.23 in /docs in the npm_and_yarn group across 1 directory by @dependabot[bot] in https://github.com/relaticle/custom-fields/pull/160
* fix: render tags-input infolist entries as badges by @ManukMinasyan in https://github.com/relaticle/custom-fields/pull/155
* feat: cross-record (relation-path) visibility conditions [3.x] by @ManukMinasyan in https://github.com/relaticle/custom-fields/pull/161

**Full Changelog**: https://github.com/relaticle/custom-fields/compare/v3.1.7...v3.2.0

## v3.1.7 - 2026-05-08

<!-- Release notes generated using configuration in .github/release.yml at v3.1.7 -->
### What's Changed

#### Other Changes

* chore(deps): bump ip-address from 10.1.0 to 10.2.0 in /docs in the npm_and_yarn group across 1 directory by @dependabot[bot] in https://github.com/relaticle/custom-fields/pull/147
* chore(deps): bump the npm_and_yarn group across 1 directory with 3 updates by @dependabot[bot] in https://github.com/relaticle/custom-fields/pull/148
* chore(deps): bump nuxt-og-image from 6.3.7 to 6.5.0 in /docs in the npm_and_yarn group across 1 directory by @dependabot[bot] in https://github.com/relaticle/custom-fields/pull/150
* fix: prevent self-clear of field_code in visibility condition repeater by @erikpach in https://github.com/relaticle/custom-fields/pull/146
* fix(phpstan): use covariant Model in Scope apply signatures for laravel 13 [3.x] by @ManukMinasyan in https://github.com/relaticle/custom-fields/pull/151

### New Contributors

* @erikpach made their first contribution in https://github.com/relaticle/custom-fields/pull/146

**Full Changelog**: https://github.com/relaticle/custom-fields/compare/v3.1.6...v3.1.7

## v3.1.6 - 2026-04-28

### What's Changed

* Adds Laravel 13 support — the package now installs and tests cleanly on both Laravel 12 and Laravel 13.
* Replaces transitive `postare/blade-mdi` dependency with `manukminasyan/blade-mdi`, a Packagist-published fork that widens the `illuminate/support` constraint to include `^13.0`. Same `Postare\BladeMdi\` namespace, no source changes.
* CI matrix now runs against Laravel 12.* + 13.* on every push.

### Compatibility

* Laravel 12 users: drop-in compatible, no changes required.
* Laravel 13 users: `composer require relaticle/custom-fields` now works without extra setup.

PR: https://github.com/relaticle/custom-fields/pull/145

## v3.1.5 - 2026-04-21

<!-- Release notes generated using configuration in .github/release.yml at 3.x -->
### What's Changed

#### Other Changes

* chore(deps): bump hono from 4.12.12 to 4.12.14 in /docs in the npm_and_yarn group across 1 directory by @dependabot[bot] in https://github.com/relaticle/custom-fields/pull/135
* fix: prevent duplicate resource table() invocation in InteractsWithCustomFields [3.x] by @ManukMinasyan in https://github.com/relaticle/custom-fields/pull/140

**Full Changelog**: https://github.com/relaticle/custom-fields/compare/v3.1.4...v3.1.5

## v3.1.4 - 2026-04-15

<!-- Release notes generated using configuration in .github/release.yml at 3.x -->
### What's Changed

#### Other Changes

* feat: configurable description maxLength and boolean required validation fix [3.x] by @ManukMinasyan in https://github.com/relaticle/custom-fields/pull/134

**Full Changelog**: https://github.com/relaticle/custom-fields/compare/v3.1.3...v3.1.4

## v3.1.3 - 2026-04-14

<!-- Release notes generated using configuration in .github/release.yml at 3.x -->
### What's Changed

#### Other Changes

* feat: configurable description position for custom fields [3.x] by @ManukMinasyan in https://github.com/relaticle/custom-fields/pull/133

**Full Changelog**: https://github.com/relaticle/custom-fields/compare/v3.1.2...v3.1.3

## v3.1.2 - 2026-04-10

<!-- Release notes generated using configuration in .github/release.yml at 3.x -->
### What's Changed

#### Other Changes

* chore(deps): bump the npm_and_yarn group across 2 directories with 6 updates by @dependabot[bot] in https://github.com/relaticle/custom-fields/pull/130
* chore(deps): bump the npm_and_yarn group across 2 directories with 19 updates by @dependabot[bot] in https://github.com/relaticle/custom-fields/pull/132
* chore(deps): bump the npm_and_yarn group across 2 directories with 26 updates by @dependabot[bot] in https://github.com/relaticle/custom-fields/pull/131
* feat: currency field display consistency and constraint improvements by @ManukMinasyan in https://github.com/relaticle/custom-fields/pull/119

**Full Changelog**: https://github.com/relaticle/custom-fields/compare/v3.1.1...v3.1.2

## v3.1.1 - 2026-03-30

<!-- Release notes generated using configuration in .github/release.yml at 3.x -->
### What's Changed

#### Other Changes

* fix: handle pre-existing releases and protected branch pushes in CI [3.x] by @ManukMinasyan in https://github.com/relaticle/custom-fields/pull/111
* fix: use deploy key for changelog direct push [3.x] by @ManukMinasyan in https://github.com/relaticle/custom-fields/pull/113
* fix: remove blade-capture-directive from test providers [3.x] by @ManukMinasyan in https://github.com/relaticle/custom-fields/pull/115
* fix: move changelog update into release workflow [3.x] by @ManukMinasyan in https://github.com/relaticle/custom-fields/pull/118
* fix: support $guarded models and MariaDB driver [3.x] by @ManukMinasyan in https://github.com/relaticle/custom-fields/pull/124
* fix: guard against non-scalar values in UniqueCustomFieldValue rule [3.x] by @ManukMinasyan in https://github.com/relaticle/custom-fields/pull/127
* chore(deps): bump dependabot/fetch-metadata from 2.5.0 to 3.0.0 by @dependabot[bot] in https://github.com/relaticle/custom-fields/pull/128
* feat: add configurable date display formats [3.x] by @ManukMinasyan in https://github.com/relaticle/custom-fields/pull/129

**Full Changelog**: https://github.com/relaticle/custom-fields/compare/v3.1.0...v3.1.1

## v3.1.0 - 2026-03-17

### What's Changed

#### New Features

- **Model attribute conditions**: Conditional field/section visibility based on model attributes (#74)
- Section conditional visibility support

#### Fixes

- Fix null dereference in `normalizeValueForEvaluation`
- Replace `addslashes` with `json_encode` for JS string escaping
- Remove duplicate docblock on `getNormalizedFieldValues`
- Resolve rector issues for CI compatibility with rector 2.3.9
- Add explicit success notification to `createSection` action
- Update tests badge to correct workflow filename and branch
- Use full URL for `git ls-remote` in changelog workflow

#### Maintenance

- Remove Ukrainian translation for maintainability
- Add release notes configuration for auto-generated changelogs
- Add auto-release workflow and normalize CI naming
- Update actions/setup-node to v6, actions/checkout to v6

**Full Changelog**: https://github.com/relaticle/custom-fields/compare/v3.0.14...v3.1.0

## 3.0.0 - 2026-01-XX

### Added

- **Record Field Type**: New field type for entity references with avatar support
- **Phone Input Component**: Country selector with validation via `propaganistas/laravel-phone`
- **Email/Phone Columns**: Table columns with clipboard support and improved display
- **RichTextColumn**: Better rich editor display in tables
- **Tags Filter**: Colored badges and filters for tags input
- **Upgrade Command**: Automated migration tool (`vendor/bin/custom-fields-upgrade`)
- **Optional Sections**: `SYSTEM_SECTIONS` feature flag for sectionless mode
- **Field Search**: Search functionality in custom fields management page
- **Field Deactivation**: Soft-disable non-system-defined fields and sections
- **Avatar Configuration**: Display options for entity references

### Changed

- **Filament 5 Required**: Upgraded from Filament 4 to Filament 5
- **Laravel 12 Required**: Upgraded from Laravel 11 to Laravel 12
- **Phone Field Format**: Now stores structured JSON with country codes instead of plain strings
- **Email Field Format**: Now stores JSON arrays to support multiple values

### Removed

- **Lookup Fields on Non-Record Types**: `lookup_type` setting removed from text, number, date, and other non-relational field types

### Migration

Run `vendor/bin/custom-fields-upgrade` to automatically migrate phone/email formats and lookup field settings.

## 2.0.0 - 2025-XX-XX

### Added

- **Fluent Builder API**: New `CustomFields::form()`, `CustomFields::table()`, `CustomFields::infolist()` builders
- **Field Type Configurator**: Fluent configuration for enabling/disabling field types
- **BaseFieldType with FieldSchema**: Cleaner field type definitions with less boilerplate
- **Settings JSON Column**: Added to `custom_field_options` table

### Changed

- **Namespace Changes**: `InteractsWithCustomFields` trait moved to `Relaticle\CustomFields\Concerns`
- **Filament 4 Required**: Upgraded from Filament 3 to Filament 4

### Deprecated

- Component-based integration (`CustomFieldsComponent::make()`) in favor of builder API

## 1.0.0 - 2024-XX-XX

- Initial release
