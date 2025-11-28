<?php

namespace Arpon\Database\Tests\Models;

use Arpon\Database\Eloquent\Model;

/**
 * Test User model
 */
class User extends Model
{
    protected ?string $table = 'users';
    protected array $fillable = ['name', 'email', 'age', 'settings', 'is_active'];
    protected array $casts = [
        'age' => 'integer',
        'settings' => 'json',
        'is_active' => 'boolean'
    ];
    protected array $dates = ['created_at', 'updated_at'];

    /**
     * Get user's posts
     */
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Get user's profile
     */
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * Accessor example - capitalize name
     */
    public function getNameAttribute($value)
    {
        return ucwords(strtolower($value));
    }

    /**
     * Mutator example - lowercase email
     */
    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = strtolower($value);
        return $this;
    }

    /**
     * Get full name (accessor example)
     */
    public function getFullNameAttribute()
    {
        return $this->name;
    }
}