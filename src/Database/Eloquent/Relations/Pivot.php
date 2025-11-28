<?php

namespace Arpon\Database\Eloquent\Relations;

use Arpon\Database\Eloquent\Model;

class Pivot extends Model
{
    /**
     * The parent model of the relationship.
     *
     * @var \Arpon\Database\Eloquent\Model
     */
    public $parent;

    /**
     * The name of the foreign key column.
     *
     * @var string
     */
    public $foreignKey;

    /**
     * The name of the other key column.
     *
     * @var string
     */
    public $otherKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected array $fillable = [];

    /**
     * Create a new pivot model instance.
     *
     * @param  \Arpon\Database\Eloquent\Model  $parent
     * @param  array  $attributes
     * @param  string  $table
     * @param  bool  $exists
     * @return void
     */
    public function __construct(Model $parent, array $attributes, string $table, bool $exists = false)
    {
        parent::__construct($attributes);

        $this->parent = $parent;
        $this->setTable($table);
        $this->exists = $exists;
        $this->timestamps = $this->hasTimestampAttributes();
    }

    /**
     * Delete the pivot model from the database.
     *
     * @return bool
     */
    public function delete(): bool
    {
        if (isset($this->attributes[$this->foreignKey]) && isset($this->attributes[$this->otherKey])) {
            return $this->getDeleteQuery()->delete() > 0;
        }

        return false;
    }

    /**
     * Get the query builder for a delete operation.
     *
     * @return \Arpon\Database\Query\Builder
     */
    protected function getDeleteQuery()
    {
        return $this->newQueryWithoutRelationships()->where([
            $this->foreignKey => $this->attributes[$this->foreignKey],
            $this->otherKey => $this->attributes[$this->otherKey],
        ]);
    }

    /**
     * Determine if the model uses timestamps.
     *
     * @return bool
     */
    protected function hasTimestampAttributes(): bool
    {
        return array_key_exists($this->getCreatedAtColumn(), $this->attributes);
    }

    /**
     * Get the name of the "created at" column.
     *
     * @return ?string
     */
    public function getCreatedAtColumn(): ?string
    {
        return $this->parent->getCreatedAtColumn();
    }

    /**
     * Get the name of the "updated at" column.
     *
     * @return ?string
     */
    public function getUpdatedAtColumn(): ?string
    {
        return $this->parent->getUpdatedAtColumn();
    }

    /**
     * Set the pivot key names on the pivot instance.
     *
     * @param string $foreignKey
     * @param string $otherKey
     * @return $this
     */
    public function setPivotKeys(string $foreignKey, string $otherKey)
    {
        $this->foreignKey = $foreignKey;
        $this->otherKey = $otherKey;

        return $this;
    }
}