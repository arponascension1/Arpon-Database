<?php

namespace Arpon\Database\Eloquent\Relations;

use Arpon\Database\Query\Builder as QueryBuilder;
use Arpon\Database\Eloquent\EloquentBuilder;
use Arpon\Database\Eloquent\Model;
use Arpon\Database\Eloquent\Collection;

class HasMany extends HasOneOrMany
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
            $model->setRelation($relation, $this->related->newCollection());
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
        return $this->matchMany($models, $results, $relation);
    }

    /**
     * Get the results of the relationship.
     *
     * @return \Database\Eloquent\Collection
     */
    public function getResults()
    {
        return $this->query->get();
    }

    /**
     * Create multiple new instances of the related model.
     *
     * @param  array  $records
     * @return \Database\Eloquent\Collection
     */
    public function createMany(array $records)
    {
        $instances = $this->related->newCollection();

        foreach ($records as $record) {
            $instances->push($this->create($record));
        }

        return $instances;
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
     * Create multiple new instances of the related model. Allow mass-assignment.
     *
     * @param  array  $records
     * @return \Database\Eloquent\Collection
     */
    public function makeMany(array $records)
    {
        $instances = $this->related->newCollection();

        foreach ($records as $record) {
            $instances->push($this->make($record));
        }

        return $instances;
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