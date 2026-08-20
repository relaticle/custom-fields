<?php

declare(strict_types=1);

use Relaticle\CustomFields\EntitySystem\EntityConfigurator;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\FieldTypeSystem\FieldTypeConfigurator;

return [
    /*
    |--------------------------------------------------------------------------
    | Entity Configuration
    |--------------------------------------------------------------------------
    |
    | Configure entities (models that can have custom fields) using the
    | clean, type-safe fluent builder interface.
    |
    */
    'entity_configuration' => EntityConfigurator::configure()
        ->discover(app_path('Models'))
        ->cache(false),

    /*
    |--------------------------------------------------------------------------
    | Advanced Field Type Configuration
    |--------------------------------------------------------------------------
    |
    | Configure field types using the powerful fluent builder API.
    | This provides advanced control over validation, security, and behavior.
    |
    */
    'field_type_configuration' => FieldTypeConfigurator::configure()
        // Control which field types are available globally
        ->enabled([]) // Empty = all enabled, or specify: ['text', 'email', 'select']
        ->disabled(['file-upload']) // Disable specific field types
        ->discover(true)
        ->cache(enabled: false, ttl: 3400),

    /*
    |--------------------------------------------------------------------------
    | Features Configuration
    |--------------------------------------------------------------------------
    |
    | Configure package features using the type-safe enum-based configurator.
    | This consolidates all feature settings into a single, organized system.
    |
    */
    'features' => FeatureConfigurator::configure()
        ->enable(
            CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY,
            CustomFieldsFeature::FIELD_ENCRYPTION,
            CustomFieldsFeature::FIELD_OPTION_COLORS,
            CustomFieldsFeature::UI_TABLE_COLUMNS,
            CustomFieldsFeature::UI_TOGGLEABLE_COLUMNS,
            CustomFieldsFeature::UI_TABLE_FILTERS,
            CustomFieldsFeature::FIELD_DESCRIPTION,
            CustomFieldsFeature::UI_FIELD_WIDTH_CONTROL,
            CustomFieldsFeature::SYSTEM_MANAGEMENT_INTERFACE,
            CustomFieldsFeature::SYSTEM_SECTIONS,
        )
        ->disable(
            CustomFieldsFeature::SYSTEM_MULTI_TENANCY,
        ),

    /*
    |--------------------------------------------------------------------------
    | Management Interface Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the Custom Fields management interface in Filament.
    | Only applies when SYSTEM_MANAGEMENT_INTERFACE feature is enabled.
    |
    */
    'management' => [
        'slug' => 'custom-fields',
        'navigation_sort' => -1,
        'navigation_group' => true,
        'cluster' => null,

        // Width of the add/edit section modal. Accepts a Filament\Support\Enums\Width case or its
        // string value (e.g. 'screen-lg'). Null falls back to a width based on conditional visibility.
        // Overridable per panel via CustomFieldsPlugin::make()->sectionModalWidth(...).
        'section_modal_width' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Settings
    |--------------------------------------------------------------------------
    |
    | Configure default settings for custom fields.
    |
    */
    'fields' => [
        'description_max_length' => 255,
    ],

    /*
    |--------------------------------------------------------------------------
    | Section Settings
    |--------------------------------------------------------------------------
    |
    | Configure default settings for custom field sections.
    |
    */
    'sections' => [
        'description_max_length' => 255,
    ],

    /*
    |--------------------------------------------------------------------------
    | Select & Record Lookup Behavior
    |--------------------------------------------------------------------------
    |
    | searchable_threshold controls when option-backed selects render a search
    | box. Set it to 0 to always show one, which is the pre-3.8 behavior.
    |
    | record_lookup governs the record-select field's initial page and search.
    | order_column must be a real column on the looked-up model; when it is left
    | at the default and the model does not use timestamps, the model key is used
    | instead so the order is always deterministic.
    |
    */
    'selects' => [
        'searchable_threshold' => 10,

        'record_lookup' => [
            'order_column' => 'updated_at',
            'order_direction' => 'desc',
            'limit' => 50,
            'min_search_length' => 2,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configure database table names and migration paths.
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Currency Configuration
    |--------------------------------------------------------------------------
    |
    | Default currency settings for currency field types.
    |
    */
    'currency' => [
        'default_code' => env('CUSTOM_FIELDS_DEFAULT_CURRENCY', 'USD'),

        // Override the currency list (null = auto-detect from PHP intl/ICU).
        // Format: ['USD' => 'US Dollar', 'EUR' => 'Euro', ...]
        'currencies' => null,
    ],

    'database' => [
        'migrations_path' => database_path('custom-fields'),
        'table_names' => [
            'custom_field_sections' => 'custom_field_sections',
            'custom_fields' => 'custom_fields',
            'custom_field_values' => 'custom_field_values',
            'custom_field_options' => 'custom_field_options',
        ],
        'column_names' => [
            'tenant_foreign_key' => 'tenant_id',
        ],
    ],
];
