<?php

namespace Arpon\Database\Schema\Definitions;

use Arpon\Database\Support\Fluent;

class ForeignKeyDefinition extends Fluent
{
    /**
     * Create a new foreign key definition.
     *
     * @param  \Arpon\Database\Support\Fluent  $base
     * @return void
     */
    public function __construct(Fluent $base)
    {
        parent::__construct($base->getAttributes());
    }

    /**
     * Set the referenced column.
     *
     * @param  string  $column
     * @return $this
     */
    public function references($column)
    {
        $this->attributes['references'] = $column;

        return $this;
    }

    /**
     * Set the referenced table.
     *
     * @param  string  $table
     * @return $this
     */
    public function on($table)
    {
        $this->attributes['on'] = $table;

        return $this;
    }

    /**
     * Set the action to be performed on update.
     *
     * @param  string  $action
     * @return $this
     */
    public function onUpdate($action)
    {
        $this->attributes['onUpdate'] = $action;

        return $this;
    }

    /**
     * Set the action to be performed on delete.
     *
     * @param  string  $action
     * @return $this
     */
    public function onDelete($action)
    {
        $this->attributes['onDelete'] = $action;

        return $this;
    }

    /**
     * Set the foreign key constraint name.
     *
     * @param  string  $name
     * @return $this
     */
    public function name($name)
    {
        $this->attributes['name'] = $name;

        return $this;
    }

    /**
     * Indicate that updates should cascade.
     *
     * @return $this
     */
    public function cascadeOnUpdate()
    {
        return $this->onUpdate('cascade');
    }

    /**
     * Indicate that updates should restrict.
     *
     * @return $this
     */
    public function restrictOnUpdate()
    {
        return $this->onUpdate('restrict');
    }

    /**
     * Indicate that deletes should cascade.
     *
     * @return $this
     */
    public function cascadeOnDelete()
    {
        return $this->onDelete('cascade');
    }

    /**
     * Indicate that deletes should restrict.
     *
     * @return $this
     */
    public function restrictOnDelete()
    {
        return $this->onDelete('restrict');
    }

    /**
     * Indicate that deletes should set null.
     *
     * @return $this
     */
    public function nullOnDelete()
    {
        return $this->onDelete('set null');
    }
}