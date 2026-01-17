# Upgrade Guide

> Upgrade Custom Fields from v2 to v3

<alert type="warning">

**Upgrading from v1?** First upgrade to v2 using the [v2 upgrade guide](/custom-fields/v2/getting-started/upgrade-guide), then follow this guide.

</alert>

## Requirements

Custom Fields v3 requires:

- **PHP** 8.3+
- **Laravel** 12+
- **Filament** 5.0+

## Quick Upgrade

```bash
composer require relaticle/custom-fields:"^3.0" -W
php artisan migrate
vendor/bin/custom-fields-upgrade
```

The upgrade command automatically handles data migrations for phone fields, email fields, and lookup fields.

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

Phone fields now store structured data with country codes:

```php
// v2 format (string)
"+1234567890"

// v3 format (JSON)
{"country": "US", "number": "1234567890", "formatted": "+1 (234) 567-890"}
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

1. **Validate Schema** - Checks database integrity
2. **Migrate Phone Format** - Converts phone strings to structured JSON
3. **Migrate Email Format** - Converts email strings to JSON arrays
4. **Migrate Lookup Fields** - Removes lookup settings from non-record fields
5. **Clear Caches** - Clears all relevant caches

```bash
vendor/bin/custom-fields-upgrade
```

You can run individual steps if needed:

```bash
vendor/bin/custom-fields-upgrade --step=migrate-phone-format
vendor/bin/custom-fields-upgrade --step=migrate-email-format
vendor/bin/custom-fields-upgrade --step=migrate-lookup-fields
vendor/bin/custom-fields-upgrade --step=clear-caches
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
- [ ] Run `vendor/bin/custom-fields-upgrade`
- [ ] Clear all caches
- [ ] Test phone and email fields display correctly
- [ ] Test any custom field type implementations
