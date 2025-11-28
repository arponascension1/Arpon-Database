<?php

namespace Arpon\Database\Eloquent\Relations;

use Arpon\Database\Eloquent\EloquentBuilder;
use Arpon\Database\Eloquent\Model;
use Arpon\Database\Eloquent\Collection;

class MorphTo extends BelongsTo
{
    /**
     * The type of the polymorphic relation.
     *
     * @var string
     */
    protected $morphType;

    /**
     * The models whose relations are being eager loaded.
     *
     * @var \Arpon\Database\Eloquent\Collection
     */
    protected $models;

    /**
     * All of the models keyed by type.
     *
     * @var array
     */
    protected $dictionary = [];

    /**
     * Create a new morph to relationship instance.
     *
     * @param  \Arpon\Database\Eloquent\EloquentBuilder  $query
     * @param  \Arpon\Database\Eloquent\Model  $parent
     * @param  string  $foreignKey
     * @param  string  $ownerKey
     * @param  string  $type
     * @param  string  $relation
     * @return void
     */
    public function __construct(EloquentBuilder $query, Model $parent, $foreignKey, $ownerKey, $type, $relation)
    {
        $this->morphType = $type;

        parent::__construct($query, $parent, $foreignKey, $ownerKey, $relation);
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        $this->buildDictionary($this->models = Collection::make($models));
    }

    /**
     * Build a dictionary with the models.
     *
     * @param  \Arpon\Database\Eloquent\Collection  $models
     * @return void
     */
    protected function buildDictionary(Collection $models)
    {
        foreach ($models as $model) {
            if ($model->{$this->morphType}) {
                $this->dictionary[$model->{$this->morphType}][$model->{$this->foreignKey}][] = $model;
            }
        }
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        if (! $this->parent->{$this->foreignKey}) {
            return null;
        }

        if (is_null($this->parent->{$this->morphType})) {
            return null;
        }

        return $this->getResultsByType($this->parent->{$this->morphType});
    }

    /**
     * Get the results of the relationship for the given type.
     *
     * @param  string  $type
     * @return mixed
     */
    protected function getResultsByType($type)
    {
        $instance = $this->createModelByType($type);

        $ownerKey = $this->ownerKey ?? $instance->getKeyName();

        return $instance->newQueryWithoutScopes()->find($this->parent->{$this->foreignKey});
    }

    /**
     * Create a model instance by type.
     *
     * @param  string  $type
     * @return \Arpon\Database\Eloquent\Model
     */
    public function createModelByType($type)
    {
        $class = Model::getActualClassNameForMorph($type);

        $instance = new $class;

        if (! $instance->getConnectionName()) {
            $instance->setConnection($this->parent->getConnectionName());
        }

        return $instance;
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
        return $models;
    }

    /**
     * Associate the model instance to the given parent.
     *
     * @param  \Arpon\Database\Eloquent\Model  $model
     * @return \Arpon\Database\Eloquent\Model
     */
    public function associate($model)
    {
        $this->parent->setAttribute(
            $this->foreignKey, $model instanceof Model ? $model->getAttribute($this->ownerKey) : null
        );

        $this->parent->setAttribute(
            $this->morphType, $model instanceof Model ? $model->getMorphClass() : null
        );

        return $this->parent->setRelation($this->relationName, $model);
    }

    /**
     * Dissociate previously associated model from the given parent.
     *
     * @return \Arpon\Database\Eloquent\Model
     */
    public function dissociate()
    {
        $this->parent->setAttribute($this->foreignKey, null);
        $this->parent->setAttribute($this->morphType, null);

        return $this->parent->setRelation($this->relationName, null);
    }

    /**
     * Get the foreign key "type" name.
     *
     * @return string
     */
    public function getMorphType()
    {
        return $this->morphType;
    }

    /**
     * Get the dictionary used by the eager loading.
     *
     * @return array
     */
    public function getDictionary()
    {
        return $this->dictionary;
    }
}