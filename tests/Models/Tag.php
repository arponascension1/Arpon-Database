<?php

namespace Arpon\Database\Tests\Models;

use Arpon\Database\Eloquent\Model;

/**
 * Tag test model for polymorphic many-to-many relationships
 */
class Tag extends Model
{
    protected array $fillable = [
        'name'
    ];

    protected array $dates = ['created_at', 'updated_at'];

    /**
     * Get all posts that have this tag
     */
    public function posts()
    {
        return $this->morphedByMany(Post::class, 'taggable');
    }

    /**
     * Get all users that have this tag
     */
    public function users()
    {
        return $this->morphedByMany(User::class, 'taggable');
    }
}