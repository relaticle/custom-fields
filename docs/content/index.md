---
seo:
  title: Custom Fields - Dynamic Fields Without Migrations
  description: Add dynamic custom fields to your Filament admin panels without writing database migrations. Perfect for multi-tenant SaaS and admin panels.
  ogImage: /preview.png
---

::u-page-hero
#title
Custom Fields

#description
Add dynamic custom fields to your Filament admin panels without writing database migrations.

Perfect for multi-tenant SaaS applications and enterprise admin panels.

#links
  :::u-button
  ---
  color: neutral
  size: xl
  to: /getting-started/installation
  trailing-icon: i-lucide-arrow-right
  ---
  Get started
  :::

  :::u-button
  ---
  color: neutral
  icon: simple-icons:github
  size: xl
  to: https://github.com/relaticle/custom-fields
  variant: outline
  ---
  GitHub
  :::
::

<div class="text-center max-w-4xl mx-auto">
  <div class="aspect-video rounded-lg shadow-lg overflow-hidden">
    <iframe width="100%" height="100%" src="https://www.youtube.com/embed/6iVfeS7VixA?si=3L8H22mbMayEuHs8" title="Custom Fields Demo" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
  </div>
</div>

::u-page-section
#title
Why choose Custom Fields?

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
#title
Pricing

#description
All tiers include every feature. Dev/staging environments are always free.

#default
  ::u-pricing-plans{compact class="gap-x-3"}
    :::u-pricing-plan
    ---
    title: Open Source
    price: Free
    description: AGPL-3.0 for open source projects
    features:
      - Unlimited domains
      - Community support
    button:
      label: View on GitHub
      to: https://github.com/relaticle/custom-fields
      target: _blank
      color: neutral
      variant: outline
    ---
    :::

    :::u-pricing-plan
    ---
    title: Solo
    price: $79
    billing-cycle: /year
    description: Personal projects & internal tools
    features:
      - 1 domain
      - 1 year updates
    button:
      label: Purchase
      to: https://relaticle.lemonsqueezy.com/buy/803d5933-4b12-4869-9d93-f96797339603
      target: _blank
    ---
    :::

    :::u-pricing-plan
    ---
    title: Pro
    price: $129
    billing-cycle: /year
    description: Agencies & multiple client sites
    features:
      - Up to 5 domains
      - 1 year updates
    button:
      label: Purchase
      to: https://relaticle.lemonsqueezy.com/buy/803d5933-4b12-4869-9d93-f96797339603
      target: _blank
    ---
    :::

    :::u-pricing-plan
    ---
    title: Business
    price: $299
    billing-cycle: /year
    description: Required for SaaS & multi-tenant
    highlight: true
    features:
      - Unlimited domains
      - 1 year updates
    button:
      label: Purchase
      to: https://relaticle.lemonsqueezy.com/buy/803d5933-4b12-4869-9d93-f96797339603
      target: _blank
    ---
    :::
  ::

  <div class="mt-4 text-center text-sm text-gray-500 dark:text-gray-400">
    <strong>Students/Educators</strong>: 50% off &middot; <strong>Non-Profits</strong>: 40% off &middot; <a href="/community/license" class="underline hover:text-gray-700 dark:hover:text-gray-200">Full license details</a>
  </div>
::

::u-page-section
#title
Our Ecosystem

#description
Extend your Laravel applications with our ecosystem of complementary tools

#default
  ::card-group
    :::card
    ---
    title: FilaForms
    icon: i-simple-icons-laravel
    to: https://filaforms.app
    target: _blank
    ---
    :img{src="https://filaforms.app/img/og-image.png" alt="FilaForms" class="mb-4 rounded-lg w-full pointer-events-none"}

    Visual form builder for all your public-facing forms.
    :::

    :::card
    ---
    title: Flowforge
    icon: i-lucide-columns-3
    to: https://relaticle.github.io/flowforge/
    target: _blank
    ---
    :img{src="https://relaticle.github.io/flowforge/preview.png" alt="Flowforge" class="mb-4 rounded-lg w-full pointer-events-none"}

    Transform any Laravel model into drag-and-drop Kanban boards.
    :::
  ::
::
