# Installation

> Get started with Custom Fields in just a few steps

Get started with Custom Fields in just a few steps.

## Requirements

- **PHP**: 8.2 or higher
- **Laravel**: 10.0 or higher
- **Filament**: 3.0 or higher

## Installation

### 1. Purchase a License

[View pricing plans](/#pricing) and choose the one that fits your project.

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
composer require relaticle/custom-fields
```

When prompted for authentication:

- **Username**: Your email (used for purchase)
- **Password**: Your license key

Your license key will be saved in Composer's auth file for future updates.

### 4. Run Installer

```bash
php artisan custom-fields:install
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
