<?php

namespace Relaticle\CustomFields\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Relaticle\CustomFields\Models\Concerns\UsesCustomFields;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Tests\Database\Factories\PostFactory;

class Post extends Model implements HasCustomFields
{
    use HasFactory;
    use SoftDeletes;
    use UsesCustomFields;

    protected $casts = [
        'is_published' => 'boolean',
        'tags' => 'array',
        'json_array_of_objects' => 'array',
    ];

    protected $guarded = [];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function tagModels(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag');
    }

    protected static function newFactory()
    {
        return PostFactory::new();
    }
}
