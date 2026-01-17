# Installation

> Get started with Custom Fields in just a few steps

<alert type="warning">

**Beta Release**: Custom Fields v3 is currently in beta. While stable for production use, some APIs may change before the final release.

</alert>

Get started with Custom Fields in just a few steps.

## Requirements

- **PHP**: 8.2 or higher
- **Laravel**: 11.28 or higher
- **Filament**: 4.x
- **Tailwind CSS**: 4.0 or higher

## Installation

Choose your installation method based on your license type:

<tabs>
<tab label="Commercial License">

### For commercial/private projects

<steps>

### Purchase a license

[Choose a commercial license](/community/license#commercial-license-pricing)

### Add the private repository

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

### Install the package

```bash
composer require relaticle/custom-fields:^3.0
```

When prompted for authentication:

- **Username**: Your email (used for purchase)
- **Password**: Your license key

### Run the installer

```bash
php artisan custom-fields:install
```

### Include CSS assets

<alert type="info">

You need a custom Filament theme to include the Custom Fields styles.

If you haven't set up a custom theme for Filament, follow the [Filament Docs](https://filamentphp.com/docs/4.x/panels/themes#creating-a-custom-theme) first.

</alert>
</steps>
</tab>
</tabs>

Once you have a custom Filament theme set up, add the plugin's views to your theme CSS file:

**resources/css/filament/admin/theme.css**

```css
@source '../../../../vendor/relaticle/custom-fields/resources';
```

::

<alert type="info">

Your license key will be saved in Composer's auth file for future updates.

</alert>

:::

<tab label="Open Source (AGPL-3.0)">
<alert type="warning">

**AGPL-3.0 License Requirements**: This installation method requires your **entire application** to be open source and licensed under AGPL-3.0. This applies to ALL code in your project, including:

- Your application code
- SaaS/web applications accessible over a network
- Any modifications or extensions

If you cannot make your entire codebase public, you **must** use a [Commercial License](/community/license#commercial-license-pricing) instead.

</alert>

### For open source projects

<steps>

### Install the package

Install directly from Packagist:

```bash
composer require relaticle/custom-fields:^3.0
```

### Run the installer

```bash
php artisan custom-fields:install
```

### Include CSS assets

<alert type="info">

You need a custom Filament theme to include the Custom Fields styles.

If you haven't set up a custom theme for Filament, follow the [Filament Docs](https://filamentphp.com/docs/4.x/panels/themes#creating-a-custom-theme) first.

</alert>

Once you have a custom Filament theme set up, add the plugin's views to your theme CSS file:

**resources/css/filament/admin/theme.css**

```css
@source '../../../../vendor/relaticle/custom-fields/resources';
```

</steps>
</tab>

::

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
