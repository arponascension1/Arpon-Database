<?php

namespace Arpon\Database\Schema\Definitions;

use Arpon\Database\Schema\Blueprint;

class ForeignIdDefinition extends ColumnDefinition
{
    /**
     * The blueprint instance.
     *
     * @var \Arpon\Database\Schema\Blueprint
     */
    protected $blueprint;

    /**
     * The column name.
     *
     * @var string
     */
    protected $column;

    /**
     * Create a new foreign ID definition.
     *
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @param  \Arpon\Database\Schema\ColumnDefinition  $column
     * @param  string  $columnName
     */
    public function __construct(Blueprint $blueprint, ColumnDefinition $column, $columnName)
    {
        parent::__construct($column->getAttributes());
        $this->blueprint = $blueprint;
        $this->column = $columnName;
    }

    /**
     * Create the foreign key constraint for this column.
     *
     * @param  string|null  $table
     * @param  string  $column
     * @return \Arpon\Database\Schema\Definitions\ForeignKeyDefinition
     */
    public function constrained($table = null, $column = 'id')
    {
        // If no table specified, derive from column name (e.g., user_id -> users)
        if (is_null($table)) {
            $table = $this->guessForeignTableName();
        }

        // Create the foreign key constraint
        return $this->blueprint->foreign($this->column)->references($column)->on($table);
    }

    /**
     * Guess the foreign table name from the column name.
     *
     * @return string
     */
    protected function guessForeignTableName()
    {
        // Remove '_id' suffix and pluralize (e.g., user_id -> users)
        $tableName = str_replace('_id', '', $this->column);
        return $this->pluralize($tableName);
    }

    /**
     * Simple pluralization (basic implementation).
     *
     * @param  string  $singular
     * @return string
     */
    protected function pluralize($singular)
    {
        // Basic pluralization rules
        if (substr($singular, -1) === 'y') {
            return substr($singular, 0, -1) . 'ies';
        } elseif (in_array(substr($singular, -1), ['s', 'x', 'z']) || 
                  in_array(substr($singular, -2), ['ch', 'sh'])) {
            return $singular . 'es';
        } else {
            return $singular . 's';
        }
    }

    /**
     * Proxy method calls to the parent ColumnDefinition.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        // Call the method on the parent and return this for chaining
        $result = parent::__call($method, $parameters);
        
        return $result === $this->getAttributes() ? $this : $result;
    }
}