<?php

namespace Arpon\Database\Schema\Grammars;

use Arpon\Database\Schema\Blueprint;

class SqliteGrammar extends Grammar
{
    /**
     * The possible column modifiers.
     *
     * @var array
     */
    protected $modifiers = ['Nullable', 'Default', 'Increment'];

    /**
     * The columns available as serials.
     *
     * @var array
     */
    protected $serials = ['bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger'];

    /**
     * Compile a create table command.
     *
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @param  \Arpon\Database\Support\Fluent  $command
     * @return string
     */
    public function compileCreate($blueprint, $command)
    {
        $sql = $this->compileCreateTable(
            $blueprint, $command
        );

        // SQLite supports foreign key constraints, so we can add them.
        $sql = $this->compileCreateTableConstraints($sql, $blueprint);

        return $sql;
    }

    /**
     * Create the main create table clause.
     *
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @param  \Arpon\Database\Support\Fluent  $command
     * @return string
     */
    protected function compileCreateTable($blueprint, $command)
    {
        return sprintf('%s table %s (%s%s%s)',
            $blueprint->temporary ? 'create temporary' : 'create',
            $this->wrapTable($blueprint),
            implode(', ', $this->getColumns($blueprint)),
            (string) $this->addForeignKeys($blueprint),
            (string) $this->addPrimaryKeys($blueprint)
        );
    }

    /**
     * Get the foreign key syntax for a table creation statement.
     *
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @return string|null
     */
    protected function addForeignKeys($blueprint)
    {
        $foreigns = $this->getCommandsByName($blueprint, 'foreign');

        return collect($foreigns)->reduce(function ($sql, $foreign) {
            // We'll add each foreign key separately as a constraint.
            $sql .= $this->getForeignKey($foreign);

            return $sql;
        }, '');
    }

    /**
     * Get the primary key syntax for a table creation statement.
     *
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @return string|null
     */
    protected function addPrimaryKeys($blueprint)
    {
        $primary = $this->getCommandByName($blueprint, 'primary');

        if (! is_null($primary)) {
            return ', primary key ('.implode(', ', $this->wrapArray($primary->columns)).')';
        }
    }

    /**
     * Get the SQL for a foreign key constraint.
     *
     * @param  \Arpon\Database\Support\Fluent  $foreign
     * @return string
     */
    protected function getForeignKey($foreign)
    {
        $sql = sprintf(', foreign key(%s) references %s(%s)',
            implode(', ', $this->wrapArray((array) $foreign->columns)),
            $this->wrapTable($foreign->on),
            implode(', ', $this->wrapArray((array) $foreign->references))
        );

        // Add ON DELETE and ON UPDATE clauses if specified
        if (! is_null($foreign->onDelete)) {
            $sql .= " on delete {$foreign->onDelete}";
        }

        if (! is_null($foreign->onUpdate)) {
            $sql .= " on update {$foreign->onUpdate}";
        }

        return $sql;
    }

    /**
     * Append the character set specifications to a command (SQLite doesn't support this).
     *
     * @param  string  $sql
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @return string
     */
    protected function compileCreateTableConstraints($sql, $blueprint)
    {
        return $sql;
    }

    /**
     * Compile a drop table command.
     *
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @param  \Arpon\Database\Support\Fluent  $command
     * @return string
     */
    public function compileDrop($blueprint, $command)
    {
        return 'drop table '.$this->wrapTable($blueprint);
    }

    /**
     * Compile a drop table (if exists) command.
     *
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @param  \Arpon\Database\Support\Fluent  $command
     * @return string
     */
    public function compileDropIfExists($blueprint, $command)
    {
        return 'drop table if exists '.$this->wrapTable($blueprint);
    }

    /**
     * Compile a rename table command.
     *
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @param  \Arpon\Database\Support\Fluent  $command
     * @return string
     */
    public function compileRename($blueprint, $command)
    {
        $from = $this->wrapTable($blueprint);

        return "alter table {$from} rename to ".$this->wrapTable($command->to);
    }

    /**
     * Compile the query to determine if a table exists.
     *
     * @return string
     */
    public function compileTableExists()
    {
        return "select * from sqlite_master where type = 'table' and name = ?";
    }

    /**
     * Compile the query to determine the list of columns.
     *
     * @param  string  $table
     * @return string
     */
    public function compileColumnListing($table)
    {
        return "select name from pragma_table_info(?) as table_info";
    }

    /**
     * Compile the command to enable foreign key constraints.
     *
     * @return string
     */
    public function compileEnableForeignKeyConstraints()
    {
        return 'PRAGMA foreign_keys = ON;';
    }

