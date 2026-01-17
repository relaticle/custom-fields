# Authorization

> Control access to the Custom Fields management interface

You can prevent pages from appearing in the menu by overriding the authorize() method when registering a plugin. This is useful if you want to control which users can see the page in the navigation, and also which users can visit the page directly:

```php
namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use Relaticle\CustomFields\CustomFieldsPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            // ...
            ->plugin(
                CustomFieldsPlugin::make()
                    ->authorize(fn(): bool => auth()->user()->email === 'admin@relaticle.com'),
            );
    }
}
```
