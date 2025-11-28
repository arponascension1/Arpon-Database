<?php

namespace Arpon\Database\Schema;

use Closure;
use Arpon\Database\Schema\Definitions\ColumnDefinition;
use Arpon\Database\Schema\Definitions\ForeignIdDefinition;
use Arpon\Database\Schema\Definitions\ForeignKeyDefinition;
use Arpon\Database\Support\Fluent;

class Blueprint
{
    /**
     * The table the blueprint describes.
     *
     * @var string
     */
    protected $table;

    /**
     * The columns that should be added to the table.
     *
     * @var array
     */
    protected $columns = [];

    /**
     * The commands that should be run for the table.
     *
     * @var array
     */
    protected $commands = [];

    /**
     * Whether to make the table temporary.
     *
     * @var bool
     */
    public $temporary = false;

    /**
     * The engine that should be used for the table.
     *
     * @var string
     */
    public $engine;

    /**
     * The default character set that should be used for the table.
     */
    public $charset;

    /**
     * The collation that should be used for the table.
     */
    public $collation;

    /**
     * Create a new schema blueprint.
     *
     * @param  string  $table
     * @param  \Closure|null  $callback
     * @return void
     */
    public function __construct($table, Closure $callback = null)
    {
        $this->table = $table;

        if (! is_null($callback)) {
            $callback($this);
        }
    }

    /**
     * Execute the blueprint against the database.
     *
     * @param  \Arpon\Database\Connection  $connection
     * @param  \Arpon\Database\Schema\Grammars\Grammar  $grammar
     * @return void
     */
    public function build($connection, $grammar)
    {
        foreach ($this->toSql($connection, $grammar) as $statement) {
            $connection->statement($statement);
        }
    }

    /**
     * Get the raw SQL statements for the blueprint.
     *
     * @param  \Arpon\Database\Connection  $connection
     * @param  \Arpon\Database\Schema\Grammars\Grammar  $grammar
     * @return array
     */
    public function toSql($connection, $grammar)
    {
        $this->addImpliedCommands($grammar);

        $statements = [];

        // Each type of command has a corresponding compiler function on the schema
        // grammar which is used to build the necessary SQL statements to build
        // the blueprint element, so we'll just call that compilers function.
        $this->ensureCommandsAreValid();

        foreach ($this->commands as $command) {
            $method = 'compile'.ucfirst($command->name);

            if (method_exists($grammar, $method)) {
                if (! is_null($sql = $grammar->$method($this, $command))) {
                    $statements = array_merge($statements, (array) $sql);
                }
            }
        }

        return $statements;
    }

    /**
     * Add the commands that are implied by the blueprint's state.
     *
     * @param  \Arpon\Database\Schema\Grammars\Grammar  $grammar
     * @return void
     */
    protected function addImpliedCommands($grammar)
    {
        if (count($this->getAddedColumns()) > 0 && ! $this->creating()) {
            array_unshift($this->commands, $this->createCommand('add'));
        }

        if (count($this->getChangedColumns()) > 0 && ! $this->creating()) {
            array_unshift($this->commands, $this->createCommand('change'));
        }

        $this->addFluentIndexes();
    }

    /**
     * Add the index commands fluently specified on columns.
     *
     * @return void
     */
    protected function addFluentIndexes()
    {
        foreach ($this->columns as $column) {
            foreach (['primary', 'unique', 'index', 'fulltext', 'spatialIndex'] as $index) {
                // If the index has been specified on the given column, but is simply equal
                // to "true" (boolean), no name has been specified for this index so the
                // index method can be called without a name and it will generate one.
                if ($column->{$index} === true) {
                    $this->{$index}($column->name);

                    continue 2;
                } elseif (isset($column->{$index})) {
                    $this->{$index}($column->name, $column->{$index});

                    continue 2;
                }
            }
        }
    }

    /**
     * Determine if the blueprint has a create command.
     *
     * @return bool
     */
    protected function creating()
    {
        return collect($this->commands)->contains(function ($command) {
            return $command->name === 'create';
        });
    }

    /**
     * Indicate that the table needs to be created.
     *
     * @return \Arpon\Database\Support\Fluent
     */
    public function create()
    {
        return $this->addCommand('create');
    }

