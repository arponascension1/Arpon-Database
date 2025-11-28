<?php

namespace Arpon\Database\Schema\Grammars;

class MySqlGrammar extends Grammar
{
    /**
     * The possi    /**
     * Compile a drop table if exists command.
     * The possible column modifiers.
     *
     * @var array
     */
    protected $modifiers = [
        'Unsigned', 'Charset', 'Collate', 'VirtualAs', 'StoredAs',
        'Nullable', 'Default', 'Increment', 'Comment', 'After', 'First'
    ];

    /**
     * The possible column serials.
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

        // Once we have the primary create table clause, we can add any storage or
        // charset specifications to the SQL. MySQL allows an engine as well as
        // a character set that may be specified with the table upon creation.
        $sql = $this->compileCreateEncoding($sql, $blueprint);

        return $this->compileCreateEngine($sql, $blueprint);
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
        $columns = $this->getColumns($blueprint);
        
        // Add indexes
        $columns = array_merge($columns, $this->getKeys($blueprint));
        
        // Add foreign keys
        $foreignKeys = $this->getForeignKeys($blueprint);
        if (!empty($foreignKeys)) {
            $columns = array_merge($columns, $foreignKeys);
        }
        
        return sprintf('%s table %s (%s)',
            $blueprint->temporary ? 'create temporary' : 'create',
            $this->wrapTable($blueprint->getTable()),
            implode(', ', $columns)
        );
    }

    /**
     * Get the foreign key constraints for the table.
     *
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @return array
     */
    protected function getForeignKeys($blueprint)
    {
        $constraints = [];
        
        foreach ($this->getCommandsByName($blueprint, 'foreign') as $foreign) {
            $constraint = sprintf('constraint %s foreign key (%s) references %s (%s)',
                $this->wrap($foreign->index),
                $this->columnize($foreign->columns),
                $this->wrapTable($foreign->on),
                $this->columnize((array) $foreign->references)
            );

            if (! is_null($foreign->onDelete)) {
                $constraint .= " on delete {$foreign->onDelete}";
            }

            if (! is_null($foreign->onUpdate)) {
                $constraint .= " on update {$foreign->onUpdate}";
            }

            $constraints[] = $constraint;
        }
        
        return $constraints;
    }

    /**
     * Get the key constraints for the table.
     *
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @return array
     */
    protected function getKeys($blueprint)
    {
        $keys = [];

        foreach (['primary', 'unique', 'index'] as $type) {
            foreach ($this->getCommandsByName($blueprint, $type) as $command) {
                $method = 'get'.ucfirst($type).'Key';
                if (method_exists($this, $method)) {
                    $keys[] = $this->$method($command);
                }
            }
        }

        return array_filter($keys);
    }

    /**
     * Get a primary key constraint.
     *
     * @param  \Arpon\Database\Support\Fluent  $command
     * @return string
     */
    protected function getPrimaryKey($command)
    {
        return 'primary key ('.implode(', ', $this->wrapArray($command->columns)).')';
    }

    /**
     * Get a unique key constraint.
     *
     * @param  \Arpon\Database\Support\Fluent  $command
     * @return string
     */
    protected function getUniqueKey($command)
    {
        return 'unique key '.$this->wrap($command->index).' ('.implode(', ', $this->wrapArray($command->columns)).')';
    }

    /**
     * Get an index constraint.
     *
     * @param  \Arpon\Database\Support\Fluent  $command
     * @return string
     */
    protected function getIndexKey($command)
    {
        return 'key '.$this->wrap($command->index).' ('.implode(', ', $this->wrapArray($command->columns)).')';
    }

    /**
     * Append the character set specifications to a command.
     *
     * @param  string  $sql
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @return string
     */
    protected function compileCreateEncoding($sql, $blueprint)
    {
        // First we will set the character set if one has been set on either the create
        // blueprint itself or on the root configuration for the connection that the
        // table is being created on. We will add these to the create table query.
        if (isset($blueprint->charset)) {
            $sql .= ' default character set '.$blueprint->charset;
        }

        if (isset($blueprint->collation)) {
            $sql .= " collate '{$blueprint->collation}'";
        }

        return $sql;
    }

