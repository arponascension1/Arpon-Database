<?php

namespace Arpon\Database\Eloquent\Scopes;

use Arpon\Database\Eloquent\EloquentBuilder;
use Arpon\Database\Eloquent\Model;

/**
 * Global scope to filter only published records
 */
class PublishedScope implements Scope
{
    /**
     * The column to check for published status.
     */
    protected string $column;

    /**
     * Create a new published scope instance.
     */
    public function __construct(string $column = 'published_at')
    {
        $this->column = $column;
    }

    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(EloquentBuilder $builder, Model $model): void
    {
        $builder->whereNotNull($model->qualifyColumn($this->column));
    }
}