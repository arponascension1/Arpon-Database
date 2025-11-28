<?php

namespace Arpon\Database\Eloquent\Relations;

use Arpon\Database\Eloquent\EloquentBuilder;
use Arpon\Database\Eloquent\Model;
use Arpon\Database\Eloquent\Collection;

class MorphOne extends MorphOneOrMany
{
    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        return $this->query->first() ?: $this->getDefaultFor($this->parent);
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
     * @param  \Arpon\Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        return $this->matchOne($models, $results, $relation);
    }

    /**
     * Get the default value for this relation.
     *
     * @param  \Arpon\Database\Eloquent\Model  $model
     * @return \Arpon\Database\Eloquent\Model|null
     */
    protected function getDefaultFor(Model $model)
    {
        return null;
    }
}