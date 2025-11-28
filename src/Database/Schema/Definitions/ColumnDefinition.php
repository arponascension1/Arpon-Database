<?php

namespace Arpon\Database\Schema\Definitions;

use Arpon\Database\Support\Fluent;

class ColumnDefinition extends Fluent
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * Allow the column to be null.
     *
     * @return $this
     */
    public function nullable($value = true)
    {
        $this->attributes['nullable'] = $value;

        return $this;
    }

    /**
     * Place the column "after" another column (MySQL).
     *
     * @param  string  $column
     * @return $this
     */
    public function after($column)
    {
        $this->attributes['after'] = $column;

        return $this;
    }

    /**
     * Used as a modifier for generatedAs() (PostgreSQL).
     *
     * @param  bool  $value
     * @return $this
     */
    public function always($value = true)
    {
        $this->attributes['always'] = $value;

        return $this;
    }

    /**
     * Set INTEGER columns as auto-increment (primary key).
     *
     * @return $this
     */
    public function autoIncrement()
    {
        $this->attributes['autoIncrement'] = true;

        return $this;
    }

    /**
     * Change the column.
     *
     * @return $this
     */
    public function change()
    {
        $this->attributes['change'] = true;

        return $this;
    }

    /**
     * Set the character set for the column.
     *
     * @param  string  $charset
     * @return $this
     */
    public function charset($charset)
    {
        $this->attributes['charset'] = $charset;

        return $this;
    }

    /**
     * Set the collation for the column.
     *
     * @param  string  $collation
     * @return $this
     */
    public function collation($collation)
    {
        $this->attributes['collation'] = $collation;

        return $this;
    }

    /**
     * Add a comment to the column.
     *
     * @param  string  $comment
     * @return $this
     */
    public function comment($comment)
    {
        $this->attributes['comment'] = $comment;

        return $this;
    }

    /**
     * Specify a "default" value for the column.
     *
     * @param  mixed  $value
     * @return $this
     */
    public function default($value)
    {
        $this->attributes['default'] = $value;

        return $this;
    }

    /**
     * Place the column "first" in the table (MySQL).
     *
     * @return $this
     */
    public function first()
    {
        $this->attributes['first'] = true;

        return $this;
    }

    /**
     * Create an index on the column.
     *
     * @param  string|null  $indexName
     * @return $this
     */
    public function index($indexName = null)
    {
        $this->attributes['index'] = $indexName ?: true;

        return $this;
    }

    /**
     * Set the column as the primary key.
     *
     * @return $this
     */
    public function primary()
    {
        $this->attributes['primary'] = true;

        return $this;
    }

    /**
     * Create a unique index on the column.
     *
     * @param  string|null  $indexName
     * @return $this
     */
    public function unique($indexName = null)
    {
        $this->attributes['unique'] = $indexName ?: true;

        return $this;
    }

    /**
     * Set the INTEGER column as UNSIGNED (MySQL).
     *
     * @return $this
     */
    public function unsigned()
    {
        $this->attributes['unsigned'] = true;

        return $this;
    }

    /**
     * Set the TIMESTAMP column to use CURRENT_TIMESTAMP as default value.
     *
     * @return $this
     */
    public function useCurrent()
    {
        $this->attributes['useCurrent'] = true;

        return $this;
    }

    /**
     * Set the TIMESTAMP column to use CURRENT_TIMESTAMP when updating (MySQL).
     *
     * @return $this
     */
    public function useCurrentOnUpdate()
    {
        $this->attributes['useCurrentOnUpdate'] = true;

        return $this;
    }

    /**
     * Create a virtual generated column (MySQL).
     *
     * @param  string  $expression
     * @return $this
     */
    public function virtualAs($expression)
    {
        $this->attributes['virtualAs'] = $expression;

        return $this;
    }

    /**
     * Create a stored generated column (MySQL).
     *
     * @param  string  $expression
     * @return $this
     */
    public function storedAs($expression)
    {
        $this->attributes['storedAs'] = $expression;

        return $this;
    }

    /**
     * Specify that the column should be zerofill (MySQL).
     *
     * @return $this
     */
    public function zerofill()
    {
        $this->attributes['zerofill'] = true;

        return $this;
    }
}