    /**
     * Indicate that the table needs to be temporary.
     *
     * @return void
     */
    public function temporary()
    {
        $this->temporary = true;
    }

    /**
     * Indicate that the table should be dropped.
     *
     * @return \Arpon\Database\Support\Fluent
     */
    public function drop()
    {
        return $this->addCommand('drop');
    }

    /**
     * Indicate that the table should be dropped if it exists.
     *
     * @return \Arpon\Database\Support\Fluent
     */
    public function dropIfExists()
    {
        return $this->addCommand('dropIfExists');
    }

    /**
     * Indicate that the table should be renamed.
     *
     * @param  string  $to
     * @return \Arpon\Database\Support\Fluent
     */
    public function rename($to)
    {
        return $this->addCommand('rename', compact('to'));
    }

    /**
     * Specify the primary key(s) for the table.
     *
     * @param  string|array  $columns
     * @param  string  $name
     * @param  string|null  $algorithm
     * @return \Arpon\Database\Support\Fluent
     */
    public function primary($columns, $name = null, $algorithm = null)
    {
        return $this->indexCommand('primary', $columns, $name, $algorithm);
    }

    /**
     * Specify a unique index for the table.
     *
     * @param  string|array  $columns
     * @param  string  $name
     * @param  string|null  $algorithm
     * @return \Arpon\Database\Support\Fluent
     */
    public function unique($columns, $name = null, $algorithm = null)
    {
        return $this->indexCommand('unique', $columns, $name, $algorithm);
    }

    /**
     * Specify an index for the table.
     *
     * @param  string|array  $columns
     * @param  string  $name
     * @param  string|null  $algorithm
     * @return \Arpon\Database\Support\Fluent
     */
    public function index($columns, $name = null, $algorithm = null)
    {
        return $this->indexCommand('index', $columns, $name, $algorithm);
    }

    /**
     * Specify a foreign key for the table.
     *
     * @param  string|array  $columns
     * @param  string  $name
     * @return \Arpon\Database\Schema\Definitions\ForeignKeyDefinition
     */
    public function foreign($columns, $name = null)
    {
        $command = new ForeignKeyDefinition(
            $this->indexCommand('foreign', $columns, $name)
        );

        $this->commands[count($this->commands) - 1] = $command;

        return $command;
    }

    /**
     * Create a new auto-incrementing integer (4-byte) column on the table.
     *
     * @param  string  $column
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function increments($column)
    {
        return $this->unsignedInteger($column, true);
    }

    /**
     * Create a new auto-incrementing big integer (8-byte) column on the table.
     *
     * @param  string  $column
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function bigIncrements($column)
    {
        return $this->unsignedBigInteger($column, true);

    }

    /**
     * Create a new "id" column (auto-incrementing big integer primary key).
     *
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function id($column = 'id')
    {
        return $this->bigIncrements($column);
    }

    /**
     * Create a new unsigned big integer (8-byte) column on the table.
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function unsignedBigInteger($column, $autoIncrement = false)
    {
        return $this->addColumn('bigInteger', $column, compact('autoIncrement'))->unsigned();
    }

    /**
     * Create a new foreign ID column (unsigned big integer with foreign key support).
     *
     * @param  string  $column
     * @return \Arpon\Database\Schema\Definitions\ForeignIdDefinition
     */
    public function foreignId($column)
    {
        $columnDefinition = $this->unsignedBigInteger($column);
        
        return new ForeignIdDefinition($this, $columnDefinition, $column);
    }

