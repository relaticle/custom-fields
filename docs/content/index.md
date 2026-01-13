---
seo:
  title: Custom Fields - Dynamic Fields Without Migrations
  description: Add dynamic custom fields to your Filament admin panels without writing database migrations. Perfect for multi-tenant SaaS and admin panels.
  ogImage: /preview.png
---

::u-page-hero
---
orientation: horizontal
---
#title
Custom Fields

#description
Add dynamic custom fields to your Filament admin panels without writing database migrations.

#links
  :::u-button
  ---
  color: primary
  size: xl
  to: /getting-started/installation
  trailing-icon: i-lucide-arrow-right
  ---
  Get Started
  :::

  :::u-button
  ---
  color: neutral
  variant: outline
  size: xl
  to: https://github.com/relaticle/custom-fields
  icon: i-simple-icons-github
  target: _blank
  ---
  GitHub
  :::

#default
  :::div{class="w-full"}
  <div class="aspect-video w-full rounded-lg overflow-hidden shadow-2xl ring-1 ring-gray-200 dark:ring-gray-800">
    <iframe
      width="100%"
      height="100%"
      src="https://www.youtube.com/embed/6iVfeS7VixA?si=3L8H22mbMayEuHs8"
      title="Custom Fields Demo"
      frameborder="0"
      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
      referrerpolicy="strict-origin-when-cross-origin"
      allowfullscreen>
    </iframe>
  </div>
  :::
::

::u-page-section
---
headline: The Problem
title: Stop Writing Migrations for Custom Fields
description: Every custom field request becomes a development bottleneck that slows down your entire team.
---

#default
  :::u-page-grid
    ::::u-page-card
    ---
    icon: i-lucide-file-code
    title: Write Migrations
    description: Create and test database migrations for every new field request.
    ---
    ::::

    ::::u-page-card
    ---
    icon: i-lucide-git-branch
    title: Coordinate Deploys
    description: Sync schema changes across development, staging, and production.
    ---
    ::::

    ::::u-page-card
    ---
    icon: i-lucide-alert-triangle
    title: Risk Production
    description: Hope nothing breaks with each database schema modification.
    ---
    ::::

    ::::u-page-card
    ---
    icon: i-lucide-repeat
    title: Repeat Forever
    description: Do it all again for every field change request from stakeholders.
    ---
    ::::
  :::
::

::u-page-section
---
headline: The Solution
title: Two Lines of Code
description: Let users create their own fields through the admin panel. No migrations. No deployments.
---

#default
<div class="max-w-4xl mx-auto">

```php
// 1. Add to your model
class Product extends Model implements HasCustomFields
{
    use UsesCustomFields;
}

// 2. Add to your Filament resource
public function form(Schema $schema): Schema
{
    return $schema->components([
        ...CustomFields::form()->forSchema($schema)->columns(),
    ]);
}
```

</div>
::

::u-page-section
---
title: Why Custom Fields?
---

#features
  :::u-page-feature
  ---
  icon: i-lucide-database
  ---
  #title
  Zero Migrations

  #description
  Users create and manage fields directly through the admin interface - no deployments required.
  :::

  :::u-page-feature
  ---
  icon: i-lucide-building
  ---
  #title
  Multi-Tenant Ready

  #description
  Complete tenant isolation with automatic scoping for SaaS applications.
  :::

  :::u-page-feature
  ---
  icon: i-lucide-shapes
  ---
  #title
  20+ Field Types

  #description
  Text, numbers, dates, selects, rich editors, tags, color pickers, and more.
  :::

  :::u-page-feature
  ---
  icon: i-lucide-shield-check
  ---
  #title
  Type-Safe Storage

  #description
  Hybrid EAV architecture with typed columns for efficient queries.
  :::

  :::u-page-feature
  ---
  icon: i-lucide-move
  ---
  #title
  Drag & Drop

  #description
  Intuitive field management with reordering, sections, and conditional visibility.
  :::

  :::u-page-feature
  ---
  icon: i-lucide-puzzle
  ---
  #title
  Full Filament Integration

  #description
  Works with forms, tables, infolists, import/export, and the complete ecosystem.
  :::
::

::u-page-section
---
headline: Ecosystem
title: Build More with Less
description: Extend your Laravel applications with our complementary tools
---

#default
  :::u-page-grid
    ::::u-page-card
    ---
    icon: i-simple-icons-laravel
    title: FilaForms
    description: Visual form builder for all your public-facing forms.
    to: https://filaforms.app
    target: _blank
    ---
    ::::

    ::::u-page-card
    ---
    icon: i-lucide-layout-kanban
    title: Flowforge
    description: Transform any Laravel model into drag-and-drop Kanban boards.
    to: https://relaticle.github.io/flowforge/
    target: _blank
    ---
    ::::
  :::
::
