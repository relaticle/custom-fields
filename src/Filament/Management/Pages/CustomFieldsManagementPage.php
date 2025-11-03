<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Management\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Override;
use Relaticle\CustomFields\CustomFields as CustomFieldsModel;
use Relaticle\CustomFields\CustomFieldsPlugin;
use Relaticle\CustomFields\Enums\CustomFieldSectionType;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Facades\Entities;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Filament\Management\Schemas\SectionForm;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Services\TenantContextService;
use Relaticle\CustomFields\Support\Utils;

class CustomFieldsManagementPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-m-document-text';

    protected string $view = 'custom-fields::filament.pages.custom-fields-management';

    protected static ?int $navigationSort = 10;

    protected static bool $shouldRegisterNavigation = true;

    #[Url(history: true, keep: true)]
    public ?string $currentEntityType = null;

    public function mount(): void
    {
        if (blank($this->currentEntityType)) {
            $firstEntity = Entities::withCustomFields()->first();
            $this->setCurrentEntityType($firstEntity?->getAlias() ?? '');
        }
    }

    #[Computed]
    public function sections(): Collection
    {
        return CustomFieldsModel::newSectionModel()->query()
            ->withDeactivated()
            ->forEntityType($this->currentEntityType)
            ->with([
                'fields' => function (HasMany $query): void {
                    $query->forMorphEntity($this->currentEntityType)
                        ->orderBy('sort_order');
                },
            ])
            ->orderBy('sort_order')
            ->get();
    }

    #[Computed]
    public function currentEntityLabel(): string
    {
        if (in_array($this->currentEntityType, [null, '', '0'], true)) {
            return '';
        }

        $entity = Entities::getEntity($this->currentEntityType);

        return $entity?->getLabelPlural() ?? $this->currentEntityType;
    }

    #[Computed]
    public function currentEntityIcon(): string
    {
        if (in_array($this->currentEntityType, [null, '', '0'], true)) {
            return 'heroicon-o-document';
        }

        $entity = Entities::getEntity($this->currentEntityType);

        return $entity?->getIcon() ?? 'heroicon-o-document';
    }

    #[Computed]
    public function entityTypes(): Collection
    {
        return collect(Entities::getOptions(onlyCustomFields: true));
    }

    public function setCurrentEntityType(?string $entityType): void
    {
        $this->currentEntityType = $entityType;
    }

    public function createSectionAction(): Action
    {
        return Action::make('createSection')
            ->size(Size::ExtraSmall)
            ->label(__('custom-fields::custom-fields.section.form.add_section'))
            ->icon('heroicon-s-plus')
            ->color('gray')
            ->button()
            ->outlined()
            ->extraAttributes([
                'class' => 'flex justify-center items-center rounded-lg border-gray-300 hover:border-gray-400 border-dashed',
            ])
            ->schema(SectionForm::entityType($this->currentEntityType)->schema())
            ->action(fn (array $data): CustomFieldSection => $this->storeSection($data))
            ->modalWidth(Width::TwoExtraLarge);
    }

    /**
     * @param  array<int, int>  $sections
     */
    public function updateSectionsOrder(array $sections): void
    {
        $sectionModel = CustomFieldsModel::newSectionModel();

        foreach ($sections as $index => $section) {
            $sectionModel->query()
                ->withDeactivated()
                ->where($sectionModel->getKeyName(), $section)
                ->update([
                    'sort_order' => $index,
                ]);
        }
    }

    private function storeSection(array $data): CustomFieldSection
    {
        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
            $data[config('custom-fields.database.column_names.tenant_foreign_key')] = TenantContextService::getCurrentTenantId();
        }

        $data['type'] ??= CustomFieldSectionType::SECTION->value;
        $data['entity_type'] = $this->currentEntityType;

        return CustomFieldsModel::newSectionModel()->create($data);
    }

    #[On('section-deleted')]
    public function sectionDeleted(): void
    {
        $this->sections = $this->sections->filter(fn (CustomFieldSection $section): bool => $section->exists);
    }

    #[Override]
    public static function getCluster(): ?string
    {
        return Utils::getResourceCluster() ?? static::$cluster;
    }

    #[Override]
    public static function shouldRegisterNavigation(): bool
    {
        return FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MANAGEMENT_INTERFACE);
    }

    #[Override]
    public static function getNavigationGroup(): ?string
    {
        return Utils::isResourceNavigationGroupEnabled()
            ? __('custom-fields::custom-fields.nav.group')
            : '';
    }

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('custom-fields::custom-fields.nav.label');
    }

    #[Override]
    public static function getNavigationIcon(): string
    {
        return __('custom-fields::custom-fields.nav.icon');
    }

    #[Override]
    public function getHeading(): string
    {
        return __('custom-fields::custom-fields.heading.title');
    }

    #[Override]
    public static function getNavigationSort(): ?int
    {
        return Utils::getResourceNavigationSort();
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return Utils::getResourceSlug();
    }

    public static function canAccess(): bool
    {
        return CustomFieldsPlugin::get()->isAuthorized();
    }
}
