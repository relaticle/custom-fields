<?php

declare(strict_types=1);

namespace Relaticle\CustomFields;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Data\WriteContext;
use Relaticle\CustomFields\Enums\ImportDateFormat;
use Relaticle\CustomFields\Enums\ImportNumberFormat;
use Relaticle\CustomFields\Enums\WriteSource;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldOption;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Models\CustomFieldValue;
use Relaticle\CustomFields\Services\TenantContextService;

final class CustomFields
{
    /**
     * The custom field model that should be used by Custom Fields.
     *
     * @var class-string<CustomField>
     */
    public static string $customFieldModel = CustomField::class;

    /**
     * The custom field value model that should be used by Custom Fields.
     *
     * @var class-string<CustomFieldValue>
     */
    public static string $valueModel = CustomFieldValue::class;

    /**
     * The custom field option model that should be used by Custom Fields.
     *
     * @var class-string<CustomFieldOption>
     */
    public static string $optionModel = CustomFieldOption::class;

    /**
     * The custom field section model that should be used by Custom Fields.
     *
     * @var class-string<CustomFieldSection>
     */
    public static string $sectionModel = CustomFieldSection::class;

    /**
     * The relationship definition model that should be used by Custom Fields.
     *
     * @var class-string<CustomFieldRelationship>
     */
    public static string $relationshipModel = CustomFieldRelationship::class;

    /**
     * The relationship link model that should be used by Custom Fields.
     *
     * @var class-string<CustomFieldLink>
     */
    public static string $linkModel = CustomFieldLink::class;

    /**
     * The display format for date fields (e.g., 'm/d/Y', 'Y-m-d', 'd.m.Y').
     *
     * When null, each component uses its own default format.
     */
    public static ?string $dateDisplayFormat = null;

    /**
     * The display format for date-time fields (e.g., 'm/d/Y h:i A', 'Y-m-d H:i:s').
     *
     * When null, each component uses its own default format.
     */
    public static ?string $dateTimeDisplayFormat = null;

    private static ?WriteContext $writeContext = null;

    /** @var (Closure(string, string, int|string|null): ((Closure(Builder<covariant Model>): Builder<Model>)|null))|null */
    private static ?Closure $codeUniquenessScope = null;

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
    public static function newCustomFieldModel(): CustomField
    {
        $model = self::customFieldModel();

        return new $model;
    }

    /**
     * Specify the custom field model that should be used by Custom Fields.
     *
     * @param  class-string<CustomField>  $model
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
    public static function newValueModel(): CustomFieldValue
    {
        $model = self::valueModel();

        return new $model;
    }

    /**
     * Specify the custom field value model that should be used by Custom Fields.
     *
     * @param  class-string<CustomFieldValue>  $model
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
    public static function newOptionModel(): CustomFieldOption
    {
        $model = self::optionModel();

        return new $model;
    }

    public static function optionModelUsesStringKeys(): bool
    {
        $optionModel = self::newOptionModel();

        return $optionModel->getKeyType() === 'string';
    }

    /**
     * Specify the custom field option model that should be used by Custom Fields.
     *
     * @param  class-string<CustomFieldOption>  $model
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
    public static function newSectionModel(): CustomFieldSection
    {
        $model = self::sectionModel();

        return new $model;
    }

    /**
     * Specify the custom field section model that should be used by Custom Fields.
     *
     * @param  class-string<CustomFieldSection>  $model
     */
    public static function useSectionModel(string $model): static
    {
        self::$sectionModel = $model;

        return new self;
    }

    /**
     * Get the name of the relationship definition model used by the application.
     *
     * @return class-string<CustomFieldRelationship>
     */
    public static function relationshipModel(): string
    {
        return self::$relationshipModel;
    }

    /**
     * Get a new instance of the relationship definition model.
     */
    public static function newRelationshipModel(): CustomFieldRelationship
    {
        $model = self::relationshipModel();

        return new $model;
    }

    /**
     * Specify the relationship definition model that should be used by Custom Fields.
     *
     * @param  class-string<CustomFieldRelationship>  $model
     */
    public static function useRelationshipModel(string $model): static
    {
        self::$relationshipModel = $model;

        return new self;
    }

