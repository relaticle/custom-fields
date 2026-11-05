<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class SecondConnectionModel extends Model
{
    protected $connection = 'second';

    protected $table = 'second_connection_models';

    protected $guarded = [];
}
