<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Relaticle\CustomFields\Enums\CustomFieldWidth;
use Relaticle\CustomFields\Livewire\Concerns\AuthorizesFieldManagement;

class ManageCustomFieldWidth extends Component
{
    use AuthorizesFieldManagement;

    /**
     * @var int
     */
    public $selectedWidth = 100; // @pest-ignore-type

    /**
     * @var array<int, int>
     */
    public $widthOptions = [ // @pest-ignore-type
        25, 33, 50, 66, 75, 100,
    ];

    /**
     * @var array<string, string>
     */
    public $widthMap = [ // @pest-ignore-type
        '25' => 'col-span-3',
        '33' => 'col-span-4',
        '50' => 'col-span-6',
        '66' => 'col-span-8',
        '75' => 'col-span-9',
        '100' => 'col-span-12',
    ];

    /**
     * @var int|string
     */
    public $fieldId; // @pest-ignore-type

    public function mount(CustomFieldWidth $selectedWidth, int|string $fieldId): void
    {
        $this->selectedWidth = $selectedWidth;
        $this->fieldId = $fieldId;
    }

    public function render(): View
    {
        return view('custom-fields::livewire.manage-custom-field-width');
    }
}
