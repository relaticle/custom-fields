# Upgrade Guide

> Upgrade Custom Fields from v2 to v3

## Requirements

Custom Fields v3 requires:

- **PHP** 8.3+
- **Laravel** 12+
- **Filament** 5.0+

## Quick Upgrade

```bash
composer require relaticle/custom-fields:"^3.0" -W
php artisan migrate
php artisan custom-fields:upgrade
```

The upgrade command automatically handles data migrations for phone fields, email fields, and lookup fields.

### Command Options

<table>
<thead>
  <tr>
    <th>
      Option
    </th>
    
    <th>
      Description
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      <code>
        --dry-run
      </code>
    </td>
    
    <td>
      Show what would be migrated without making changes
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --force
      </code>
    </td>
    
    <td>
      Run without confirmation prompts
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --skip=
      </code>
    </td>
    
    <td>
      Skip specific steps (comma-separated)
    </td>
  </tr>
</tbody>
</table>

**Skippable steps**: `lookup-fields`, `email-format`, `phone-format`, `validate-schema`, `clear-caches`

```bash
# Preview changes without applying them
php artisan custom-fields:upgrade --dry-run

# Run in CI/CD without prompts
php artisan custom-fields:upgrade --force

# Skip specific steps
php artisan custom-fields:upgrade --skip=clear-caches
php artisan custom-fields:upgrade --skip=email-format,phone-format
```

## Picking Up New Migrations

Package migrations are not run automatically by `php artisan migrate` after a version
bump — they're only copied into your app on first install, or when you explicitly
republish them:

```bash
php artisan vendor:publish --tag="custom-fields-migrations"
php artisan migrate
```

For example, a release may relax the `custom_fields` unique key from
`(code, entity_type[, tenant])` to `(code, entity_type[, tenant], custom_field_section_id)`
so a field code can be reused across different sections — the shape `onlySections()` (see
[Builder Scoping](/essentials/builder-scoping)) relies on. Existing installs need the
republish-and-migrate step above to pick that change up; it is not applied automatically.

**Before rolling that migration back**, resolve any rows that ended up sharing a code
across sections. The migration checks for this first and aborts with a clear error rather
than dropping the wide key and then failing to recreate the narrow one, which would leave
the table with no unique key at all.

**NULL is not unique-constrained.** `custom_field_section_id` is nullable, and both MySQL
and Postgres treat `NULL` as distinct from every other value in a unique index — including
one that includes it. So after this migration, two sectionless fields
(`custom_field_section_id IS NULL`) can still share a code for the same entity type, a
collision the narrow key used to prevent. There is no schema-level fix for this; keep
sectionless codes unique at the application layer if you rely on that guarantee.

## Breaking Changes

### High Impact

#### Filament 5 Required

Custom Fields v3 requires Filament 5. If you're still on Filament 4, upgrade Filament first following the [Filament upgrade guide](https://filamentphp.com/docs/5.x/upgrade-guide).

#### Lookup Fields Removed from Non-Record Types

The `lookup_type` setting has been removed from field types that don't support entity lookups. The upgrade command migrates affected fields automatically.

**Affected field types**: Text, Textarea, Number, Date, DateTime, Email, Phone, and other non-relational types.

If you were using `lookup_type` on these fields, they will be converted to standard fields.

### Medium Impact

#### Phone Field Format Changed

Phone fields now store values as JSON arrays of E.164 strings (supporting multiple phone numbers):

```php
// v2 format (single string in string_value column)
"+11234567890"

// v3 format (JSON array in json_value column)
["+11234567890"]
```

The upgrade command migrates existing phone values automatically.

#### Email Field Format Changed

Email fields now support multiple values stored as JSON:

```php
// v2 format (string)
"user@example.com"

// v3 format (JSON array)
["user@example.com"]
```

The upgrade command migrates existing email values automatically.

### Low Impact

#### New Phone Validation Package

v3 adds `propaganistas/laravel-phone` for phone validation. This is installed automatically with composer.

## Manual Upgrade Steps

If you prefer manual control over the upgrade process:

### 1. Update Dependencies

```bash
composer require relaticle/custom-fields:"^3.0" -W
```

### 2. Run Migrations

```bash
php artisan migrate
```

### 3. Run Upgrade Command

The upgrade command performs these steps:

1. **Migrate Lookup Fields** - Removes lookup settings from non-record fields
2. **Migrate Email Format** - Converts email strings to JSON arrays
3. **Migrate Phone Format** - Converts phone strings to structured JSON
4. **Validate Schema** - Checks database integrity
5. **Clear Caches** - Clears all relevant caches

```bash
php artisan custom-fields:upgrade
```

You can skip specific steps if needed:

```bash
# Run everything except cache clearing
php artisan custom-fields:upgrade --skip=clear-caches

# Run only lookup field migration
php artisan custom-fields:upgrade --skip=email-format,phone-format,validate-schema,clear-caches
```

### 4. Clear Caches

```bash
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan filament:cache-components
```

## Troubleshooting

<accordion>
<accordion-item label="Phone fields showing raw JSON">

Clear your views and Filament component cache:

```bash
php artisan view:clear
php artisan filament:cache-components
```

</accordion-item>

<accordion-item label="Upgrade command fails on phone migration">

Check for invalid phone numbers in your database:

```sql
SELECT * FROM custom_field_values
WHERE custom_field_id IN (
  SELECT id FROM custom_fields WHERE type = 'phone'
) AND text_value IS NOT NULL;
```

Invalid numbers are preserved as-is with a default country code.

</accordion-item>

<accordion-item label="Missing lookup_type errors">

If you have custom code referencing `lookup_type` on non-record fields, remove those references. Only the `record` field type supports lookups in v3.

</accordion-item>
</accordion>

## Migration Checklist

- [ ] Backup your database
- [ ] Verify PHP 8.3+, Laravel 12+, Filament 5+ requirements
- [ ] Run `composer require relaticle/custom-fields:"^3.0" -W`
- [ ] Run `php artisan migrate`
- [ ] Run `php artisan custom-fields:upgrade` (use `--dry-run` first to preview)
- [ ] Clear all caches
- [ ] Test phone and email fields display correctly
- [ ] Test any custom field type implementations
