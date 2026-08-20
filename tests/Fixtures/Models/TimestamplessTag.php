<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A lookup-capable model that opts out of timestamps.
 *
 * It reads the tags table so the record-select ordering fallback can be exercised
 * without adding a second table for the same shape.
 */
class TimestamplessTag extends Model
{
    public $timestamps = false;

    protected $table = 'tags';

    protected $guarded = [];
}
