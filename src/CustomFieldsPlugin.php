<?php

declare(strict_types=1);

namespace Relaticle\CustomFields;

use Closure;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Schemas\Components\Component;
use Filament\Support\Concerns\EvaluatesClosures;
use Filament\Support\Enums\Width;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Relaticle\CustomFields\Contracts\FieldTypeDefinitionInterface;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Filament\Management\Pages\CustomFieldsManagementPage;
use Relaticle\CustomFields\Http\Middleware\SetTenantContextMiddleware;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Services\TenantContextService;

class CustomFieldsPlugin implements Plugin
{
    use EvaluatesClosures;

    protected bool|Closure $authorizeUsing = true;

    protected ?string $dateDisplayFormat = null;

    protected ?string $dateTimeDisplayFormat = null;

    protected Width|Closure|null $sectionModalWidth = null;

    /** @var class-string<CustomFieldsManagementPage>|null */
    protected ?string $managementPage = null;

    /** @var array<int, Closure(array<int, Component>, ?CustomFieldSection): array<int, Component>> */
    protected array $fieldSchemaExtensions = [];

    /** @var array<int, Closure(array<int, Component>, string): array<int, Component>> */
    protected array $sectionSchemaExtensions = [];

    /** @var ?Closure(?CustomFieldSection): ?Closure */
    protected ?Closure $uniqueFieldNameRule = null;

    /** @var ?Closure(?CustomFieldSection): ?Closure */
    protected ?Closure $uniqueFieldCodeRule = null;

    /** @var ?Closure(CustomFieldSection): ?Closure */
    protected ?Closure $uniqueSectionRule = null;

    /** @var ?Closure(string, ?CustomFieldSection): ?Closure */
    protected ?Closure $visibilityFields = null;

