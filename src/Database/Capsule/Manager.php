<?php

namespace Arpon\Database\Capsule;

use Arpon\Database\Connection;
use Arpon\Database\Connectors\ConnectionFactory;
use Arpon\Database\DatabaseManager;
use Arpon\Database\Eloquent\Model as Eloquent;
use Arpon\Database\Query\Builder;
use Closure;
use PDO;

class Manager
{
    use CapsuleManagerTrait;

    /**
     * The database manager instance.
     *
     * @var DatabaseManager
     */
    protected DatabaseManager $manager;

    /**
     * Create a new database capsule manager.
     *
     * @param Container|null $container
     */
    public function __construct(Container $container = null)
    {
        $this->setupContainer($container ?: new Container);

        // Once we have the container setup, we will setup the default configuration
        // options in the container "config" binding. This will make the database
        // manager work correctly out of the box without extreme configuration.
        $this->setupDefaultConfiguration();

        $this->setupManager();
    }

    /**
     * Setup the default database configuration options.
     *
     * @return void
     */
    protected function setupDefaultConfiguration(): void
    {
        $config = $this->container['config'];
        $config['database.fetch'] = PDO::FETCH_OBJ;
        $config['database.default'] = 'default';
        $config['database.connections'] = [];
        $this->container['config'] = $config;
    }

    /**
     * Build the database manager instance.
     *
     * @return void
     */
    protected function setupManager(): void
    {
        $factory = new ConnectionFactory($this->container);

        $this->manager = new DatabaseManager($this->container, $factory);
    }

    /**
     * Get a connection instance from the global manager.
     *
     * @param string|null $connection
     * @return Connection
     */
    public static function connection(string $connection = null): Connection
    {
        return static::$instance->getConnection($connection);
    }

    /**
     * Get a fluent query builder instance.
     *
     * @param Closure|string|Builder $table
     * @param string|null $as
     * @param string|null $connection
     * @return Builder
     */
    public static function table(Closure|Builder|string $table, string $as = null, string $connection = null): Builder
    {
        return static::$instance->connection($connection)->table($table, $as);
    }

    /**
     * Get a schema builder instance.
     *
     * @param string|null $connection
     * @return \Arpon\Database\Schema\Builder
     */
    public static function schema(string $connection = null): \Arpon\Database\Schema\Builder
    {
        return static::$instance->connection($connection)->getSchemaBuilder();
    }

    /**
     * Get a registered connection instance.
     *
     * @param string|null $name
     * @return Connection
     */
    public function getConnection(string $name = null): Connection
    {
        return $this->manager->connection($name);
    }

    /**
     * Register a connection with the manager.
     *
     * @param  array  $config
     * @param  string  $name
     * @return void
     */
    public function addConnection(array $config, $name = 'default')
    {
        $containerConfig = $this->container['config'];
        
        if (!isset($containerConfig['database.connections'])) {
            $containerConfig['database.connections'] = [];
        }
        
        $containerConfig['database.connections'][$name] = $config;
        
        $this->container['config'] = $containerConfig;
    }

    /**
     * Bootstrap Eloquent so it is ready for usage.
     *
     * @return void
     */
    public function bootEloquent()
    {
        Eloquent::setConnectionResolver($this->manager);

        // If we have an event dispatcher instance, we will go ahead and register it
        // with the Eloquent ORM, allowing for model callbacks while creating and
        // updating "model" instances; however, it is not necessary to operate.
        if ($dispatcher = $this->getEventDispatcher()) {
            Eloquent::setEventDispatcher($dispatcher);
        }
    }

    /**
     * Set the fetch mode for the database connections.
     *
     * @param  int  $fetchMode
     * @return $this
     */
    public function setFetchMode($fetchMode)
    {
        $this->container['config']['database.fetch'] = $fetchMode;

        return $this;
    }

    /**
     * Get the database manager instance.
     *
     * @return DatabaseManager
     */
    public function getDatabaseManager()
    {
        return $this->manager;
    }

    /**
     * Get the current event dispatcher instance.
     *
     * @return \Arpon\Database\Events\Dispatcher|null
     */
    public function getEventDispatcher()
    {
        if ($this->container->bound('events')) {
            return $this->container['events'];
        }
    }

    /**
     * Set the event dispatcher instance to be used by connections.
     *
     * @param  \Arpon\Database\Events\Dispatcher  $dispatcher
     * @return void
     */
    public function setEventDispatcher($dispatcher)
    {
        $this->container->instance('events', $dispatcher);
    }

    /**
     * Dynamically pass methods to the default connection.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return static::connection()->$method(...$parameters);
    }
}