    /**
     * Create a new unsigned integer (4-byte) column on the table.
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function unsignedInteger($column, $autoIncrement = false)
    {
        return $this->addColumn('integer', $column, compact('autoIncrement'))->unsigned();
    }

    /**
     * Create a new big integer (8-byte) column on the table.
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function bigInteger($column, $autoIncrement = false)
    {
        return $this->addColumn('bigInteger', $column, compact('autoIncrement'));
    }

    /**
     * Create a new integer (4-byte) column on the table.
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function integer($column, $autoIncrement = false)
    {
        return $this->addColumn('integer', $column, compact('autoIncrement'));
    }

    /**
     * Create a new string column on the table.
     *
     * @param  string  $column
     * @param  int  $length
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function string($column, $length = 255)
    {
        return $this->addColumn('string', $column, compact('length'));
    }

    /**
     * Create a new text column on the table.
     *
     * @param  string  $column
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function text($column)
    {
        return $this->addColumn('text', $column);
    }

    /**
     * Create a new boolean column on the table.
     *
     * @param  string  $column
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function boolean($column)
    {
        return $this->addColumn('boolean', $column);
    }

    /**
     * Create a new decimal column on the table.
     *
     * @param  string  $column
     * @param  int  $precision
     * @param  int  $scale
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function decimal($column, $precision = 8, $scale = 2)
    {
        return $this->addColumn('decimal', $column, compact('precision', 'scale'));
    }

    /**
     * Create a new float column on the table.
     *
     * @param  string  $column
     * @param  int  $total
     * @param  int  $places
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function float($column, $total = 8, $places = 2)
    {
        return $this->addColumn('float', $column, compact('total', 'places'));
    }

    /**
     * Create a new double column on the table.
     *
     * @param  string  $column
     * @param  int|null  $total
     * @param  int|null  $places
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function double($column, $total = null, $places = null)
    {
        return $this->addColumn('double', $column, compact('total', 'places'));
    }

    /**
     * Create timestamp columns for the table.
     *
     * @param  int  $precision
     * @return void
     */
    public function timestamps($precision = 0)
    {
        $this->timestamp('created_at', $precision)->nullable();
        $this->timestamp('updated_at', $precision)->nullable();
    }

    /**
     * Create a new date column on the table.
     *
     * @param  string  $column
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function date($column)
    {
        return $this->addColumn('date', $column);
    }

    /**
     * Create a new datetime column on the table.
     *
     * @param  string  $column
     * @param  int  $precision
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function dateTime($column, $precision = 0)
    {
        return $this->addColumn('dateTime', $column, compact('precision'));
    }

    /**
     * Create a new time column on the table.
     *
     * @param  string  $column
     * @param  int  $precision
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function time($column, $precision = 0)
    {
        return $this->addColumn('time', $column, compact('precision'));
    }

    /**
     * Create a new timestamp column on the table.
     *
     * @param  string  $column
     * @param  int  $precision
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function timestamp($column, $precision = 0)
    {
        return $this->addColumn('timestamp', $column, compact('precision'));
    }

    /**
     * Create a new json column on the table.
     *
     * @param  string  $column
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function json($column)
    {
        return $this->addColumn('json', $column);
    }

    /**
     * Create a new jsonb column on the table.
     *
     * @param  string  $column
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function jsonb($column)
    {
        return $this->addColumn('jsonb', $column);
    }

    /**
     * Create a new uuid column on the table.
     *
     * @param  string  $column
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function uuid($column)
    {
        return $this->addColumn('uuid', $column);
    }

    /**
     * Create a new enum column on the table.
     *
     * @param  string  $column
     * @param  array   $allowed
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function enum($column, array $allowed)
    {
        return $this->addColumn('enum', $column, compact('allowed'));
    }

    /**
     * Create a new set column on the table.
     *
     * @param  string  $column
     * @param  array   $allowed
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function set($column, array $allowed)
    {
        return $this->addColumn('set', $column, compact('allowed'));
    }

    /**
     * Create a new binary column on the table.
     *
     * @param  string  $column
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function binary($column)
    {
        return $this->addColumn('binary', $column);
    }

    /**
     * Create a new longText column on the table.
     *
     * @param  string  $column
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function longText($column)
    {
        return $this->addColumn('longText', $column);
    }

    /**
     * Create a new mediumText column on the table.
     *
     * @param  string  $column
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function mediumText($column)
    {
        return $this->addColumn('mediumText', $column);
    }

    /**
     * Create a new year column on the table.
     *
     * @param  string  $column
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function year($column)
    {
        return $this->addColumn('year', $column);
    }

    /**
     * Create a new geometry column on the table.
     *
     * @param  string  $column
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function geometry($column)
    {
        return $this->addColumn('geometry', $column);
    }

    /**
     * Create a new point column on the table.
     *
     * @param  string  $column
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function point($column)
    {
        return $this->addColumn('point', $column);
    }

    /**
     * Add soft deletes to the table.
     *
     * @param  string  $column
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function softDeletes($column = 'deleted_at')
    {
        return $this->timestamp($column)->nullable();
    }

    /**
     * Create remember token column.
     *
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function rememberToken()
    {
        return $this->string('remember_token', 100)->nullable();
    }

    /**
     * Create a polymorphic relation columns.
     *
     * @param  string  $name
     * @param  string|null  $indexName
     * @return void
     */
    public function morphs($name, $indexName = null)
    {
        $this->string($name.'_type');
        $this->unsignedBigInteger($name.'_id');
        $this->index([$name.'_type', $name.'_id'], $indexName);
    }

