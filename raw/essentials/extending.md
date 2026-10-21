---
title: "Extending"
description: "Filter which fields and sections a builder resolves, and extend the field form's schema"
canonical_url: "https://relaticle.github.io/essentials/extending"
---
# Extending

> Filter which fields and sections a builder resolves, and extend the field form's schema

## Resolution filters

The Table, Infolist, and Exporter builders expose `filterFieldsUsing()` and
`filterSectionsUsing()`. Each callback receives a collection and the concrete builder.
It must return a collection containing selected existing models.

Configure one builder directly when the rule belongs to one use:

```php
use Illuminate\Support\Collection;
use Relaticle\CustomFields\Facades\CustomFields;
use Relaticle\CustomFields\Filament\Integration\Builders\TableBuilder;
use Relaticle\CustomFields\Models\CustomField;

$columns = CustomFields::table()
    ->forModel($record)
    ->filterFieldsUsing(
        static fn (Collection $fields, TableBuilder $builder): Collection => $fields
            ->reject(
                static fn (CustomField $field): bool => in_array(
                    'portal',
                    $field->setting('hidden_in_panels', []),
                    true,
                )
            )
    )
    ->columns();
```

Register application-wide defaults through Laravel's container in a service provider.
Target each concrete builder class that needs the rule:

```php
use Illuminate\Support\Collection;
use Relaticle\CustomFields\Filament\Integration\Builders\ExporterBuilder;
use Relaticle\CustomFields\Models\CustomField;

$this->app->resolving(
    ExporterBuilder::class,
    static function (ExporterBuilder $builder): void {
        $builder->filterFieldsUsing(
            static fn (Collection $fields): Collection => $fields->reject(
                static fn (CustomField $field): bool =>
                    $field->setting('exclude_from_exports', false) === true
            )
        );
    }
);
```

Facade factories resolve fresh builders through the container. Container callbacks
register before `forModel()` binds a model. Evaluate model-specific decisions inside the
filter callback:

```php
use Relaticle\CustomFields\Filament\Integration\Builders\InfolistBuilder;

$infolist = CustomFields::infolist()
    ->forModel($record)
    ->filterFieldsUsing(
        static fn (Collection $fields, InfolistBuilder $builder): Collection =>
            $builder->getRecord()?->is_archived === true
                ? $fields->reject(
                    static fn (CustomField $field): bool => $field->code === 'current_status'
                )
                : $fields
    )
    ->build();
```

`getModel()` returns the bound model class. It returns `null` before binding.
`getRecord()` returns the persisted model. It returns `null` for a model class, an
unsaved model, or an unbound builder.

Table and export schemas normally bind a model class. Keep row-specific display rules in
their component renderers, where the current record exists.

Builders also use Laravel's `Conditionable` and `Tappable` traits. Their selected
metadata is a standard collection:

```php
use App\Models\Opportunity;

$codes = CustomFields::table()
    ->forModel(Opportunity::class)
    ->when(
        $hideInternal,
        static fn (TableBuilder $builder): TableBuilder =>
            $builder->except(['internal_margin'])
    )
    ->getFields()
    ->pluck('code')
    ->all();
```

`getFields()` returns selected field metadata before component factories and
record-specific conditional visibility. A returned field might not render for the
current record.

`getSections()` returns selected, nonempty sections with their selected fields. It
returns an empty collection when sections are disabled.

Field callbacks receive `Collection<int, CustomField>`. Section callbacks receive
`Collection<int, CustomFieldSection>`. Both receive the current concrete builder as
their second argument.

Section callbacks run before field callbacks. Field callbacks receive fields from the
surviving sections. Empty sections are pruned. Field order within each section remains
stable, while a section callback may reorder sections.

Callbacks run when metadata or components resolve. Deferred and cloned infolists carry
their configured callbacks once, including container defaults and local callbacks.

Choose the API that matches the result you need:

- Eloquent queries constrain rows in the database.
- `getFields()` and `getSections()` return selected metadata.
- `columns()`, `values()`, and `build()` produce Filament components and apply visibility.

