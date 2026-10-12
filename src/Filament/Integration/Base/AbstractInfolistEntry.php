<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Base;

use Filament\Infolists\Components\Entry;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Contracts\InfolistComponentInterface;
use Relaticle\CustomFields\Models\CustomField;

/**
 * ABOUTME: Abstract base class for infolist entry components providing common structure.
 * ABOUTME: Standardizes entry creation pattern across different entry types.
 */
abstract class AbstractInfolistEntry implements InfolistComponentInterface
{
    /**
     * Create and configure an infolist entry.
     */
    abstract public function make(CustomField $customField, ?Model $record = null): Entry;
}