    public function getId(): string
    {
        return 'custom-fields';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                $this->getManagementPage(),
            ])
            ->tenantMiddleware([SetTenantContextMiddleware::class], true);
    }

    public function boot(Panel $panel): void
    {
        if ($this->dateDisplayFormat !== null) {
            CustomFields::useDateDisplayFormat($this->dateDisplayFormat);
        }

        if ($this->dateTimeDisplayFormat !== null) {
            CustomFields::useDateTimeDisplayFormat($this->dateTimeDisplayFormat);
        }

        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
            Action::configureUsing(
                fn (Action $action): Action => $action->before(
                    function (Action $action): Action {
                        TenantContextService::setFromFilamentTenant();

                        return $action;
                    }
                )
            );
        }
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    /**
     * The plugin on the current panel, or null when no panel registers it.
     */
    public static function current(): ?static
    {
        $panel = Filament::getCurrentPanel()
            ?? Arr::first(Filament::getPanels(), fn (Panel $panel): bool => $panel->isDefault());

        $id = app(static::class)->getId();

        if (! $panel instanceof Panel || ! $panel->hasPlugin($id)) {
            return null;
        }

        /** @var static $plugin */
        $plugin = $panel->getPlugin($id);

        return $plugin;
    }

    /**
     * @param  array<int|string, class-string<FieldTypeDefinitionInterface>> | Closure  $fieldTypes
     */
    public function registerFieldTypes(array|Closure $fieldTypes): static
    {
        CustomFieldsType::register($fieldTypes);

        return $this;
    }

    public function authorize(bool|Closure $callback = true): static
    {
        $this->authorizeUsing = $callback;

        return $this;
    }

    public function isAuthorized(): bool
    {
        return $this->evaluate($this->authorizeUsing) === true;
    }

    public function dateDisplayFormat(?string $format): static
    {
        $this->dateDisplayFormat = $format;

        return $this;
    }

    public function dateTimeDisplayFormat(?string $format): static
    {
        $this->dateTimeDisplayFormat = $format;

        return $this;
    }

    public function sectionModalWidth(Width|Closure|null $width): static
    {
        $this->sectionModalWidth = $width;

        return $this;
    }

    /**
     * Register your own management page in place of the packaged one, so the host
     * application controls the things Filament reads off the page class itself —
     * slug, sub-navigation, cluster, heading. The subclass keeps all packaged
     * behaviour; only the page registered on the panel changes.
     *
     * @param  class-string<CustomFieldsManagementPage>  $page
     */
    public function managementPage(string $page): static
    {
        // is_a() rather than is_subclass_of(), so passing the packaged page
        // itself is an explicit no-op rather than an error.
        if (! is_a($page, CustomFieldsManagementPage::class, allow_string: true)) {
            throw new InvalidArgumentException(sprintf(
                '[%s] must extend [%s] to be used as the management page.',
                $page,
                CustomFieldsManagementPage::class,
            ));
        }

        $this->managementPage = $page;

        return $this;
    }

    /**
     * @return class-string<CustomFieldsManagementPage>
     */
    public function getManagementPage(): string
    {
        return $this->managementPage ?? CustomFieldsManagementPage::class;
    }

    /**
     * Append to or modify the field create/edit form. The callback receives the schema
     * and the section the field belongs to. Each call adds another extension.
     *
     * @param  Closure(array<int, Component> $schema, ?CustomFieldSection $section): array<int, Component>  $callback
     */
    public function fieldSchema(Closure $callback): static
    {
        $this->fieldSchemaExtensions[] = $callback;

        return $this;
    }

    /**
     * @return array<int, Closure(array<int, Component>, ?CustomFieldSection): array<int, Component>>
     */
    public function getFieldSchemaExtensions(): array
    {
        return $this->fieldSchemaExtensions;
    }

    /**
     * Append to or modify the section create/edit form. The callback receives the schema
     * and the section's entity type. Each call adds another extension.
     *
     * @param  Closure(array<int, Component> $schema, string $entityType): array<int, Component>  $callback
     */
    public function sectionSchema(Closure $callback): static
    {
        $this->sectionSchemaExtensions[] = $callback;

        return $this;
    }

    /**
     * @return array<int, Closure(array<int, Component>, string): array<int, Component>>
     */
    public function getSectionSchemaExtensions(): array
    {
        return $this->sectionSchemaExtensions;
    }

    /**
     * Scope the field-name uniqueness rule beyond the entity type (and tenant). The callback
     * receives the field's section and returns a rule modifier, or null for no extra scope.
     *
     * @param  Closure(?CustomFieldSection $section): ?Closure  $modifier
     */
    public function uniqueFieldNameRule(Closure $modifier): static
    {
        $this->uniqueFieldNameRule = $modifier;

        return $this;
    }

    /**
     * @return ?Closure(?CustomFieldSection): ?Closure
     */
    public function getUniqueFieldNameRule(): ?Closure
    {
        return $this->uniqueFieldNameRule;
    }

    /**
     * Scope the field-code uniqueness rule beyond the entity type (and tenant). Kept apart
     * from the name rule because a code is a stable identity and usually needs a wider scope.
     *
     * @param  Closure(?CustomFieldSection $section): ?Closure  $modifier
     */
    public function uniqueFieldCodeRule(Closure $modifier): static
    {
        $this->uniqueFieldCodeRule = $modifier;

        return $this;
    }

    /**
     * @return ?Closure(?CustomFieldSection): ?Closure
     */
    public function getUniqueFieldCodeRule(): ?Closure
    {
        return $this->uniqueFieldCodeRule;
    }

    /**
     * Scope the section name and code uniqueness rules on the edit form. The callback
     * receives the edited section and returns a rule modifier, or null for no extra scope.
     *
     * @param  Closure(CustomFieldSection $section): ?Closure  $modifier
     */
    public function uniqueSectionRule(Closure $modifier): static
    {
        $this->uniqueSectionRule = $modifier;

        return $this;
    }

    /**
     * @return ?Closure(CustomFieldSection): ?Closure
     */
    public function getUniqueSectionRule(): ?Closure
    {
        return $this->uniqueSectionRule;
    }

    /**
     * Constrain the fields the conditional-visibility picker offers. The callback receives
     * the entity type and the conditioned section, and returns a query constraint or null.
     *
     * @param  Closure(string $entityType, ?CustomFieldSection $section): ?Closure  $scope
     */
    public function visibilityFields(Closure $scope): static
    {
        $this->visibilityFields = $scope;

        return $this;
    }

    /**
     * @return ?Closure(string, ?CustomFieldSection): ?Closure
     */
    public function getVisibilityFields(): ?Closure
    {
        return $this->visibilityFields;
    }

    public function getSectionModalWidth(): Width
    {
        $width = $this->evaluate($this->sectionModalWidth);

        if (! $width instanceof Width) {
            $configured = config('custom-fields.management.section_modal_width');

            $width = match (true) {
                $configured instanceof Width => $configured,
                is_string($configured) => Width::tryFrom($configured),
                default => null,
            };
        }

        // The conditional-visibility editor adds a four-column conditions row that is unreadable
        // at the narrower default width, so it gets the wider modal unless overridden.
        return $width
            ?? (FeatureManager::isEnabled(CustomFieldsFeature::SECTION_CONDITIONAL_VISIBILITY)
                ? Width::ScreenLarge
                : Width::TwoExtraLarge);
    }
}
