<?php

namespace Arpon\Database\Tests\Models;

use Arpon\Database\Eloquent\Model;

/**
 * Test Profile model
 */
class Profile extends Model
{
    protected ?string $table = 'profiles';
    protected array $fillable = ['user_id', 'bio', 'website'];
    protected array $dates = ['created_at', 'updated_at'];

    /**
     * Get the profile's owner
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Accessor for website
     */
    public function getWebsiteAttribute($value)
    {
        if ($value && !str_starts_with($value, 'http')) {
            return 'https://' . $value;
        }
        return $value;
    }
}

/**
 * Test Category model
 */
class Category extends Model
{
    protected ?string $table = 'categories';
    protected array $fillable = ['name', 'description'];
    protected array $dates = ['created_at', 'updated_at'];
}

/**
 * Test Tag model
 */
class Tag extends Model
{
    protected ?string $table = 'tags';
    protected array $fillable = ['name'];
    protected array $dates = ['created_at', 'updated_at'];
}

/**
 * Test Comment model
 */
class Comment extends Model
{
    protected ?string $table = 'comments';
    protected array $fillable = ['content', 'post_id'];
    protected array $dates = ['created_at', 'updated_at'];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}