    /**
     * Append the engine specifications to a command.
     *
     * @param  string  $sql
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @return string
     */
    protected function compileCreateEngine($sql, $blueprint)
    {
        if (isset($blueprint->engine)) {
            return $sql.' engine = '.$blueprint->engine;
        }

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
        return 'drop table '.$this->wrapTable($blueprint->getTable());
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
        return 'drop table if exists '.$this->wrapTable($blueprint->getTable());
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

        return "rename table {$from} to ".$this->wrapTable($command->to);
    }

    /**
     * Compile the query to determine the list of tables.
     *
     * @return string
     */
    public function compileTableExists()
    {
        return "select * from information_schema.tables where table_schema = database() and table_name = ? and table_type = 'BASE TABLE'";
    }

    /**
     * Compile the query to determine the list of columns.
     *
     * @param  string  $table
     * @return string
     */
    public function compileColumnListing($table)
    {
        return "select column_name as `name` from information_schema.columns where table_schema = database() and table_name = ?";
    }

    /**
     * Compile the command to enable foreign key constraints.
     *
     * @return string
     */
    public function compileEnableForeignKeyConstraints()
    {
        return 'SET FOREIGN_KEY_CHECKS=1;';
    }

    /**
     * Compile a drop column command.
     *
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @param  \Arpon\Database\Support\Fluent  $command
     * @return string
     */
    public function compileDropColumn($blueprint, $command)
    {
        $columns = $this->prefixArray('drop column', $this->wrapArray($command->columns));

        return 'alter table '.$this->wrapTable($blueprint->getTable()).' '.implode(', ', $columns);
    }

    /**
     * Compile a rename column command.
     *
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @param  \Arpon\Database\Support\Fluent  $command
     * @return string
     */
    public function compileRenameColumn($blueprint, $command)
    {
        return sprintf('alter table %s change %s %s %s',
            $this->wrapTable($blueprint->getTable()),
            $this->wrap($command->from),
            $this->wrap($command->to),
            $this->getType($this->getColumn($blueprint, $command->to))
        );
    }

    /**
     * Compile a create index command.
     * 
     * Note: For MySQL, indexes are now handled during CREATE TABLE,
     * so we don't need separate ALTER TABLE statements for new tables.
     * This prevents duplicate index errors.
     *
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @param  \Arpon\Database\Support\Fluent  $command
     * @return string|null
     */
    public function compileIndex($blueprint, $command)
    {
        // For MySQL, indexes are included in CREATE TABLE statements,
        // so we return null to skip separate ALTER TABLE execution.
        // This prevents "Duplicate key name" errors.
        return null;
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
        return 'alter table '.$this->wrapTable($blueprint->getTable()).' drop index '.$command->index;
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
        return 'alter table '.$this->wrapTable($blueprint->getTable()).' drop index '.$command->index;
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
        return 'alter table '.$this->wrapTable($blueprint->getTable()).' drop foreign key '.$command->index;
    }

    /**
     * Compile a foreign key command.
     * 
     * Note: For MySQL, foreign keys are now handled during CREATE TABLE,
     * so we don't need separate ALTER TABLE statements for new tables.
     * This prevents duplicate foreign key constraints.
     *
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @param  \Arpon\Database\Support\Fluent  $command
     * @return string|null
     */
    public function compileForeign($blueprint, $command)
    {
        // For MySQL, foreign keys are included in CREATE TABLE statements,
        // so we return null to skip separate ALTER TABLE execution.
        // This prevents "Duplicate foreign key constraint" errors.
        return null;
    }

    /**
     * Compile the command to disable foreign key constraints.
     *
     * @return string
     */
    public function compileDisableForeignKeyConstraints()
    {
        return 'SET FOREIGN_KEY_CHECKS=0;';
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
        return 'bigint';
    }

