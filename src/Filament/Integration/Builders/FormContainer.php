<?php

namespace Relaticle\CustomFields\Filament\Integration\Builders;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;

final class FormContainer extends Grid
{
    private Model|string|null $explicitModel = null;

    private array $except = [];

    private array $only = [];

    /** @var array<int, int> */
    private array $onlySections = [];

    private ?bool $withoutSections = null;

    /** @var array<string, CustomField> */
    private array $renderedFields = [];

    public static function make(array|int|null $columns = 12): static
    {
        $container = new self($columns);

        // Defer schema generation until component is in container. Resolve the
        // component Filament is evaluating instead of capturing `$container`:
        // cloning a component copies this closure by reference, so a captured
        // `$container` would keep generating the clone's schema from the
        // original — which Filament never assigns a container to.
        $container->schema(static fn (self $component): array => $component->generateSchema());

        return $container;
    }

    public function forModel(Model|string|null $model): static
    {
        $this->explicitModel = $model;

        return $this;
    }

    public function except(array $fieldCodes): static
    {
        $this->except = $fieldCodes;

        return $this;
    }

    public function only(array $fieldCodes): static
    {
        $this->only = $fieldCodes;

        return $this;
    }

    /**
     * @param  array<int, int>  $sectionIds
     */
    public function onlySections(array $sectionIds): static
    {
        $this->onlySections = $sectionIds;

        return $this;
    }

    public function withoutSections(bool $withoutSections = true): static
    {
        $this->withoutSections = $withoutSections;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function dehydrateState(array &$state, bool $isDehydrated = true): void
    {
        parent::dehydrateState($state, $isDehydrated);

        if (! $isDehydrated || ! $this->isDehydrated()) {
            return;
        }

        $record = $this->explicitModel instanceof Model ? $this->explicitModel : $this->getRecord();

        if (! $record instanceof HasCustomFields || ! $record->exists) {
            return;
        }

        $hiddenFields = collect($this->getChildSchemas(withHidden: true))
            ->flatMap(fn (Schema $schema): array => $schema->getFlatFields(withHidden: true))
            ->filter(fn (Field $field): bool => isset($this->renderedFields[$field->getName()]) && $this->isHiddenWithin($field));

        // Filament drops a hidden field from the submitted data, and the record would store null for it.
        // The user never saw it, so it keeps what is stored, whatever the browser sent.
        $hiddenFields->each(function (Field $field) use ($record, &$state): void {
            data_set($state, $field->getStatePath(), $record->getCustomFieldValue($this->renderedFields[$field->getName()]));
        });
    }

    private function isHiddenWithin(Component $component): bool
    {
        while ($component instanceof Component && $component !== $this) {
            if ($component->isHidden()) {
                return true;
            }

            $component = $component->getContainer()->getParentComponent();
        }

        return false;
    }

    private function generateSchema(): array
    {
        // Inline priority: explicit ?? record ?? model class
        $model = $this->explicitModel ?? $this->getRecord() ?? $this->getModel();

        if ($model === null) {
            return []; // Graceful fallback
        }

        // Use explicit setting if provided, otherwise check feature flag
        $withoutSections = $this->withoutSections
            ?? ! FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_SECTIONS);

        $builder = app(FormBuilder::class)
            ->forModel($model)
            ->withoutSections($withoutSections)
            ->only($this->only)
            ->except($this->except)
            ->onlySections($this->onlySections);

        $components = $builder->values()->toArray();

        $this->renderedFields = $builder->fields()
            ->keyBy(fn (CustomField $customField): string => $customField->getFieldName())
            ->all();

        return $components;
    }
}
