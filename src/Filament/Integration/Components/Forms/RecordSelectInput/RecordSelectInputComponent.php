<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms\RecordSelectInput;

use Closure;
use Filament\Forms\Components\Concerns\HasNestedRecursiveValidationRules;
use Filament\Forms\Components\Concerns\HasPlaceholder;
use Filament\Forms\Components\Contracts\HasNestedRecursiveValidationRules as HasNestedRecursiveValidationRulesContract;
use Filament\Forms\Components\Field;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Filament\Support\Concerns\HasExtraAlpineAttributes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Livewire\Attributes\Renderless;
use Relaticle\CustomFields\Data\AvatarConfiguration;
use Relaticle\CustomFields\Data\EntityConfigurationData;
use Relaticle\CustomFields\Facades\Entities;
use Relaticle\CustomFields\Support\Utils;
use Throwable;

/**
 * A custom Filament form field for selecting records from other entities
 * with search, avatars, and single/multiple mode support.
 *
 * Single value: Shows a searchable select dropdown
 * Multiple values: Shows pills + add button with searchable dropdown
 */
class RecordSelectInputComponent extends Field implements HasNestedRecursiveValidationRulesContract
{
    use HasExtraAlpineAttributes;
    use HasNestedRecursiveValidationRules;
    use HasPlaceholder;

    protected string $view = 'custom-fields::forms.record-select-input';

    protected bool|Closure $allowMultiple = false;

    protected int|Closure $maxValues = 1;

    protected string|Closure|null $lookupType = null;

    protected string|Closure|null $addLabel = null;

    protected string|Closure|null $emptyStateLabel = null;