    /**
     * Create the column definition for an integer type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeInteger($column)
    {
        return 'int';
    }

    /**
     * Create the column definition for a medium integer type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeMediumInteger($column)
    {
        return 'mediumint';
    }

    /**
     * Create the column definition for a small integer type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeSmallInteger($column)
    {
        return 'smallint';
    }

    /**
     * Create the column definition for a tiny integer type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeTinyInteger($column)
    {
        return 'tinyint';
    }

    /**
     * Create the column definition for a string type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeString($column)
    {
        return "varchar({$column->length})";
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
     * Create the column definition for a medium text type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeMediumText($column)
    {
        return 'mediumtext';
    }

    /**
     * Create the column definition for a long text type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeLongText($column)
    {
        return 'longtext';
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
        $columnType = 'datetime';

        if ($column->precision) {
            $columnType .= "({$column->precision})";
        }

        return $columnType;
    }

    /**
     * Create the column definition for a timestamp type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeTimestamp($column)
    {
        $columnType = 'timestamp';

        if ($column->precision) {
            $columnType .= "({$column->precision})";
        }

        return $columnType;
    }

    /**
     * Create the column definition for a decimal type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeDecimal($column)
    {
        return "decimal({$column->precision}, {$column->scale})";
    }

    /**
     * Create the column definition for a float type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeFloat($column)
    {
        return $this->typeDouble($column);
    }

    /**
     * Create the column definition for a double type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeDouble($column)
    {
        return 'double';
    }

    /**
     * Create the column definition for a JSON type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeJson($column)
    {
        return 'json';
    }

    /**
     * Create the column definition for a JSONB type (MySQL uses JSON).
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeJsonb($column)
    {
        return 'json';
    }

    /**
     * Create the column definition for a UUID type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeUuid($column)
    {
        return 'char(36)';
    }

    /**
     * Create the column definition for an enum type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeEnum($column)
    {
        return "enum('".implode("', '", $column->allowed)."')";
    }

    /**
     * Create the column definition for a set type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeSet($column)
    {
        return "set('".implode("', '", $column->allowed)."')";
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
     * Create the column definition for a year type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeYear($column)
    {
        return 'year';
    }

    /**
     * Create the column definition for a geometry type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typeGeometry($column)
    {
        return 'geometry';
    }

    /**
     * Create the column definition for a point type.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string
     */
    protected function typePoint($column)
    {
        return 'point';
    }

    /**
     * Get the SQL for a nullable column modifier.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string|null
     */
    protected function modifyNullable($column)
    {
        if (is_null($column->virtualAs) && is_null($column->storedAs)) {
            return $column->nullable ? ' null' : ' not null';
        }
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
            return ' auto_increment primary key';
        }
    }

    /**
     * Get the SQL for an unsigned column modifier.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string|null
     */
    protected function modifyUnsigned($column)
    {
        if ($column->unsigned) {
            return ' unsigned';
        }
    }

    /**
     * Get the SQL for a character set column modifier.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string|null
     */
    protected function modifyCharset($column)
    {
        if (! is_null($column->charset)) {
            return ' character set '.$column->charset;
        }
    }

    /**
     * Get the SQL for a collation column modifier.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string|null
     */
    protected function modifyCollate($column)
    {
        if (! is_null($column->collation)) {
            return " collate '{$column->collation}'";
        }
    }

    /**
     * Get the SQL for a comment column modifier.
     *
     * @param  \Arpon\Database\Schema\Definitions\ColumnDefinition  $column
     * @return string|null
     */
    protected function modifyComment($column)
    {
        if (! is_null($column->comment)) {
            return " comment '".$column->comment."'";
        }
    }

    /**
     * Wrap a table in keyword identifiers.
     *
     * @param  string  $table
     * @return string
     */
    public function wrapTable($table)
    {
        return '`' . str_replace('`', '``', $table) . '`';
    }

    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param  string  $value
     * @return string
     */
    protected function wrapValue($value)
    {
        if ($value !== '*') {
            return '`'.str_replace('`', '``', $value).'`';
        }

        return $value;
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
     * Get a column instance by name.
     *
     * @param  \Arpon\Database\Schema\Blueprint  $blueprint
     * @param  string  $name
     * @return \Arpon\Database\Schema\ColumnDefinition|null
     */
    protected function getColumn($blueprint, $name)
    {
        foreach ($blueprint->getColumns() as $column) {
            if ($column->name === $name) {
                return $column;
            }
        }
        return null;
    }
}