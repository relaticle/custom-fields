<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\EntitySystem;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Relaticle\CustomFields\Data\AvatarConfiguration;
use Relaticle\CustomFields\Enums\AvatarShape;
use Relaticle\CustomFields\Enums\EntityFeature;

/**
 * Array-based entity configuration factory
 * Provides type-safe configuration using class strings and enums
 */
final class EntityModel
{
    /**
     * Create entity configuration array from model class
     */
    public static function for(string $modelClass): array
    {
        self::validateModelClass($modelClass);

        return self::setSmartDefaults($modelClass);
    }

    /**
     * Configure entity with custom settings
     */
    public static function configure(
        string $modelClass,
        ?string $alias = null,
        ?string $labelSingular = null,
        ?string $labelPlural = null,
        string $icon = 'heroicon-o-document',
        string $primaryAttribute = 'id',
        array $searchAttributes = [],
        ?string $resourceClass = null,
        ?string $recordPage = 'view',
        array $features = [EntityFeature::CUSTOM_FIELDS, EntityFeature::LOOKUP_SOURCE],
        int $priority = 999,
        array $metadata = [],
        ?AvatarConfiguration $avatarConfiguration = null,
    ): array {
        self::validateModelClass($modelClass);

        if ($resourceClass && ! class_exists($resourceClass)) {
            throw new InvalidArgumentException(sprintf('Resource class %s does not exist', $resourceClass));
        }

        return [
            'modelClass' => $modelClass,
            'alias' => $alias, // null if not provided, resolved lazily by EntityConfigurator
            'labelSingular' => $labelSingular ?? Str::headline(class_basename($modelClass)),
            'labelPlural' => $labelPlural ?? Str::plural($labelSingular ?? Str::headline(class_basename($modelClass))),
            'icon' => $icon,
            'primaryAttribute' => $primaryAttribute,
            'searchAttributes' => $searchAttributes !== [] ? $searchAttributes : self::guessSearchAttributes($primaryAttribute),
            'resourceClass' => $resourceClass,
            'recordPage' => $recordPage,
            'features' => array_map(fn (mixed $feature) => $feature instanceof EntityFeature ? $feature->value : $feature, $features),
            'priority' => max(0, $priority),
            'metadata' => $metadata,
            'avatarConfiguration' => $avatarConfiguration,
        ];
    }

    /**
     * Create avatar configuration for an entity
     */
    public static function avatar(?string $attribute = null, AvatarShape $shape = AvatarShape::Circle): AvatarConfiguration
    {
        return new AvatarConfiguration(
            attribute: $attribute,
            shape: $shape,
        );
    }

    /**
     * Validate the model class is valid
     */
    private static function validateModelClass(string $modelClass): void
    {
        if (! class_exists($modelClass)) {
            throw new InvalidArgumentException(sprintf('Model class %s does not exist', $modelClass));
        }

        if (! is_subclass_of($modelClass, Model::class)) {
            throw new InvalidArgumentException(sprintf('Class %s must extend ', $modelClass).Model::class);
        }
    }

    /**
     * Set smart defaults based on the model class
     * Icon is resolved lazily in EntityConfigurationData::getIcon() at runtime
     */
    private static function setSmartDefaults(string $modelClass): array
    {
        /** @var Model $model */
        $model = new $modelClass;

        $baseName = class_basename($modelClass);
        $labelSingular = Str::headline($baseName);
        $primaryAttribute = self::guessPrimaryAttribute($model);

        return [
            'modelClass' => $modelClass,
            'alias' => null, // Resolved lazily by EntityConfigurator
            'labelSingular' => $labelSingular,
            'labelPlural' => Str::plural($labelSingular),
            'icon' => 'heroicon-o-document', // Resolved lazily from Filament resource in EntityConfigurationData::getIcon()
            'primaryAttribute' => $primaryAttribute,
            'searchAttributes' => self::guessSearchAttributes($primaryAttribute),
            'resourceClass' => null,
            'recordPage' => 'view',
            'features' => [EntityFeature::CUSTOM_FIELDS->value, EntityFeature::LOOKUP_SOURCE->value],
            'priority' => 999,
            'metadata' => [],
            'avatarConfiguration' => null,
        ];
    }

    /**
     * Guess the best primary attribute for this model
     */
    private static function guessPrimaryAttribute(Model $model): string
    {
        $fillable = $model->getFillable();

        // Check common title attributes
        foreach (['name', 'title', 'label', 'display_name'] as $attribute) {
            if (in_array($attribute, $fillable, true)) {
                return $attribute;
            }
        }

        return 'id';
    }

    /**
     * Guess the best search attributes for this model
     */
    private static function guessSearchAttributes(string $primaryAttribute): array
    {
        // If we found a good primary attribute, use it for search
        if ($primaryAttribute !== 'id') {
            return [$primaryAttribute];
        }

        return [];
    }
}
