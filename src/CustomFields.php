<?php

declare(strict_types=1);

namespace Relaticle\CustomFields;

use Closure;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldOption;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Models\CustomFieldValue;
use Relaticle\CustomFields\Services\TenantContextService;

final class CustomFields
{
    /**
     * The custom field model that should be used by Custom Fields.
     */
    public static string $customFieldModel = CustomField::class;

    /**
     * The custom field value model that should be used by Custom Fields.
     */
    public static string $valueModel = CustomFieldValue::class;

    /**
     * The custom field option model that should be used by Custom Fields.
     */
    public static string $optionModel = CustomFieldOption::class;

    /**
     * The custom field section model that should be used by Custom Fields.
     */
    public static string $sectionModel = CustomFieldSection::class;

    /**
     * Get the name of the custom field model used by the application.
     *
     * @return class-string<CustomField>
     */
    public static function customFieldModel(): string
    {
        return self::$customFieldModel;
    }

    /**
     * Get a new instance of the custom field model.
     */
    public static function newCustomFieldModel(): mixed
    {
        $model = self::customFieldModel();

        return new $model;
    }

    /**
     * Specify the custom field model that should be used by Custom Fields.
     */
    public static function useCustomFieldModel(string $model): static
    {
        self::$customFieldModel = $model;

        return new self;
    }

    /**
     * Get the name of the custom field value model used by the application.
     *
     * @return class-string<CustomFieldValue>
     */
    public static function valueModel(): string
    {
        return self::$valueModel;
    }

    /**
     * Get a new instance of the custom field value model.
     */
    public static function newValueModel(): mixed
    {
        $model = self::valueModel();

        return new $model;
    }

    /**
     * Specify the custom field value model that should be used by Custom Fields.
     */
    public static function useValueModel(string $model): static
    {
        self::$valueModel = $model;

        return new self;
    }

    /**
     * Get the name of the custom field option model used by the application.
     *
     * @return class-string<CustomFieldOption>
     */
    public static function optionModel(): string
    {
        return self::$optionModel;
    }

    /**
     * Get a new instance of the custom field option model.
     */
    public static function newOptionModel(): mixed
    {
        $model = self::optionModel();

        return new $model;
    }

    /**
     * Specify the custom field option model that should be used by Custom Fields.
     */
    public static function useOptionModel(string $model): static
    {
        self::$optionModel = $model;

        return new self;
    }

    /**
     * Get the name of the custom field section model used by the application.
     *
     * @return class-string<CustomFieldSection>
     */
    public static function sectionModel(): string
    {
        return self::$sectionModel;
    }

    /**
     * Get a new instance of the custom field section model.
     */
    public static function newSectionModel(): mixed
    {
        $model = self::sectionModel();

        return new $model;
    }

    /**
     * Specify the custom field section model that should be used by Custom Fields.
     */
    public static function useSectionModel(string $model): static
    {
        self::$sectionModel = $model;

        return new self;
    }

    /**
     * Register a custom tenant resolver callback.
     *
     * This allows developers to provide their own tenant resolution logic
     * when they extend the CustomField models with custom tenant handling.
     *
     * The resolver will be called whenever the package needs to determine
     * the current tenant context (validation, queries, scopes, etc.).
     *
     * Example:
     * ```
     * CustomFields::resolveTenantUsing(fn() => auth()->user()?->company_id);
     * ```
     *
     * @param  Closure(): (int|string|null)  $callback
     */
    public static function resolveTenantUsing(Closure $callback): void
    {
        TenantContextService::setTenantResolver($callback);
    }
}
