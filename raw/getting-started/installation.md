# Installation

> Get started with Custom Fields in just a few steps

<alert type="warning">

**Beta Release**: Custom Fields v3 is currently in beta. While stable for production use, some APIs may change before the final release.

</alert>

Get started with Custom Fields in just a few steps.

## Requirements

- **PHP**: 8.3 or higher
- **Laravel**: 12 or higher
- **Filament**: 5.x
- **Tailwind CSS**: 4.0 or higher

## Installation

Choose your installation method based on your license type:

<tabs>
<tab label="Commercial License">

### 1. Purchase a License

[Choose a commercial license](/community/license) that fits your project.

### 2. Add Private Repository

Add our private Composer repository to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https://satis.relaticle.com"
        }
    ]
}
```

### 3. Install Package

```bash
composer require relaticle/custom-fields:^3.0
```

When prompted for authentication:

- **Username**: Your email (used for purchase)
- **Password**: Your license key

Your license key will be saved in Composer's auth file for future updates.

### 4. Run Installer

```bash
php artisan custom-fields:install
```

</tab>

<tab label="Open Source (AGPL-3.0)">

> **AGPL-3.0 License**: Requires your **entire application** to be open source. See [license details](/community/license).

### 1. Install Package

Install directly from Packagist:

```bash
composer require relaticle/custom-fields:^3.0
```

### 2. Run Installer

```bash
php artisan custom-fields:install
```

</tab>
</tabs>

## Include CSS Assets

You need a custom Filament theme to include the Custom Fields styles. If you haven't set up a custom theme for Filament, follow the [Filament Docs](https://filamentphp.com/docs/5.x/panels/themes#creating-a-custom-theme) first.

Once you have a custom Filament theme set up, add the plugin's views to your theme CSS file:

**resources/css/filament/admin/theme.css**

```css
@source '../../../../vendor/relaticle/custom-fields/resources';
```

## Optional Configuration

### Publish Configuration File

```bash
php artisan vendor:publish --tag="custom-fields-config"
```

### Publish Language Files

```bash
php artisan vendor:publish --tag="custom-fields-translations"
```

### Publish Views (for customization)

```bash
php artisan vendor:publish --tag="custom-fields-views"
```