    /**
     * Create a nullable polymorphic relation columns.
     *
     * @param  string  $name
     * @param  string|null  $indexName
     * @return void
     */
    public function nullableMorphs($name, $indexName = null)
    {
        $this->string($name.'_type')->nullable();
        $this->unsignedBigInteger($name.'_id')->nullable();
        $this->index([$name.'_type', $name.'_id'], $indexName);
    }

    /**
     * Add a new column to the blueprint.
     *
     * @param  string  $type
     * @param  string  $name
     * @param  array  $parameters
     * @return \Arpon\Database\Schema\Definitions\ColumnDefinition
     */
    public function addColumn($type, $name, array $parameters = [])
    {
        $this->columns[] = $column = new ColumnDefinition(
            array_merge(compact('type', 'name'), $parameters)
        );

        return $column;
    }

    /**
     * Add a new command to the blueprint.
     *
     * @param  string  $name
     * @param  array  $parameters
     * @return \Arpon\Database\Support\Fluent
     */
    protected function addCommand($name, array $parameters = [])
    {
        $this->commands[] = $command = $this->createCommand($name, $parameters);

        return $command;
    }

    /**
     * Create a new Fluent command.
     *
     * @param  string  $name
     * @param  array  $parameters
     * @return \Arpon\Database\Support\Fluent
     */
    protected function createCommand($name, array $parameters = [])
    {
        return new Fluent(array_merge(compact('name'), $parameters));
    }

    /**
     * Add a new index command to the blueprint.
     *
     * @param  string        $type
     * @param  string|array  $columns
     * @param  string        $index
     * @param  string|null   $algorithm
     * @return \Arpon\Database\Support\Fluent
     */
    protected function indexCommand($type, $columns, $index, $algorithm = null)
    {
        // If no name was specified for this index, we will create one using a basic
        // convention of the table name, followed by the columns, followed by an
        // index type, such as primary or index, which makes the index unique.
        if (is_null($index)) {
            $index = $this->createIndexName($type, $columns = (array) $columns);
        }

        return $this->addCommand($type, compact('index', 'columns', 'algorithm'));
    }

    /**
     * Create a default index name for the table.
     *
     * @param  string  $type
     * @param  array   $columns
     * @return string
     */
    protected function createIndexName($type, array $columns)
    {
        $index = strtolower($this->table.'_'.implode('_', $columns).'_'.$type);

        return str_replace(['-', '.'], '_', $index);
    }

    /**
     * Get the table the blueprint describes.
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Get the columns on the blueprint.
     *
     * @return array
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Get the commands on the blueprint.
     *
     * @return array
     */
    public function getCommands()
    {
        return $this->commands;
    }

    /**
     * Get the columns on the blueprint that should be added.
     *
     * @return array
     */
    public function getAddedColumns()
    {
        return array_filter($this->columns, function ($column) {
            return ! (bool) $column->change;
        });
    }

    /**
     * Get the columns on the blueprint that should be changed.
     *
     * @return array
     */
    public function getChangedColumns()
    {
        return array_filter($this->columns, function ($column) {
            return (bool) $column->change;
        });
    }

    /**
     * Ensure the commands on the blueprint are valid for the connection type.
     *
     * @return void
     */
    protected function ensureCommandsAreValid()
    {
        // This method can be overridden to add validation
    }
}