    protected int|Closure $maxVisiblePills = 3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);

        $this->afterStateHydrated(static function (RecordSelectInputComponent $component, mixed $state): void {
            if (is_array($state)) {
                return;
            }

            if ($state === null) {
                $component->state([]);

                return;
            }

            // Convert single value to array
            $component->state([$state]);
        });

        $this->dehydrateStateUsing(static function (RecordSelectInputComponent $component, mixed $state): array {
            if (! is_array($state)) {
                return $state !== null ? [$state] : [];
            }

            // Filter out empty values
            return array_values(array_filter($state, fn (mixed $value): bool => filled($value)));
        });
    }

    public function allowMultiple(bool|Closure $allow = true): static
    {
        $this->allowMultiple = $allow;

        return $this;
    }

    public function getAllowMultiple(): bool
    {
        return $this->evaluate($this->allowMultiple);
    }

    public function maxValues(int|Closure $max): static
    {
        $this->maxValues = $max;

        return $this;
    }

    public function getMaxValues(): int
    {
        return $this->evaluate($this->maxValues);
    }

    public function lookupType(string|Closure|null $type): static
    {
        $this->lookupType = $type;

        return $this;
    }

    public function getLookupType(): ?string
    {
        return $this->evaluate($this->lookupType);
    }

    public function addLabel(string|Closure|null $label): static
    {
        $this->addLabel = $label;

        return $this;
    }

    public function getAddLabel(): string
    {
        return $this->evaluate($this->addLabel) ?? __('custom-fields::custom-fields.common.add');
    }

    public function emptyStateLabel(string|Closure|null $label): static
    {
        $this->emptyStateLabel = $label;

        return $this;
    }

    public function getEmptyStateLabel(): string
    {
        return $this->evaluate($this->emptyStateLabel) ?? $this->getPlaceholder() ?? __('custom-fields::custom-fields.record.empty_state_label');
    }

    public function maxVisiblePills(int|Closure $max): static
    {
        $this->maxVisiblePills = $max;

        return $this;
    }

    public function getMaxVisiblePills(): int
    {
        return $this->evaluate($this->maxVisiblePills);
    }

    /**
     * Characters required before the field issues a filtered lookup query.
     *
     * The view reads the same value, so raising it cannot leave the client
     * asking for a filtered search the server answers with an unfiltered page.
     */
    public function getMinSearchLength(): int
    {
        return (int) config('custom-fields.selects.record_lookup.min_search_length', 2);
    }

    /**
     * Get entity configuration for the lookup type.
     */
    public function getEntityConfiguration(): ?EntityConfigurationData
    {
        $lookupType = $this->getLookupType();

        if ($lookupType === null) {
            return null;
        }

        return Entities::getEntity($lookupType);
    }

    /**
     * Prepare entity query with common attributes.
     *
     * @return array{entity: EntityConfigurationData, model: Model, query: Builder, keyName: string, titleAttribute: string, avatarConfig: ?AvatarConfiguration}|null
     */
    private function prepareEntityQuery(): ?array
    {
        $entity = $this->getEntityConfiguration();

        if (! $entity instanceof EntityConfigurationData) {
            return null;
        }

        $model = $entity->createModelInstance();

        return [
            'entity' => $entity,
            'model' => $model,
            'query' => $model->newQuery(),
            'keyName' => $model->getKeyName(),
            'titleAttribute' => $entity->getPrimaryAttribute(),
            'avatarConfig' => $entity->getAvatarConfiguration(),
        ];
    }

    /**
     * Apply a deterministic order to a lookup query.
     *
     * Without one, LIMIT returns arbitrary rows and the initial page can change
     * between renders. The default is the model key: it is backed by the primary
     * key index, so ordering costs no more than the unordered query it replaces.
     * Measured on a 50k-row table, ordering by an unindexed column instead costs
     * roughly 176x the time and 205x the buffers, because every render sorts the
     * whole tenant.
     *
     * A configured column is trusted and the model key is appended to it, so rows
     * sharing a value still come back in a fixed sequence. Column existence is
     * resolved without a schema query: a runtime Schema::hasColumn() call would be
     * a per-request round trip. The one exception is the documented 'updated_at',
     * which falls back to the key on a model that opts out of timestamps.
     */
    private function applyLookupOrder(Builder $query, Model $model): Builder
    {
        $column = config('custom-fields.selects.record_lookup.order_column');
        $direction = (string) config('custom-fields.selects.record_lookup.order_direction', 'desc');
        $key = $model->getQualifiedKeyName();

        if (! is_string($column) || $column === '') {
            return $query->orderBy($key, $direction);
        }

        if ($column === 'updated_at' && ! $model->usesTimestamps()) {
            return $query->orderBy($key, $direction);
        }

        return $query
            ->orderBy($query->qualifyColumn($column), $direction)
            ->orderBy($key, $direction);
    }

    private function lookupLimit(): int
    {
        return (int) config('custom-fields.selects.record_lookup.limit', 50);
    }

    /**
     * Search for records matching the query.
     *
     * @return array<string, array{id: string, label: string, avatar: ?string, avatarShape: string}>
     */
    public function searchRecords(string $search): array
    {
        $prepared = $this->prepareEntityQuery();

        if ($prepared === null) {
            return [];
        }

        ['entity' => $entity, 'model' => $model, 'query' => $query, 'keyName' => $keyName, 'titleAttribute' => $titleAttribute, 'avatarConfig' => $avatarConfig] = $prepared;
        $searchAttributes = $entity->getSearchAttributes();

        // Try to use resource's search if available
        $resource = null;
        if ($entity->getResourceClass()) {
            try {
                $resource = App::make($entity->getResourceClass());
            } catch (Throwable) {
                $resource = null;
            }
        }

        if ($resource !== null) {
            Utils::invokeMethodByReflection($resource, 'applyGlobalSearchAttributeConstraints', [
                $query,
                $search,
                $searchAttributes,
            ]);
        } else {
            $query->where(function (Builder $q) use ($search, $searchAttributes, $titleAttribute): void {
                $attrs = $searchAttributes === [] ? [$titleAttribute] : $searchAttributes;
                foreach ($attrs as $attribute) {
                    $q->orWhere($attribute, 'like', sprintf('%%%s%%', $search));
                }
            });
        }

        $records = $this->applyLookupOrder($query, $model)
            ->limit($this->lookupLimit())
            ->get();

        return $this->formatRecordsForJs($records, $keyName, $titleAttribute, $avatarConfig);
    }

    /**
     * Get records by their IDs.
     *
     * @param  array<string>  $ids
     * @return array<string, array{id: string, label: string, avatar: ?string, avatarShape: string}>
     */
    public function getRecordsByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $prepared = $this->prepareEntityQuery();

        if ($prepared === null) {
            return [];
        }

        ['query' => $query, 'keyName' => $keyName, 'titleAttribute' => $titleAttribute, 'avatarConfig' => $avatarConfig] = $prepared;

        $records = $query->whereIn($keyName, $ids)->get()
            ->sortBy(fn (Model $record): int|false => array_search($record->getKey(), $ids, true));

        return $this->formatRecordsForJs($records, $keyName, $titleAttribute, $avatarConfig);
    }

    /**
     * Get initial options (first 50 records).
     *
     * @return array<string, array{id: string, label: string, avatar: ?string, avatarShape: string}>
     */
    public function getInitialOptions(): array
    {
        $prepared = $this->prepareEntityQuery();

        if ($prepared === null) {
            return [];
        }

        ['model' => $model, 'query' => $query, 'keyName' => $keyName, 'titleAttribute' => $titleAttribute, 'avatarConfig' => $avatarConfig] = $prepared;

        $records = $this->applyLookupOrder($query, $model)
            ->limit($this->lookupLimit())
            ->get();

        return $this->formatRecordsForJs($records, $keyName, $titleAttribute, $avatarConfig);
    }

    /**
     * Format records for JavaScript consumption.
     *
     * @return array<string, array{id: string, label: string, avatar: ?string, avatarShape: string}>
     */
    private function formatRecordsForJs(
        iterable $records,
        string $keyName,
        string $titleAttribute,
        ?AvatarConfiguration $avatarConfig
    ): array {
        $result = [];

        foreach ($records as $record) {
            $id = (string) $record->getAttribute($keyName);
            $result[$id] = [
                'id' => $id,
                'label' => $record->getAttribute($titleAttribute) ?? '',
                'avatar' => $this->getAvatarUrl($record, $avatarConfig),
                'avatarShape' => $avatarConfig?->getCssClass() ?? 'rounded-full',
            ];
        }

        return $result;
    }

    private function getAvatarUrl(Model $record, ?AvatarConfiguration $avatarConfig): ?string
    {
        if (! $avatarConfig instanceof AvatarConfiguration || ! $avatarConfig->hasAttribute()) {
            return null;
        }

        return $record->getAttribute($avatarConfig->attribute);
    }

    /**
     * Search records via Livewire call.
     * Called from Alpine.js when user types in search box.
     *
     * @return array<int, array{id: string, label: string, avatar: ?string, avatarShape: string}>
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getSearchResultsForJs(string $search): array
    {
        // Below the minimum, show the unfiltered first page rather than nothing.
        // Returning [] renders as "no results", which reads as broken for a
        // one-character search, and is wrong for single-character CJK names.
        if (mb_strlen($search) < $this->getMinSearchLength()) {
            return array_values($this->getInitialOptions());
        }

        return array_values($this->searchRecords($search));
    }
}
