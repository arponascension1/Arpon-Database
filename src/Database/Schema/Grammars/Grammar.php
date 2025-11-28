<?php

namespace Arpon\Database\Schema\Grammars;

use Arpon\Database\Grammar as BaseGrammar;
use Arpon\Database\Schema\Blueprint;

class Grammar extends BaseGrammar
{
    /**
     * The components that make up a select clause.
     *
     * @var array
     */
    protected $selectComponents = [
        'aggregate',
        'columns',
        'from',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'limit',
        'offset',
        'unions',
        'lock',
    ];

    /**
     * Compile a create table command.
     *
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @param  \Arpon\Database\Support\Fluent  $command
     * @return string
     */
    public function compileCreate($blueprint, $command)
    {
        return "CREATE TABLE " . $this->wrapTable($blueprint->getTable()) . " (id INTEGER PRIMARY KEY AUTOINCREMENT)";
    }

    /**
     * Get the columns for the table creation.
     *
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @return array
     */
    protected function getColumns($blueprint)
    {
        $columns = [];

        foreach ($blueprint->getAddedColumns() as $column) {
            // Each column in the blueprint has a type (e.g. string, integer)
            // and we need to convert that to SQL
            $sql = $this->wrap($column->name) . ' ' . $this->getType($column);

            $columns[] = $this->addModifiers($sql, $blueprint, $column);
        }

        return $columns;
    }

    /**
     * Get the SQL for the column data type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function getType($column)
    {
        return $this->{'type'.ucfirst($column->type)}($column);
    }

    /**
     * Add the column modifiers to the definition.
     *
     * @param  string  $sql
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function addModifiers($sql, $blueprint, $column)
    {
        foreach ($this->modifiers as $modifier) {
            if (method_exists($this, $method = "modify{$modifier}")) {
                $sql .= $this->{$method}($column);
            }
        }

        return $sql;
    }

    /**
     * Get the default value for a column.
     *
     * @param  mixed  $value
     * @return string
     */
    protected function getDefaultValue($value)
    {
        if ($value instanceof \Arpon\Database\Query\Expression) {
            return $value;
        }

        return is_bool($value)
                    ? "'".(int) $value."'"
                    : "'".$value."'";
    }

    /**
     * Get the commands for the schema build.
     *
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @param  string  $name
     * @return \Arpon\Database\Support\Fluent[]
     */
    protected function getCommandsByName($blueprint, $name)
    {
        return array_filter($blueprint->getCommands(), function ($command) use ($name) {
            return $command->name == $name;
        });
    }

    /**
     * Get a single command by name.
     *
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @param  string  $name
     * @return \Arpon\Database\Support\Fluent|null
     */
    protected function getCommandByName($blueprint, $name)
    {
        $commands = $this->getCommandsByName($blueprint, $name);

        return count($commands) > 0 ? reset($commands) : null;
    }

    /**
     * Wrap an array of values.
     *
     * @param  array  $values
     * @return array
     */
    public function wrapArray(array $values)
    {
        return array_map([$this, 'wrap'], $values);
    }

    /**
     * Wrap a table in keyword identifiers.
     *
     * @param  mixed  $table
     * @return string
     */
    public function wrapTable($table)
    {
        if ($table instanceof Blueprint) {
            $table = $table->getTable();
        }

        return parent::wrapTable($table);
    }
}