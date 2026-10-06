<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;
use Livewire\Component;
use Relaticle\CustomFields\Enums\CustomFieldWidth;

final class ManageCustomFieldWidth extends Component
{
    public CustomFieldWidth $selectedWidth = CustomFieldWidth::_100;

    /**
     * @var array<int, int>
     */
    public array $widthOptions = [
        25, 33, 50, 66, 75, 100,
    ];

    public int|string $fieldId;

    public function mount(CustomFieldWidth $selectedWidth, int|string $fieldId): void
    {
        $this->selectedWidth = $selectedWidth;
        $this->fieldId = $fieldId;
    }

    public function render(): View
    {
        /** @var view-string $view */
        $view = 'custom-fields::livewire.manage-custom-field-width';

        return ViewFactory::make($view);
    }
}
