<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Livewire\Concerns;

use Relaticle\CustomFields\CustomFieldsPlugin;

// The management page checks the plugin's authorize callback only when it loads,
// so every component it renders re-checks it on each request of its own.
trait AuthorizesFieldManagement
{
    public function mountAuthorizesFieldManagement(): void
    {
        $this->authorizeFieldManagement();
    }

    public function hydrateAuthorizesFieldManagement(): void
    {
        $this->authorizeFieldManagement();
    }

    private function authorizeFieldManagement(): void
    {
        abort_unless(CustomFieldsPlugin::get()->isAuthorized(), 403);
    }
}
