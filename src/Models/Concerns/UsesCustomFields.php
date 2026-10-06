<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Models\Concerns;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Data\RecordLinkPayload;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Exceptions\UniqueCustomFieldValueTakenException;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Models\CustomFieldValue;
use Relaticle\CustomFields\Models\Scopes\TenantScope;
use Relaticle\CustomFields\QueryBuilders\CustomFieldQueryBuilder;
use Relaticle\CustomFields\Rules\UniqueCustomFieldValue;
use Relaticle\CustomFields\Services\Relationships\LinkReader;
use Relaticle\CustomFields\Services\Relationships\LinkWriter;
use Relaticle\CustomFields\Services\TenantContextService;
use Relaticle\CustomFields\Services\ValueResolver\LookupPreloader;
use Relaticle\CustomFields\Support\RelationshipTables;

/**
 * @see HasCustomFields
 */
trait UsesCustomFields
{
    public function __construct($attributes = [])
    {
        if (count($this->getFillable()) !== 0) {
            $this->mergeFillable(['custom_fields']);
        }

        parent::__construct($attributes);

        $this->handleCustomFields();
    }

    public function isFillable($key): bool
    {
        if ($key === 'custom_fields') {
            return true;
        }

        return parent::isFillable($key);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function fillableFromArray(array $attributes): array
    {
        $fillable = parent::fillableFromArray($attributes);

        if (array_key_exists('custom_fields', $attributes) && ! array_key_exists('custom_fields', $fillable)) {
            $fillable['custom_fields'] = $attributes['custom_fields'];
        }

        return $fillable;
    }

    /**
     * @var array<int, array<string, mixed>>
     */
    protected static array $tempCustomFields = [];

    protected static function bootUsesCustomFields(): void
    {
        static::saving(function (Model $model): void {
            $model->handleCustomFields();
        });

        static::saved(function (Model $model): void {
            $model->saveCustomFieldsFromTemp();
        });

        static::deleting(function (Model $model): void {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->customFieldValues()->delete();
            $model->deleteCustomFieldLinks();
        });

        // A trashed record gives up its unique values, so it may only come back while they are still free.
        static::registerModelEvent('restoring', function (Model $model): void {
            $taken = $model->takenUniqueCustomFieldValues()->first();

            if ($taken !== null) {
                throw new UniqueCustomFieldValueTakenException($model, $taken['customField'], $taken['value']);
            }
        });
    }

    /**
     * @return Collection<int, array{customField: CustomField, value: string}>
     */
    public function takenUniqueCustomFieldValues(): Collection
    {
        $tenantKey = (string) config('custom-fields.database.column_names.tenant_foreign_key');

        return $this->customFieldValues()
            ->with('customField')
            ->get()
            ->filter(fn (CustomFieldValue $value): bool => $value->customField?->active && $value->customField->settings->unique_per_entity_type)
            ->flatMap(fn (CustomFieldValue $value): Collection => TenantContextService::withTenant(
                $value->getAttribute($tenantKey),
                fn (): Collection => collect($value->getValue())
                    ->filter(fn (mixed $candidate): bool => is_scalar($candidate) && filled($candidate))
                    ->reject(fn (mixed $candidate): bool => validator(
                        ['value' => $candidate],
                        ['value' => [new UniqueCustomFieldValue($value->customField, $this->getKey())]],
                    )->passes())
                    ->map(fn (mixed $candidate): array => ['customField' => $value->customField, 'value' => (string) $candidate])
                    ->values(),
            ))
            ->values();
    }

    /**
     * The saved hook writes custom fields after the record row is already written, and a
     * rejected link throws there, so a pending payload puts the whole save in one
     * transaction (a savepoint when the host already opened one).
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        if (! $this->hasPendingCustomFields()) {
            return parent::save($options);
        }

        return (bool) $this->getConnection()->transaction(fn (): bool => parent::save($options));
    }

    /**
     * A payload reaches save() either as an attribute or, when it came through the
     * constructor, already parked in the temporary store.
     */
    protected function hasPendingCustomFields(): bool
    {
        if (isset($this->custom_fields) && is_array($this->custom_fields)) {
            return true;
        }

        return isset(self::$tempCustomFields[spl_object_id($this)]);
    }

    /**
     * Handle the custom fields before saving the model.
     */
    protected function handleCustomFields(): void
    {
        if (isset($this->custom_fields) && is_array($this->custom_fields)) {
            self::$tempCustomFields[spl_object_id($this)] = $this->custom_fields;
            unset($this->custom_fields);
        }
    }

    /**
     * Save custom fields from temporary storage after the model is created/updated.
     */
    protected function saveCustomFieldsFromTemp(): void
    {
        $objectId = spl_object_id($this);

        if (! isset(self::$tempCustomFields[$objectId]) || ! method_exists($this, 'saveCustomFields')) {
            return;
        }

        // A rejected payload rolls its record back, and the store is keyed on an object id
        // PHP reuses after collection, so the entry goes whichever way the write ends.
        try {
            $this->saveCustomFields(self::$tempCustomFields[$objectId]);
        } finally {
            unset(self::$tempCustomFields[$objectId]);
        }
    }

    /**
     * @return CustomFieldQueryBuilder<CustomField>
     */
    public function customFields(): CustomFieldQueryBuilder
    {
        return CustomFields::newCustomFieldModel()->query()->forEntity($this::class);
    }

    /**
     * @return MorphMany<CustomFieldValue>
     */
    public function customFieldValues(): MorphMany
    {
        return $this->morphMany(CustomFields::valueModel(), 'entity');
    }

    /**
     * @return MorphMany<CustomFieldLink>
     */
    public function outgoingLinks(): MorphMany
    {
        return $this->morphMany(CustomFields::linkModel(), 'from_entity');
    }

    /**
     * @return MorphMany<CustomFieldLink>
     */
    public function incomingLinks(): MorphMany
    {
        return $this->morphMany(CustomFields::linkModel(), 'to_entity');
    }

    /**
     * A record that is really gone leaves no edge behind, in either direction and not in
     * history either: one delete per end, each on its own reverse index. A soft delete
     * never reaches this, so a restored record finds its links where it left them.
     *
     * The tenant scope is dropped because the record is already identified, and no context
     * a delete happens in may strand an edge.
     */
    protected function deleteCustomFieldLinks(): void
    {
        if (! RelationshipTables::exist()) {
            return;
        }

        $ends = [CustomFieldRelationship::DIRECTION_FROM, CustomFieldRelationship::DIRECTION_TO];

        foreach ($ends as $end) {
            CustomFields::newLinkModel()
                ->newQuery()
                ->withoutGlobalScope(TenantScope::class)
                ->where($end.'_entity_type', $this->getMorphClass())
                ->where($end.'_entity_id', $this->getKey())
                ->delete();
        }
    }

    /**
     * The ledger keeps closed edges forever, so only the active ones are worth carrying
     * into a page render.
     */
    public function scopeWithActiveCustomFieldLinks(Builder $query): Builder
    {
        if (! RelationshipTables::exist()) {
            return $query;
        }

        return $query->with([
            'outgoingLinks' => fn (MorphMany $links): MorphMany => $links->whereNull('active_until'),
            'incomingLinks' => fn (MorphMany $links): MorphMany => $links->whereNull('active_until'),
        ]);
    }

    public function scopeWithCustomFieldValues(Builder $query): Builder
    {
        return $query
            ->withActiveCustomFieldLinks()
            ->with('customFieldValues.customField.options')
            ->afterQuery(function ($records): void {
                if ($records instanceof EloquentCollection) {
                    app(LookupPreloader::class)->preload($records);
                }
            });
    }

    public function getCustomFieldValue(CustomField $customField): mixed
    {
        $definition = $customField->relationshipDefinition();

        if ($definition instanceof CustomFieldRelationship) {
            return app(LinkReader::class)->orderedIdsFor($this, $definition, $definition->readDirectionFor($customField));
        }

        $fieldValue = $this->customFieldValues
            ->firstWhere('custom_field_id', $customField->getKey())
            ?->getValue();

        if (empty($fieldValue)) {
            return $fieldValue;
        }

        if ($customField->settings?->encrypted) {
            $fieldValue = Crypt::decryptString($fieldValue);
        }

        return $fieldValue instanceof Collection
            ? $fieldValue->toArray()
            : $fieldValue;
    }

    public function saveCustomFieldValue(CustomField $customField, mixed $value, ?Model $tenant = null): void
    {
        if ($this->writesLinksFor($customField)) {
            $payload = RecordLinkPayload::fromValue($value);

            app(LinkWriter::class)->apply($this, $customField, $payload->ids, confirmed: $payload->confirmed);

            return;
        }

        $data = ['custom_field_id' => $customField->getKey()];

        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
            $data[config('custom-fields.database.column_names.tenant_foreign_key')] = $this->resolveTenantId($tenant, $customField);
        }

        $customFieldValue = $this->customFieldValues();

        if ($customField->settings?->encrypted) {
            $customFieldValue->withCasts([$customField->getValueColumn() => 'encrypted']);
        }

        $customFieldValue = $customFieldValue->firstOrNew($data);
        $customFieldValue->setValue($value);
        $customFieldValue->save();
    }

