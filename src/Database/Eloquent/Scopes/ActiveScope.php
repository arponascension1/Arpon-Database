<?php

namespace Arpon\Database\Eloquent\Scopes;

use Arpon\Database\Eloquent\EloquentBuilder;
use Arpon\Database\Eloquent\Model;

/**
 * Global scope to filter only active records
 */
class ActiveScope implements Scope
{
    /**
     * The column to check for active status.
     */
    protected string $column;

    /**
     * Create a new active scope instance.
     */
    public function __construct(string $column = 'active')
    {
        $this->column = $column;
    }

    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(EloquentBuilder $builder, Model $model): void
    {
        $builder->where($model->qualifyColumn($this->column), 1);
    }
}