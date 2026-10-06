<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Support;

use Illuminate\Support\Facades\Schema;

/**
 * The flag gates the relationship migrations, not what a record or field does once they
 * have run. The memo is process-wide: a long-lived worker booted before the migrations ran
 * keeps the answer until it restarts, which a deploy does.
 */
final class RelationshipTables
{
    public static function exist(): bool
    {
        return once(fn (): bool => Schema::hasTable((string) config('custom-fields.database.table_names.custom_field_links')));
    }
}
