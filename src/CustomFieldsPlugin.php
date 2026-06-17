<?php

declare(strict_types=1);

namespace Relaticle\CustomFields;

use Closure;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use Filament\Support\Enums\Width;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Filament\Management\Pages\CustomFieldsManagementPage;
use Relaticle\CustomFields\Http\Middleware\SetTenantContextMiddleware;
use Relaticle\CustomFields\Services\TenantContextService;

class CustomFieldsPlugin implements Plugin
{
    use EvaluatesClosures;

    protected bool|Closure $authorizeUsing = true;

    protected ?string $dateDisplayFormat = null;

    protected ?string $dateTimeDisplayFormat = null;

    protected Width|Closure|null $sectionModalWidth = null;

    public function getId(): string
    {
        return 'custom-fields';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                CustomFieldsManagementPage::class,
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
