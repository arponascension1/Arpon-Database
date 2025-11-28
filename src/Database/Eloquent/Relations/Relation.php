<?php

namespace Arpon\Database\Eloquent\Relations;

use Arpon\Database\Query\Builder as QueryBuilder;
use Arpon\Database\Eloquent\EloquentBuilder;
use Arpon\Database\Eloquent\Model;
use Arpon\Database\Eloquent\Collection;

abstract class Relation
{
    /**
     * The Eloquent query builder instance.
     *
     * @var \Arpon\Database\Eloquent\EloquentBuilder
     */
    protected $query;

    /**
     * The parent model instance.
     *
     * @var \Database\Eloquent\Model
     */
    protected $parent;

    /**
     * The related model instance.
     *
     * @var \Database\Eloquent\Model
     */
    protected $related;

    /**
     * Create a new relation instance.
     *
     * @param  \Arpon\Database\Eloquent\EloquentBuilder  $query
     * @param  \Arpon\Database\Eloquent\Model  $parent
     * @return void
     */
    public function __construct(EloquentBuilder $query, Model $parent)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->related = $query->getModel();

        $this->addConstraints();
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    abstract public function addConstraints();

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    abstract public function addEagerConstraints(array $models);

    /**
     * Initialize the relation on a set of models.
     *
     * @param  array   $models
     * @param  string  $relation
     * @return array
     */
    abstract public function initRelation(array $models, $relation);

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array   $models
     * @param  \Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @return array
     */
    abstract public function match(array $models, Collection $results, $relation);

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    abstract public function getResults();

    /**
     * Get the relationship for eager loading.
     *
     * @return \Database\Eloquent\Collection
     */
    public function getEager()
    {
        return $this->get();
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return \Database\Eloquent\Collection
     */
    public function get($columns = ['*'])
    {
        return $this->query->get($columns);
    }

    /**
     * Get the first related model record matching the attributes.
     *
     * @param  array  $columns
     * @return \Database\Eloquent\Model|null
     */
    public function first($columns = ['*'])
    {
        return $this->query->first($columns);
    }

    /**
     * Find a related model by its primary key.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return \Database\Eloquent\Model|\Database\Eloquent\Collection|null
     */
    public function find($id, $columns = ['*'])
    {
        if (is_array($id)) {
            return $this->findMany($id, $columns);
        }

        return $this->where($this->related->getKeyName(), '=', $id)->first($columns);
    }

    /**
     * Find multiple related models by their primary keys.
     *
     * @param  array  $ids
     * @param  array  $columns
     * @return \Database\Eloquent\Collection
     */
    public function findMany(array $ids, $columns = ['*'])
    {
        if (empty($ids)) {
            return $this->related->newCollection();
        }

        return $this->whereIn($this->related->getKeyName(), $ids)->get($columns);
    }

    /**
     * Get the count of the results.
     *
     * @return int
     */
    public function count()
    {
        return $this->query->toBase()->count();
    }

    /**
     * Get the underlying query builder instance.
     *
     * @return \Arpon\Database\Eloquent\EloquentBuilder
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Get the parent model of the relation.
     *
     * @return \Database\Eloquent\Model
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Get the related model of the relation.
     *
     * @return \Database\Eloquent\Model
     */
    public function getRelated()
    {
        return $this->related;
    }

    /**
     * Handle dynamic method calls to the relationship.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $result = $this->query->{$method}(...$parameters);

        if ($result === $this->query) {
            return $this;
        }

        return $result;
    }

    /**
     * Run a callback with constraints disabled on the relation.
     *
     * @param  \Closure  $callback
     * @return mixed
     */
    public static function noConstraints(\Closure $callback)
    {
        $previous = static::$constraints;

        static::$constraints = false;

        try {
            return $callback();
        } finally {
            static::$constraints = $previous;
        }
    }

    /**
     * Indicates if constraints are being applied.
     *
     * @var bool
     */
    protected static $constraints = true;
}