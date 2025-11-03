![Custom Fields](art/preview.png)

<p align="center">
   <a href="https://packagist.org/packages/relaticle/custom-fields"><img src="https://img.shields.io/packagist/dt/relaticle/custom-fields.svg?style=for-the-badge" alt="Downloads"></a>
  <a href="https://laravel.com/docs/12.x"><img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel" alt="Laravel 12"></a>
  <a href="https://php.net"><img src="https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php" alt="PHP 8.3"></a>
  <a href="https://github.com/Relaticle/custom-fields/blob/main/LICENSE"><img src="https://img.shields.io/badge/License-AGPL--3.0-blue.svg?style=for-the-badge" alt="License"></a>
  <a href="https://github.com/relaticle/custom-fields/actions"><img src="https://img.shields.io/github/actions/workflow/status/relaticle/custom-fields/run-tests.yml?branch=2.x&style=for-the-badge&label=tests" alt="License"></a>
</p>

A powerful Laravel/Filament plugin for adding dynamic custom fields to any Eloquent model without database migrations.

## Features

- **20+ Field Types** - Text, date, select, file upload, rich editor, and more
- **Conditional Visibility** - Show/hide fields based on other field values
- **Multi-tenancy** - Complete tenant isolation and context management
- **Filament Integration** - Forms, tables, infolists, and admin interface
- **Import/Export** - Built-in CSV capabilities
- **Security** - Optional field encryption and type-safe validation
- **Extensible** - Custom field types and automatic discovery

## Requirements

- PHP 8.3+
- Laravel via Filament 4.0+

## Getting Started

### Integrating Custom Fields Plugin into a panel

```php
use Relaticle\CustomFields\CustomFieldsPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        // ... other panel configurations
        ->plugins([
            CustomFieldsPlugin::make(),
        ]);
}
```

### Setting Up the Model

Add the trait to your model:

```php
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\Concerns\UsesCustomFields;

class Post extends Model implements HasCustomFields
{
    use UsesCustomFields;
}
```

Add to your Filament form:

```php
use Filament\Schemas\Schema;
use Relaticle\CustomFields\Facades\CustomFields;

public function form(Schema $schema): Form
{
    return $schema->components([
        // Your existing form fields...
        
        CustomFields::form()->forSchema($schema)->build()
    ]);
}
```

## Documentation

For complete installation instructions, configuration options, and examples, visit our [documentation](https://custom-fields.relaticle.com/).

## Contributing

Contributions are welcome! Please see our [contributing guide](https://custom-fields.relaticle.com/help-support/contributing) in the documentation.

## Licensing

This plugin is dual-licensed: Open Source (AGPL-3.0) for open source projects, and Commercial License for closed-source projects.

AGPL-3.0 requires your entire application to be open source. For private/closed-source projects, you need a commercial license.

Please take a look at our documentation for licensing details.  
