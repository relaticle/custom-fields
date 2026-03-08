# 

> 

<u-page-hero>
<template v-slot:title="">

Custom Fields

</template>

<template v-slot:description="">

Add dynamic custom fields to your Filament admin panels without writing database migrations.

Perfect for multi-tenant SaaS applications and enterprise admin panels.

</template>

<template v-slot:links="">
<u-button color="neutral" size="xl" to="/getting-started/installation" trailing-icon="i-lucide-arrow-right">

Get started

</u-button>
</template>
</u-page-hero>

<div className="text-center,max-w-4xl,mx-auto">
<div className="aspect-video,rounded-lg,shadow-lg,overflow-hidden">
<iframe width="100%" height="100%" src="https://www.youtube.com/embed/6iVfeS7VixA?si=3L8H22mbMayEuHs8" title="Custom Fields Demo" frameBorder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerPolicy="strict-origin-when-cross-origin" allowFullScreen="true">



</iframe>
</div>
</div>

<div className="text-center,mt-6,text-sm,text-gray-500,dark:text-gray-400">

Powering [Relaticle CRM](https://relaticle.com) in production

</div>

<u-page-section>
<template v-slot:title="">

Why choose Custom Fields?

</template>

<template v-slot:features="">
<u-page-feature icon="i-lucide-database">
<template v-slot:title="">

Zero Migrations

</template>

<template v-slot:description="">

Users create and manage fields directly through the admin interface - no deployments required.

</template>
</u-page-feature>

<u-page-feature icon="i-lucide-building">
<template v-slot:title="">

Multi-Tenant Ready

</template>

<template v-slot:description="">

Complete tenant isolation with automatic scoping for SaaS applications.

</template>
</u-page-feature>

<u-page-feature icon="i-lucide-shapes">
<template v-slot:title="">

20+ Field Types

</template>

<template v-slot:description="">

Text, numbers, dates, selects, rich editors, tags, color pickers, and more.

</template>
</u-page-feature>

<u-page-feature icon="i-lucide-shield-check">
<template v-slot:title="">

Type-Safe Storage

</template>

<template v-slot:description="">

Hybrid EAV architecture with typed columns for efficient queries.

</template>
</u-page-feature>

<u-page-feature icon="i-lucide-move">
<template v-slot:title="">

Drag & Drop

</template>

<template v-slot:description="">

Intuitive field management with reordering, sections, and conditional visibility.

</template>
</u-page-feature>

<u-page-feature icon="i-lucide-puzzle">
<template v-slot:title="">

Full Filament Integration

</template>

<template v-slot:description="">

Works with forms, tables, infolists, import/export, and the complete ecosystem.

</template>
</u-page-feature>
</template>
</u-page-section>

<u-page-section>
<template v-slot:title="">

Pricing

</template>

<template v-slot:description="">

All tiers include every feature. Dev/staging environments are always free.

</template>

  <div className="max-w-4xl,mx-auto">
<u-pricing-plans :compact="true" className="gap-x-3">
<u-pricing-plan :button="{"label":"Purchase","to":"https://relaticle.lemonsqueezy.com/buy/803d5933-4b12-4869-9d93-f96797339603","target":"_blank"}" :features="["1 domain","1 year updates"]" billing-cycle="/year" description="Personal projects & internal tools" price="$79" title="Solo">



</u-pricing-plan>

<u-pricing-plan :button="{"label":"Purchase","to":"https://relaticle.lemonsqueezy.com/buy/803d5933-4b12-4869-9d93-f96797339603","target":"_blank"}" :features="["Up to 5 domains","1 year updates"]" billing-cycle="/year" description="Agencies & multiple client sites" price="$129" title="Pro">



</u-pricing-plan>

<u-pricing-plan :button="{"label":"Purchase","to":"https://relaticle.lemonsqueezy.com/buy/803d5933-4b12-4869-9d93-f96797339603","target":"_blank"}" :features="["Unlimited domains","1 year updates"]" billing-cycle="/year" description="Required for SaaS & multi-tenant" price="$299" title="Business" :highlight="true">



</u-pricing-plan>
</u-pricing-plans>
</div>
</u-page-section>

<u-page-section>
<template v-slot:title="">

Our Ecosystem

</template>

<template v-slot:description="">

Extend your Laravel applications with our ecosystem of complementary tools

</template>

<card-group>
<card icon="i-simple-icons-laravel" target="_blank" title="FilaForms" to="https://filaforms.app">

![FilaForms](https://filaforms.app/img/og-image.png)Visual form builder for all your public-facing forms.

</card>

<card icon="i-lucide-columns-3" target="_blank" title="Flowforge" to="https://relaticle.github.io/flowforge/">

![Flowforge](https://relaticle.github.io/flowforge/og-image.png)Transform any Laravel model into drag-and-drop Kanban boards.

</card>
</card-group>
</u-page-section>