    /**
     * A definition is the write fork, exactly as it already is for reads: no definition can
     * exist without the tables it lives in, so a host that never enabled the feature keeps
     * writing value rows either way.
     */
    protected function writesLinksFor(CustomField $customField): bool
    {
        return $customField->relationshipDefinition() instanceof CustomFieldRelationship;
    }

    /**
     * Resolve the tenant ID from available sources
     */
    protected function resolveTenantId(?Model $tenant, CustomField $customField): mixed
    {
        // First priority: Explicitly provided tenant
        if ($tenant instanceof Model) {
            return $tenant->getKey();
        }

        // Second priority: Current Filament tenant
        $filamentTenant = Filament::getTenant();
        if ($filamentTenant !== null) {
            return $filamentTenant->getKey();
        }

        // Fallback: Use the tenant from the custom field
        $tenantColumn = config('custom-fields.database.column_names.tenant_foreign_key');

        return $customField->{$tenantColumn};
    }

    /**
     * @param  array<string, mixed>  $customFields
     */
    public function saveCustomFields(array $customFields, ?Model $tenant = null): void
    {
        $this->customFields()->each(function (CustomField $customField) use ($customFields, $tenant): void {
            // A relationship has no row to overwrite with null: an absent key means the
            // payload said nothing about those edges, so they stay as they are.
            if (! array_key_exists($customField->code, $customFields) && $this->writesLinksFor($customField)) {
                return;
            }

            $value = $customFields[$customField->code] ?? null;
            $this->saveCustomFieldValue($customField, $value, $tenant);
        });
    }
}
