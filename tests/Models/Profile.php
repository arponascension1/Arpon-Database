<?php

namespace Arpon\Database\Tests\Models;

use Arpon\Database\Eloquent\Model;

/**
 * Profile test model
 */
class Profile extends Model
{
    protected array $fillable = [
        'user_id',
        'bio',
        'website'
    ];

    protected array $dates = ['created_at', 'updated_at'];

    /**
     * Profile belongs to a user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}