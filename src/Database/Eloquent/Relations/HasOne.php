<?php

namespace Arpon\Database\Eloquent\Relations;

use Arpon\Database\Query\Builder as QueryBuilder;
use Arpon\Database\Eloquent\EloquentBuilder;
use Arpon\Database\Eloquent\Model;
use Arpon\Database\Eloquent\Collection;

class HasOne extends HasOneOrMany
{
    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            $this->query->where($this->getQualifiedForeignKeyName(), '=', $this->getParentKey());
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        $this->query->whereIn(
            $this->getQualifiedForeignKeyName(), $this->getKeys($models, $this->localKey)
        );
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param  array   $models
     * @param  string  $relation
     * @return array
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array   $models
     * @param  \Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        return $this->matchOne($models, $results, $relation);
    }

    /**
     * Get the results of the relationship.
     *
     * @return \Database\Eloquent\Model|null
     */
    public function getResults()
    {
        return $this->query->first();
    }

    /**
     * Create a new instance of the related model. Allow mass-assignment.
     *
     * @param  array  $attributes
     * @return \Database\Eloquent\Model
     */
    public function make(array $attributes = [])
    {
        return tap($this->related->newInstance($attributes), function ($instance) {
            $instance->setAttribute($this->foreignKey, $this->getParentKey());
        });
    }

    /**
     * Get the fully qualified foreign key name.
     *
     * @return string
     */
    public function getQualifiedForeignKeyName()
    {
        return $this->related->getTable().'.'.$this->foreignKey;
    }

    /**
     * Get the local key for the relationship.
     *
     * @return string
     */
    public function getLocalKeyName()
    {
        return $this->localKey;
    }
}