<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Management\Forms\Components\Visibility;

use Closure;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Enums\ConditionSource;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Services\ModelAttributeDiscoveryService;
use Relaticle\CustomFields\Services\RelationConditionResolver;
use Relaticle\CustomFields\Services\Visibility\BackendVisibilityService;
use Relaticle\CustomFields\Support\RelationConditionConfig;

final class ConditionOptions
{
    /** @var ?Closure(string, ?CustomFieldSection): ?Closure */
    private static ?Closure $availableFieldsScopeResolver = null;

    /**
     * @param  ?CustomFieldSection  $scopeSection  The section the conditioned field/section belongs
     *                                             to. When a consumer registers a scope resolver,
     *                                             this is handed to it so the "depends on" picker
     *                                             can be constrained to a subset (e.g. only fields
     *                                             in the same parent form). Null applies no scope.
     */
    public function __construct(
        private bool $forSection = false,
        private ?string $sectionEntityType = null,
        private ?CustomFieldSection $scopeSection = null,
    ) {}

    /**
     * Register a resolver that constrains the conditional-visibility "depends on" field
     * picker. The resolver receives the entity type and the section the conditioned
     * field/section belongs to, and returns a query constraint closure, or null for no
     * scope. Register once (e.g. from a service provider).
     *
     * @param  ?Closure(string $entityType, ?CustomFieldSection $section): ?Closure  $resolver
     */
    public static function resolveAvailableFieldsScopeUsing(?Closure $resolver): void
    {
        self::$availableFieldsScopeResolver = $resolver;
    }

    /**
     * @return array<string, string>
     */
    public function getAvailableSourceOptions(Get $get): array
    {
        $entityType = $this->getEntityType($get);

        $options = [
            ConditionSource::CustomField->value => ConditionSource::CustomField->getLabel(),
        ];

        if (FeatureManager::isEnabled(CustomFieldsFeature::MODEL_ATTRIBUTE_CONDITIONS)) {
            $options[ConditionSource::ModelAttribute->value] = ConditionSource::ModelAttribute->getLabel();
        }

        if (! blank($entityType) && app(RelationConditionConfig::class)->isRelationSourceAvailable($entityType)) {
            $options[ConditionSource::RelationAttribute->value] = ConditionSource::RelationAttribute->getLabel();
        }

        return $options;
    }

    private function sourceIs(Get $get, ConditionSource $expected): bool
    {
        $source = $get('source');

        if ($source instanceof ConditionSource) {
            return $source === $expected;
        }

        return $source === $expected->value;
    }

    public function isModelAttributeSource(Get $get): bool
    {
        return $this->sourceIs($get, ConditionSource::ModelAttribute);
    }

    public function isRelationAttributeSource(Get $get): bool
    {
        return $this->sourceIs($get, ConditionSource::RelationAttribute);
    }

    /**
     * @return array<string, string>
     */
    public function getAvailableFields(Get $get): array
    {
        $entityType = $this->getEntityType($get);
        if (blank($entityType)) {
            return [];
        }

        if ($this->isRelationAttributeSource($get)) {
            return app(RelationConditionConfig::class)->relationsFor($entityType);
        }

        if ($this->isModelAttributeSource($get)) {
            return rescue(
                fn (): array => app(ModelAttributeDiscoveryService::class)->getAttributeOptions($entityType),
                []
            );
        }

        $currentFieldCode = $this->forSection ? null : $get('../../../../code');
        $scopeSection = $this->scopeSection;
        $scopeResolver = self::$availableFieldsScopeResolver;

        return rescue(function () use ($entityType, $currentFieldCode, $scopeSection, $scopeResolver) {
            $query = CustomFields::customFieldModel()::query()
                ->forMorphEntity($entityType)
                ->when($currentFieldCode, fn (mixed $query) => $query->where('code', '!=', $currentFieldCode));

            if ($scopeResolver instanceof Closure) {
                $constraint = $scopeResolver($entityType, $scopeSection);

                if ($constraint instanceof Closure) {
                    $query = $constraint($query) ?? $query;
                }
            }

            return $query->orderBy('name')
                ->pluck('name', 'code')
                ->toArray();
        }, []);
    }

    /**
     * @return array<string, string>
     */
    public function getCompatibleOperators(Get $get): array
    {
        if ($this->isRelationAttributeSource($get)) {
            return [
                VisibilityOperator::IS_IN->value => VisibilityOperator::IS_IN->getLabel(),
                VisibilityOperator::IS_NOT_IN->value => VisibilityOperator::IS_NOT_IN->getLabel(),
            ];
        }

        if ($this->isModelAttributeSource($get)) {
            return collect(VisibilityOperator::options())
                ->except([VisibilityOperator::IS_IN->value, VisibilityOperator::IS_NOT_IN->value])
                ->all();
        }

        $fieldData = $this->getFieldTypeData($get);

        return $fieldData
            ? $fieldData->getCompatibleOperatorOptions()
            : collect(VisibilityOperator::options())
                ->except([VisibilityOperator::IS_IN->value, VisibilityOperator::IS_NOT_IN->value])
                ->all();
    }

    /**
     * @return array<string, string>
     */
    public function getFieldOptions(Get $get): array
    {
        if ($this->isModelAttributeSource($get)) {
            return [];
        }

        $fieldCode = $get('field_code');
        if (blank($fieldCode)) {
            return [];
        }

        $entityType = $this->getEntityType($get);
        if (blank($entityType)) {
            return [];
        }

        return rescue(function () use ($fieldCode, $entityType) {
            return app(BackendVisibilityService::class)
                ->getFieldOptions($fieldCode, $entityType);
        }, []);
    }

    /**
     * @return array<int|string, string>
     */
    public function getRelationValueOptions(Get $get): array
    {
        $path = $get('field_code');

        if (blank($path)) {
            return [];
        }

        $entityType = $this->getEntityType($get);
        if (blank($entityType)) {
            return [];
        }

        $related = app(RelationConditionResolver::class)->resolveTerminalRelatedModel($entityType, (string) $path);

        if (! $related instanceof Model) {
            return [];
        }

        static $labelColumns = [];
        $modelClass = $related::class;
        if (! isset($labelColumns[$modelClass])) {
            $labelColumns[$modelClass] = collect(['name', 'title', 'label'])
                ->first(fn (string $column): bool => $related->getConnection()->getSchemaBuilder()->hasColumn($related->getTable(), $column))
                ?? $related->getKeyName();
        }

        $labelColumn = $labelColumns[$modelClass];

        return $related::query()->pluck($labelColumn, $related->getKeyName())->all();
    }

    public function getFieldTypeData(Get $get): ?object
    {
        $fieldCode = $get('field_code');
        if (blank($fieldCode)) {
            return null;
        }

        $field = $this->getCustomField($fieldCode, $get);
        if (! $field instanceof CustomField) {
            return null;
        }

        return rescue(
            fn () => CustomFieldsType::getFieldType($field->type)
        );
    }

    private function getCustomField(string $fieldCode, Get $get): ?CustomField
    {
        $entityType = $this->getEntityType($get);
        if (blank($entityType)) {
            return null;
        }

        return rescue(function () use ($entityType, $fieldCode) {
            return CustomFields::customFieldModel()::query()
                ->forMorphEntity($entityType)
                ->where('code', $fieldCode)
                ->first();
        });
    }

    public function getEntityType(?Get $get = null): ?string
    {
        if ($this->forSection && $this->sectionEntityType) {
            return $this->sectionEntityType;
        }

        return ($get instanceof Get ? $get('../../../../entity_type') : null)
            ?? request('entityType')
            ?? request()->route('entityType');
    }
}