    /**
     * Compile the command to disable foreign key constraints.
     *
     * @return string
     */
    public function compileDisableForeignKeyConstraints()
    {
        return 'PRAGMA foreign_keys = OFF;';
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
     * Create the column definition for a big integer type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeBigInteger($column)
    {
        return 'integer';
    }

    /**
     * Create the column definition for an integer type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeInteger($column)
    {
        return 'integer';
    }

    /**
     * Create the column definition for a medium integer type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeMediumInteger($column)
    {
        return 'integer';
    }

    /**
     * Create the column definition for a small integer type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeSmallInteger($column)
    {
        return 'integer';
    }

    /**
     * Create the column definition for a tiny integer type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeTinyInteger($column)
    {
        return 'integer';
    }

    /**
     * Create the column definition for a string type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeString($column)
    {
        return 'varchar';
    }

    /**
     * Create the column definition for a text type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeText($column)
    {
        return 'text';
    }

    /**
     * Create the column definition for a boolean type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeBoolean($column)
    {
        return 'tinyint(1)';
    }

    /**
     * Create the column definition for a date type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeDate($column)
    {
        return 'date';
    }

    /**
     * Create the column definition for a date-time type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeDateTime($column)
    {
        return 'datetime';
    }

    /**
     * Create the column definition for a timestamp type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeTimestamp($column)
    {
        return 'datetime';
    }

    /**
     * Create the column definition for a time type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeTime($column)
    {
        return 'time';
    }

    /**
     * Create the column definition for a decimal type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeDecimal($column)
    {
        return 'numeric';
    }

    /**
     * Create the column definition for a float type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeFloat($column)
    {
        return 'float';
    }

    /**
     * Create the column definition for a double type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeDouble($column)
    {
        return 'float';
    }

    /**
     * Create the column definition for a JSON type (SQLite stores as text).
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeJson($column)
    {
        return 'text';
    }

    /**
     * Create the column definition for a JSONB type (SQLite stores as text).
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeJsonb($column)
    {
        return 'text';
    }

    /**
     * Create the column definition for a UUID type (SQLite stores as text).
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeUuid($column)
    {
        return 'varchar(36)';
    }

    /**
     * Create the column definition for an enum type (SQLite stores as text).
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeEnum($column)
    {
        return 'varchar(255)';
    }

    /**
     * Create the column definition for a set type (SQLite stores as text).
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeSet($column)
    {
        return 'text';
    }

    /**
     * Create the column definition for a binary type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeBinary($column)
    {
        return 'blob';
    }

    /**
     * Create the column definition for a longText type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeLongText($column)
    {
        return 'text';
    }

    /**
     * Create the column definition for a mediumText type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeMediumText($column)
    {
        return 'text';
    }

    /**
     * Create the column definition for a year type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeYear($column)
    {
        return 'integer';
    }

    /**
     * Create the column definition for a geometry type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeGeometry($column)
    {
        return 'text'; // SQLite doesn't have native geometry support
    }

    /**
     * Create the column definition for a point type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typePoint($column)
    {
        return 'text'; // SQLite doesn't have native point support
    }

    /**
     * Get the SQL for a nullable column modifier.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string|null
     */
    protected function modifyNullable($column)
    {
        return $column->nullable ? ' null' : ' not null';
    }

    /**
     * Get the SQL for a default column modifier.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string|null
     */
    protected function modifyDefault($column)
    {
        if (! is_null($column->default)) {
            return ' default '.$this->getDefaultValue($column->default);
        }
    }

    /**
     * Get the SQL for an auto-increment column modifier.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string|null
     */
    protected function modifyIncrement($column)
    {
        if (in_array($column->type, $this->serials) && $column->autoIncrement) {
            return ' primary key autoincrement';
        }
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

    /**
     * Compile a drop column command (SQLite doesn't support this directly).
     *
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @param  \Arpon\Database\Support\Fluent  $command
     * @return string
     */
    public function compileDropColumn($blueprint, $command)
    {
        // SQLite doesn't support dropping columns directly
        // This would require table recreation which is complex
        throw new \RuntimeException('SQLite doesn\'t support dropping columns. Consider recreating the table.');
    }

    /**
     * Compile a rename column command (SQLite doesn't support this directly).
     *
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @param  \Arpon\Database\Support\Fluent  $command
     * @return string
     */
    public function compileRenameColumn($blueprint, $command)
    {
        // SQLite doesn't support renaming columns directly
        throw new \RuntimeException('SQLite doesn\'t support renaming columns. Consider recreating the table.');
    }

    /**
     * Compile a create index command.
     *
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @param  \Arpon\Database\Support\Fluent  $command
     * @return string
     */
    public function compileIndex($blueprint, $command)
    {
        return sprintf('create index %s on %s (%s)',
            $command->index,
            $this->wrapTable($blueprint->getTable()),
            $this->columnize($command->columns)
        );
    }

    /**
     * Compile a drop index command.
     *
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @param  \Arpon\Database\Support\Fluent  $command
     * @return string
     */
    public function compileDropIndex($blueprint, $command)
    {
        return 'drop index '.$command->index;
    }

    /**
     * Compile a drop unique key command.
     *
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @param  \Arpon\Database\Support\Fluent  $command
     * @return string
     */
    public function compileDropUnique($blueprint, $command)
    {
        return 'drop index '.$command->index;
    }

    /**
     * Compile a drop foreign key command.
     *
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @param  \Arpon\Database\Support\Fluent  $command
     * @return string
     */
    public function compileDropForeign($blueprint, $command)
    {
        // SQLite handles foreign keys differently, but we'll return a placeholder
        throw new \RuntimeException('SQLite foreign key constraints are handled during table creation.');
    }
}