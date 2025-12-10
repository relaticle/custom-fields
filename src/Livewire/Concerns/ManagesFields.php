<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Livewire\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Relaticle\CustomFields\CustomFields;

/**
 * Shared logic for managing custom fields in Livewire components.
 */
trait ManagesFields
{
    #[Computed]
    public function fields(): Collection
    {
        return $this->section->fields()->withDeactivated()->orderBy('sort_order')->get();
    }

    #[On('field-width-updated')]
    public function fieldWidthUpdated(int|string $fieldId, int $width): void
    {
        $model = CustomFields::newCustomFieldModel();
        $model->where($model->getKeyName(), $fieldId)->update(['width' => $width]);

        $this->section->refresh();
    }

    #[On('field-deleted')]
    public function fieldDeleted(): void
    {
        $this->section->refresh();
    }

    #[On('fields-reordered')]
    public function fieldsReordered(): void
    {
        unset($this->fields);
    }
}
