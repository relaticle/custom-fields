<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Providers;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\ServiceProvider;
use Relaticle\CustomFields\Services\ValidationService;

class ValidationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ValidationService::class, fn (mixed $app): ValidationService => new ValidationService);
    }

    public function boot(): void
    {
        $this->registerPhoneValidationMessage();
    }

    private function registerPhoneValidationMessage(): void
    {
        // Add phone validation message if not already defined
        $this->app->booted(function (): void {
            if (! Lang::has('validation.phone')) {
                Lang::addLines([
                    'validation.phone' => __('custom-fields::custom-fields.validation.messages.phone'),
                ], Lang::getLocale());
            }
        });
    }
}