    /**
     * Get the name of the relationship link model used by the application.
     *
     * @return class-string<CustomFieldLink>
     */
    public static function linkModel(): string
    {
        return self::$linkModel;
    }

    /**
     * Get a new instance of the relationship link model.
     */
    public static function newLinkModel(): CustomFieldLink
    {
        $model = self::linkModel();

        return new $model;
    }

    /**
     * Specify the relationship link model that should be used by Custom Fields.
     *
     * @param  class-string<CustomFieldLink>  $model
     */
    public static function useLinkModel(string $model): static
    {
        self::$linkModel = $model;

        return new self;
    }

    /**
     * Set the display format for date custom fields.
     *
     * This format is used across all Filament components (forms, tables, infolists)
     * when rendering date custom field values.
     *
     * Example:
     * ```
     * CustomFields::useDateDisplayFormat('m/d/Y');
     * ```
     */
    public static function useDateDisplayFormat(?string $format): static
    {
        self::$dateDisplayFormat = $format;

        return new self;
    }

    /**
     * Set the display format for date-time custom fields.
     *
     * This format is used across all Filament components (forms, tables, infolists)
     * when rendering date-time custom field values.
     *
     * Example:
     * ```
     * CustomFields::useDateTimeDisplayFormat('m/d/Y h:i A');
     * ```
     */
    public static function useDateTimeDisplayFormat(?string $format): static
    {
        self::$dateTimeDisplayFormat = $format;

        return new self;
    }

    /**
     * Get the configured date display format, or null for component defaults.
     */
    public static function dateDisplayFormat(): ?string
    {
        return self::$dateDisplayFormat;
    }

    /**
     * Get the configured date-time display format, or null for component defaults.
     */
    public static function dateTimeDisplayFormat(): ?string
    {
        return self::$dateTimeDisplayFormat;
    }

    /**
     * Date convention used when reading CSV cells during import.
     */
    public static function importDateFormat(): ImportDateFormat
    {
        return ImportDateFormat::tryFrom(
            (string) config('custom-fields.imports.date_format', 'iso')
        ) ?? ImportDateFormat::ISO;
    }

    /**
     * Decimal separator convention used when reading CSV cells during import.
     */
    public static function importNumberFormat(): ImportNumberFormat
    {
        return ImportNumberFormat::tryFrom(
            (string) config('custom-fields.imports.number_format', 'point')
        ) ?? ImportNumberFormat::POINT;
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

    /**
     * Narrow the generated-code uniqueness check to a subset of rows.
     *
     * The callback receives the entity type, either 'field' or 'section', and the section
     * the code is being generated within (null when there is none). It returns a query
     * scope closure, or null to leave the check global.
     *
     * Example:
     * ```
     * CustomFields::scopeCodeUniquenessUsing(fn (string $entityType, string $type, int|string|null $sectionId) => null);
     * ```
     *
     * @param  Closure(string $entityType, string $type, int|string|null $sectionId): ((Closure(Builder<covariant Model>): Builder<Model>)|null)  $scope
     */
    public static function scopeCodeUniquenessUsing(Closure $scope): void
    {
        self::$codeUniquenessScope = $scope;
    }

    /**
     * @return (Closure(string, string, int|string|null): ((Closure(Builder<covariant Model>): Builder<Model>)|null))|null
     */
    public static function codeUniquenessScope(): ?Closure
    {
        return self::$codeUniquenessScope;
    }

    /**
     * Stamp every write made inside the callback with where it came from.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function writing(WriteSource $source, Closure $callback, ?Model $actor = null, ?float $confidence = null): mixed
    {
        $previous = self::$writeContext;
        self::$writeContext = new WriteContext($source, $actor ?? self::authenticatedActor(), $confidence);

        try {
            return $callback();
        } finally {
            self::$writeContext = $previous;
        }
    }

    public static function writeContext(): WriteContext
    {
        return self::$writeContext ?? new WriteContext(WriteSource::User, self::authenticatedActor());
    }

    private static function authenticatedActor(): ?Model
    {
        $user = auth()->user();

        return $user instanceof Model ? $user : null;
    }
}
