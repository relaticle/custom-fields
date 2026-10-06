<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Tests\Fixtures\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\Tests\Database\Factories\UserFactory;

class User extends Authenticatable implements FilamentUser, HasTenants, MustVerifyEmail
{
    use HasFactory;
    use Notifiable;

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($panel->getId(), ['admin', 'slugs']);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'author_id');
    }

    public function post(): HasOne
    {
        return $this->hasOne(Post::class, 'author_id');
    }

    public function publishedPost(): HasOne
    {
        return $this->hasOne(Post::class, 'author_id')->where('is_published', true);
    }

    protected static function newFactory()
    {
        return UserFactory::new();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return true;
    }

    public function getTenants(Panel $panel): array|Collection
    {
        return collect();
    }
}
