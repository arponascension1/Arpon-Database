<?php

namespace Arpon\Database\Tests\Models;

use Arpon\Database\Eloquent\Model;

/**
 * Test Post model
 */
class Post extends Model
{
    protected ?string $table = 'posts';
    protected array $fillable = ['title', 'content', 'user_id', 'published', 'published_at'];
    protected array $dates = ['published_at', 'created_at', 'updated_at'];

    /**
     * Get the post's author
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the post's author (alias)
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Accessor for title
     */
    public function getTitleAttribute($value)
    {
        return ucfirst($value);
    }

    /**
     * Scope for published posts
     */
    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at');
    }

    /**
     * Scope for posts by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get the post's comments
     */
    public function comments()
    {
        return $this->hasMany('Arpon\Database\Tests\Models\Comment');
    }
}