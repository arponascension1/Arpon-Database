<?php

namespace Arpon\Database\Eloquent\Scopes;

use Arpon\Database\Eloquent\EloquentBuilder;
use Arpon\Database\Eloquent\Model;

/**
 * Interface for Eloquent global scopes
 */
interface Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Arpon\Database\Eloquent\EloquentBuilder  $builder
     * @param  \Arpon\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(EloquentBuilder $builder, Model $model): void;
}