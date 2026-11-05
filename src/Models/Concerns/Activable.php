<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Models\Concerns;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Relaticle\CustomFields\Models\Scopes\ActivableScope;

/**
 * Activable trait for models that can be activated/deactivated.
 *
 * This trait adds the following query methods via ActivableScope:
 *
 * @method static Builder<static> active() Scope to only active records
 * @method static Builder<static> withDeactivated(bool $withDeactivated = true) Include deactivated records
 * @method static Builder<static> withoutDeactivated() Exclude deactivated records
 */
trait Activable
{
    const string ACTIVE_COLUMN = 'active';

    /**
     * Boot the soft deleting trait for a model.
     */
    public static function bootActivable(): void
    {
        static::addGlobalScope(new ActivableScope);
    }

    /**
     * Archive the model.
     *
     * @throws Exception
     */
    public function activate(): bool
    {
        // If the activating event doesn't return false, we'll continue with the operation.
        if ($this->fireModelEvent('activating') === false) {
            return false;
        }

        $this->{$this->getActiveColumn()} = true;

        $result = $this->save();

        // Fire archived event to allow hooking into the post-active operations.
        $this->fireModelEvent('activated', false);

        // Return true as the activating is presumably successful.
        return $result;
    }

    public function deactivate(): bool
    {
        // If the deactivating event doesn't return false, we'll continue with the operation.
        if ($this->fireModelEvent('deactivating') === false) {
            return false;
        }

        $this->{$this->getActiveColumn()} = false;

        $result = $this->save();

        $this->fireModelEvent('deactivated', false);

        // Return true as the deactivating is presumably successful.
        return $result;
    }

    /**
     * Determine if the model instance has been archived.
     */
    public function isActive(): bool
    {
        return (bool) $this->{$this->getActiveColumn()};
    }

    /**
     * Get the name of the "active" column.
     */
    public function getActiveColumn(): string
    {
        return static::ACTIVE_COLUMN;
    }

    /**
     * Get the fully qualified "created at" column.
     */
    public function getQualifiedActiveColumn(): string
    {
        return $this->qualifyColumn($this->getActiveColumn());
    }
}