Filters select existing models. They must not rewrite metadata or enforce authorization.
Use Eloquent queries when you need database constraints:

```php
use App\Models\CustomField;
use App\Models\Opportunity;

$fields = CustomField::query()
    ->where('tenant_id', $team->getKey())
    ->forMorphEntity(Opportunity::class)
    ->visibleInList()
    ->with('options')
    ->orderBy('sort_order')
    ->get();
```

Query your application's configured custom field model. Package builders also respect
that configured subclass when loading metadata.

### Which builders apply filters

Filters apply to the **Table**, **Infolist** and **Exporter** builders. They do not
apply to the **Form** or the **Importer** builder. `UsesCustomFields::saveCustomFields()`
clears any field absent from its payload, so a filtered form would erase hidden values
on save. Fixing that contract is a breaking change, tracked separately for a future
major version.

Conditional visibility is unaffected by filtering: it's still evaluated against every
field for the entity type, so a field that a filter removes can still be the input of
another field's visibility condition.

When sections share a field code, infolist conditions use the field from their own section.
Fields with other codes remain available for conditions across sections.

Every facade call returns a fresh builder. Local callbacks belong only to that builder.
Container resolving callbacks apply to every resolved builder of the targeted class.
Direct construction follows Laravel's normal behavior and bypasses container callbacks.

## FieldForm::extendSchemaUsing()

`FieldForm::extendSchemaUsing()` lets a consumer append or modify components on the
field create/edit form, mirroring `SectionForm::extendSchemaUsing()` for sections:

```php
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Component;
use Relaticle\CustomFields\Filament\Management\Schemas\FieldForm;
use Relaticle\CustomFields\Models\CustomFieldSection;

FieldForm::extendSchemaUsing(function (array $schema, ?CustomFieldSection $section): array {
    return [
        ...$schema,
        CheckboxList::make('settings.additional.hidden_in_panels')
            ->label('Hide from')
            ->options(['portal' => 'Portal']),
    ];
});
```

The callback receives the current schema and the field's section: the create target, or
the edited field's section. It's null when the form is built without a section, such as
in flat management mode with `SYSTEM_SECTIONS` disabled. This differs from
`SectionForm::extendSchemaUsing()`, whose second argument is a `string $entityType`
rather than a section, because a field-level extension usually needs to look at its
section, while a section-level one is being defined for an entity type before any
section-specific state exists.

Register once, from a service provider. Extensions persist until
`FieldForm::flushSchemaExtensions()` is called, which is primarily useful in tests.

## setting() accessors

`CustomField::setting()` and `CustomFieldSection::setting()` read a consumer-defined key
out of the model's settings bag, with a default when the key is absent:

```php
$field->setting('hidden_in_panels', []);
$section->setting('hidden_in_panels', []);
$field->setting('display.compact', false);
```

`CustomField::setting()` reads from `settings->additional`; `CustomFieldSection::setting()`
reads from `settings->extra`. These are the same bags that `FieldForm::extendSchemaUsing()`
and `SectionForm::extendSchemaUsing()` write into via a `settings.additional.*` or
`settings.extra.*` statePath, so a filter callback and a schema extension are typically
used together: the extension collects the setting, and the filter reads it back.

Both accessors support Laravel's dot notation and preserve explicitly stored nulls.

Saving an edit merges the submitted settings into the stored ones rather than replacing
them, so a key whose component the form did not render survives. The merge recurses into
associative arrays and replaces everything else, which means a submitted empty list or a
submitted null still clears the value it targets, but a key nested inside an associative
setting can only be removed by code, not by leaving it out of a submission.

See [Builder Scoping](https://relaticle.github.io/essentials/builder-scoping) for `onlySections()`, which narrows
resolution a different way, by section identity rather than by an arbitrary predicate.


## Sitemap

See the full [sitemap](https://relaticle.github.io/sitemap.md) for all pages.
