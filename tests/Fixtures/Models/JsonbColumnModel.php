<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class JsonbColumnModel extends Model
{
    protected $table = 'jsonb_column_models';

    protected $guarded = [];
}
