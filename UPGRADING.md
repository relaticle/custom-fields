# Upgrading

The v2 to v3 steps live in the [upgrade guide](docs/content/1.getting-started/3.upgrade-guide.md). This file lists what changes between minor releases of v3.

## From 3.11 to 3.12

3.12 normalizes values when they are written. You cannot opt out of any change below.

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

Normalization lower-cases the value and removes whitespace, the scheme, userinfo, port, path, query, fragment, leading `www.`, and a trailing dot.

A list item with no host left, such as `https://`, is dropped. A value that is not a host, such as `tel:+14155550100`, is kept as typed.

Links without the `domain` variant keep their path. They lose one leading `http://` or `https://`, as in 3.11.

### The unique rule compares normalized values

`UniqueCustomFieldValue` normalizes the submitted value and every stored value before comparing them. A stored `https://www.acme.com/` now blocks a new `acme.com` on a domain link field.

For fields stored in `json_value`, the rule reads every stored value of the field. It no longer matches by exact JSON containment.

`ValidationService` passes `exceptHeldValues: true`. A record can keep a unique value that it already holds, even when another record holds it too.

### Custom field types

`BaseFieldType` has a new method, `normalize(string $value, CustomField $customField): string`. It returns `setValue($value)` by default.

Override `normalize()` when the stored form depends on a field setting, as `LinkFieldType` does with `link_variant`. Keep it idempotent: normalizing a normalized value must return the same value.

If your type already overrides `setValue()`, that method now runs on every write path, not only where your form called it.

`SafeValueConverter::toDbSafe()` takes an optional third argument, `?CustomField $customField`. Without it, no normalization runs.

### No backfill ships

The package does not rewrite values stored by 3.11 or earlier. Old rows keep their spelling until a record is saved again.

The unique rule compares old spellings in their normalized form. Code that reads the stored text directly still sees the old spelling.

To normalize old rows, loop over the fields of the types you use and rewrite each stored value:

1. Select the `CustomField` records of type `link` and `phone`.
2. For each one, read its value rows. The value model is tenant-scoped, so set the tenant first when multi-tenancy is on.
3. Pass each item of the stored value through the field type's `normalize($item, $customField)`.
4. Drop items that come back empty, remove duplicates, and save the row only if the result differs.

The rewrite is idempotent, so you can run it again after a partial failure.
