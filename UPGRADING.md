# Upgrading

The v2 to v3 steps live in the [upgrade guide](docs/content/1.getting-started/3.upgrade-guide.md). This file lists what changes between minor releases of v3.

## From 3.13 to 3.14

### The `link_variant` setting is gone

`LinkFieldType` no longer reads the internal `link_variant` setting. Every link field keeps its scheme, path, query and fragment. `LinkFieldType::normalize()` is removed, so the type uses `BaseFieldType::normalize()`, which returns `setValue($value)`.

A field that had `link_variant` set to `domain` stops reducing new values to a host. Values already stored stay as they are. If you relied on the setting, register your own field type that overrides `setValue()` to return the host, and change the `type` of those fields to it.

`LinkFieldType::equivalentValues()` is unchanged. On a field that still carries `link_variant`, the unique rule and value matching stop treating a pasted URL as its host, so `https://www.acme.com/pricing` no longer collides with a stored `acme.com`.

### A field type can stay out of the type picker

`FieldSchema::systemOnly()` marks a type that only code should create. The type still resolves, validates and renders. The field type picker leaves it out, and the settings form refuses it on create. The edit form of an existing field still shows it. A field of a system-only type has no duplicate action.

`FieldTypeData` has a new `systemOnly` property, `false` by default. `FieldTypeCollection::selectable(?string $except = null)` returns the types the picker offers.

## From 3.11 to 3.13

There is no 3.12 release. 3.13 follows 3.11.

3.13 normalizes values when they are written. You cannot opt out of any change below.

### Every write path normalizes

`CustomFieldValue::setValue()` now passes the value and its `CustomField` to `SafeValueConverter::toDbSafe()`. The result is stored.

This covers `saveCustomFieldValue()` and any other code that sets a value through `CustomFieldValue::setValue()`. In 3.11 only the link form field applied `setValue()` before saving.

For list values, an item that normalizes to an empty string is dropped. Items that normalize to the same string collapse into one.

A scalar value that normalizes to an empty string is stored as `null`.

### Phones are stored as E.164

A phone value that parses as a possible number is stored as E.164, for example `+14155550100`. Spaces, brackets, and dashes are removed.

An extension is stored as a `;ext=` suffix, for example `+14155550100;ext=12`. Tables and infolists show it as `ext. 12` and dial only the number.

A value must start with `+` to be parsed. Without it, or when it is not a possible number, the value is stored trimmed.

### Domain links are stored as a bare lowercase host

A link field with the `link_variant` setting set to `domain` stores only the host. `HTTPS://www.Acme.com/pricing?x=1` becomes `acme.com`.

`link_variant` is an internal setting. No settings form offers it. 3.14 removes the setting. See From 3.13 to 3.14 above.

Normalization lower-cases the value and removes whitespace, the scheme, userinfo, port, path, query, fragment, leading `www.`, and a trailing dot.

A list item with no host left, such as `https://`, is dropped. A value that is not a host, such as `tel:+14155550100`, is kept as typed.

### Url links keep their scheme

A link without the `domain` variant keeps its scheme, path, query and fragment. `https://Example.com/Pricing/` is stored as `https://example.com/Pricing`.

The scheme and host are lower-cased and trailing slashes are removed. A value typed without a scheme gets none. In 3.11 one leading `http://` or `https://` was stripped.

`http://acme.com` and `https://acme.com` are two stored values. The unique rule treats them as one.

### The unique rule compares equivalent values

`UniqueCustomFieldValue` asks the field type for every stored form that counts as the same value. `BaseFieldType::equivalentValues()` returns them.

For a link, the forms are the normalized value, the stored value, and the stored value with `https://`, with `http://`, and with no scheme. On a domain link field, a stored `acme.com` blocks a typed `HTTPS://www.Acme.com/x`. On a url link field, a stored `https://acme.com/pricing` blocks a typed `acme.com/pricing`.

The rule looks up those forms only. It does not match every spelling an older version stored. A stored `www.acme.com` does not block a typed `acme.com`.

`ValidationService` passes `exceptHeldValues: true`. A record can keep a unique value that it already holds, even when another record holds it too.

### Custom field types

`BaseFieldType` has a new method, `normalize(string $value, CustomField $customField): string`. It returns `setValue($value)` by default.

Override `normalize()` when the stored form depends on a field setting, as `LinkFieldType` did with `link_variant` in 3.13. Keep it idempotent: normalizing a normalized value must return the same value.

If your type already overrides `setValue()`, that method now runs on every write path, not only where your form called it.

`BaseFieldType` also has `equivalentValues(string $value, CustomField $customField): array`. It returns the normalized value alone by default. A subclass that already declares a method with that name and another signature fails to load.

`SafeValueConverter::toDbSafe()` takes an optional third argument, `?CustomField $customField`. Without it, no normalization runs.

### No backfill ships

The package does not rewrite values stored by 3.11 or earlier.

Values stored before 3.13 keep their old spelling until you normalize them. Until then, the unique rule matches a stored value only when it equals one of the equivalent forms above. Code that reads the stored text directly still sees the old spelling.

To normalize old rows, loop over the fields of the types you use and rewrite each stored value:

1. Select the `CustomField` records of type `link` and `phone`.
2. For each one, read its value rows. The value model is tenant-scoped, so set the tenant first when multi-tenancy is on.
3. Pass each item of the stored value through the field type's `normalize($item, $customField)`.
4. Drop items that come back empty, remove duplicates, and save the row only if the result differs.

The rewrite is idempotent, so you can run it again after a partial failure.
