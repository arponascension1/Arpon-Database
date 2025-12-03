<?php

namespace Arpon\Database\Eloquent;

use Arpon\Database\Query\Builder as QueryBuilder;
use Arpon\Database\Eloquent\Scopes\Scope;
use Closure;
use BadMethodCallException;
use Error;

class EloquentBuilder
{
    /**
     * The base query builder instance.
     */
    protected QueryBuilder $query;

    /**
     * The model being queried.
     */
    protected Model $model;

    /**
     * The registered builder macros.
     */
    protected static array $macros = [];

    /**
     * A replacement for the typical delete function.
     */
    protected $onDelete;

    /**
     * Create a new Eloquent query builder instance.
     */
    public function __construct(QueryBuilder $query)
    {
        $this->query = $query;
    }

    /**
     * Set the model instance for the model being queried.
     */
    public function setModel(Model $model): static
    {
        $this->model = $model;

        $this->query->from($model->getTable());

        return $this;
    }

    /**
     * Get the model instance being queried.
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * The relationships that should be eager loaded.
     */
    protected array $eagerLoad = [];

    /**
     * Set the relationships that should be eager loaded.
     *
     * @param  mixed  ...$relations
     * @return $this
     */
    public function with(...$relations): static
    {
        $eagerLoad = $this->parseWithRelations(is_array($relations[0]) ? $relations[0] : $relations);

        $this->eagerLoad = array_merge($this->eagerLoad, $eagerLoad);

        return $this;
    }

    /**
     * Parse a list of relations into individuals.
     *
     * @param  array  $relations
     * @return array
     */
    protected function parseWithRelations(array $relations): array
    {
        $results = [];

        // Handle sequential arrays where relations may be passed as [ 'relation', Closure, ... ]
        // e.g. with(['posts', function($q) { ... }])
        $keys = array_keys($relations);
        $sequential = $keys === range(0, count($relations) - 1);

        if ($sequential) {
            $i = 0;
            $count = count($relations);

            while ($i < $count) {
                $item = $relations[$i];

                // If the item is a relation name
                if (is_string($item)) {
                    $name = $item;

                    // If the next item exists and is a Closure, treat it as the constraints
                    $next = $relations[$i + 1] ?? null;

                    if ($next instanceof \Closure) {
                        $constraints = $next;
                        $i += 2;
                    } else {
                        $constraints = function () {
                            //
                        };
                        $i += 1;
                    }

                    $results = $this->addNestedWiths($name, $results);
                    $results[$name] = $constraints;
                    continue;
                }

                // If the item is a key => value style (shouldn't happen in sequential)
                if ($item instanceof \Closure) {
                    // No associated relation name; skip.
                    $i += 1;
                    continue;
                }

                // Unknown entry, skip it
                $i += 1;
            }

            return $results;
        }

        foreach ($relations as $name => $constraints) {
            // If the "relation" value is actually a numeric key, we can assume that no
            // constraints have been specified for the eager load and we'll just put
            // an empty Closure with the loader so that we can treat all the same.
            if (is_numeric($name)) {
                if (! is_string($constraints)) {
                    continue;
                }

                $name = $constraints;
                $constraints = function () {
                    //
                };
            }

            // We need to separate out any nested includes, which allows the developers
            // to load deep relationships using "dots" without stating each level of
            // the relationship with its own key in the array of eager load names.
            $results = $this->addNestedWiths($name, $results);

            $results[$name] = $constraints;
        }

        return $results;
    }

    /**
     * Parse the nested relationships in a relation.
     *
     * @param  string  $name
     * @param  array  $results
     * @return array
     */
    protected function addNestedWiths(string $name, array $results): array
    {
        $progress = [];

        // If the relation has already been set on the result array, we will not set it
        // again, since that would override any constraints that were already placed
        // on the relationships. We will only set the ones that are not specified.
        foreach (explode('.', $name) as $segment) {
            $progress[] = $segment;

            if (! isset($results[$last = implode('.', $progress)])) {
                $results[$last] = function () {
                    //
                };
            }
        }

        return $results;
    }

    /**
     * Get the relationships being eagerly loaded.
     *
     * @return array
     */
    public function getEagerLoads(): array
    {
        return $this->eagerLoad;
    }

    /**
     * Set the relationships being eagerly loaded.
     *
     * @param  array  $eagerLoad
     * @return $this
     */
    public function setEagerLoads(array $eagerLoad): static
    {
        $this->eagerLoad = $eagerLoad;

        return $this;
    }

    /**
     * Eager load the relationships for the models.
     *
     * @param  array  $models
     * @return array
     */
    public function eagerLoadRelations(array $models): array
    {
        foreach ($this->eagerLoad as $name => $constraints) {
            // For nested eager loads we'll skip loading them here and they will be set as an
            // eager load on the query to retrieve the relation so that they will be eager
            // loaded on that query, because that is where they get hydrated as models.
            if (! str_contains($name, '.')) {
                $models = $this->eagerLoadRelation($models, $name, $constraints);
            }
        }

        return $models;
    }

    /**
     * Eagerly load the relationship on a set of models.
     *
     * @param  array  $models
     * @param  string  $name
     * @param  \Closure  $constraints
     * @return array
     */
    protected function eagerLoadRelation(array $models, string $name, \Closure $constraints): array
    {
        // First we will "back up" the existing where conditions on the query so we can
        // add our eager constraints. Then we will merge the wheres that were on the
        // query back to it in order that any where conditions might be specified.
        $relation = $this->getRelation($name);

        $relation->addEagerConstraints($models);

        $constraints($relation);

        // Once we have the results, we just match those back up to their parent models
        // using the relationship instance. Then we just return the finished arrays
        // of models which have been eagerly hydrated and are readied for return.
        return $relation->match(
            $relation->initRelation($models, $name),
            $relation->getEager(),
            $name
        );
    }

    /**
     * Get the relation instance for the given relation name.
     *
     * @param  string  $name
     * @return \Arpon\Database\Eloquent\Relations\Relation
     */
    public function getRelation(string $name)
    {
        return \Arpon\Database\Eloquent\Relations\Relation::noConstraints(function () use ($name) {
            try {
                return $this->getModel()->$name();
            } catch (\BadMethodCallException $e) {
                throw new \Exception("Call to undefined relationship [{$name}] on model [".get_class($this->getModel())."].");
            }
        });
    }

    /**
     * Find a model by its primary key.
     */
    public function find($id, array $columns = ['*']): ?Model
    {
        if (is_array($id) || $id instanceof Collection) {
            return $this->findMany($id, $columns);
        }

        return $this->whereKey($id)->first($columns);
    }

    /**
     * Find multiple models by their primary keys.
     */
    public function findMany($ids, array $columns = ['*']): Collection
    {
        $ids = $ids instanceof Collection ? $ids->modelKeys() : $ids;

        if (empty($ids)) {
            return $this->model->newCollection();
        }

        return $this->whereKey($ids)->get($columns);
    }

    /**
     * Find a model by its primary key or throw an exception.
     */
    public function findOrFail($id, array $columns = ['*']): Model
    {
        $result = $this->find($id, $columns);

        $id = $id instanceof Collection ? $id->modelKeys() : $id;

        if (is_array($id)) {
            if (count($result) !== count(array_unique($id))) {
                throw new ModelNotFoundException();
            }

            return $result;
        }

        if (is_null($result)) {
            throw new ModelNotFoundException();
        }

        return $result;
    }

    /**
     * Execute the query and get the first result.
     */
    public function first(array $columns = ['*']): ?Model
    {
        return $this->take(1)->get($columns)->first();
    }

    /**
     * Execute the query and get the first result or throw an exception.
     */
    public function firstOrFail(array $columns = ['*']): Model
    {
        if (!is_null($model = $this->first($columns))) {
            return $model;
        }

        throw new ModelNotFoundException();
    }

    /**
     * Execute the query as a "select" statement.
     */
    public function get(array $columns = ['*']): Collection
    {
        $builder = $this->applyScopes();

        // If we actually found models we will also eager load any relationships that
        // have been specified as needing to be eager loaded, which will solve the
        // n+1 query issue for the developers in most cases.
        $models = $builder->getModels($columns);

        // If we actually found models we will also eager load any relationships that
        // have been specified as needing to be eager loaded. This will prevent the
        // n+1 query problem by loading all relationships in separate queries.
        if (count($models) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }

        return $this->model->newCollection($models);
    }

    /**
     * Get all of the models from the database.
     */
    public function all(array $columns = ['*']): Collection
    {
        return $this->get($columns);
    }

    /**
     * Paginate the given query.
     */
    public function paginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null): array
    {
        $page = $page ?: 1;
        $total = $this->toBase()->getCountForPagination();

        $results = $total ? $this->forPage($page, $perPage)->get($columns) : $this->model->newCollection();

        return [
            'data' => $results,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int) ceil($total / $perPage),
            'from' => (($page - 1) * $perPage) + 1,
            'to' => min($total, $page * $perPage),
        ];
    }

    /**
     * Constrain the query to the next "page" of results after a given ID.
     */
    public function forPage(int $page, int $perPage = 15): static
    {
        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    /**
     * Get the hydrated models without eager loading.
     */
    public function getModels(array $columns = ['*']): array
    {
        $results = $this->query->get($columns);
        
        if ($results instanceof \Arpon\Database\Support\Collection) {
            $results = $results->all();
        }
        
        return $this->model->hydrate($results)->all();
    }

    /**
     * Add a where clause on the primary key to the query.
     */
    public function whereKey($id): static
    {
        if (is_array($id) || $id instanceof Collection) {
            $this->query->whereIn($this->model->getKeyName(), $id);

            return $this;
        }

        return $this->where($this->model->getKeyName(), '=', $id);
    }

    /**
     * Add a where clause to the query.
     */
    public function where($column, $operator = null, $value = null, string $boolean = 'and'): static
    {
        if ($column instanceof Closure && is_null($operator)) {
            $column($query = $this->model->newQueryWithoutRelationships());

            $this->query->addNestedWhereQuery($query->getQuery(), $boolean);
        } else {
            $this->query->where($column, $operator, $value, $boolean);
        }

        return $this;
    }

    /**
     * Add an "or where" clause to the query.
     */
    public function orWhere($column, $operator = null, $value = null): static
    {
        [$value, $operator] = $this->query->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Add a "where in" clause to the query.
     */
    public function whereIn(string $column, $values, string $boolean = 'and', bool $not = false): static
    {
        $this->query->whereIn($column, $values, $boolean, $not);

        return $this;
    }

    /**
     * Add a "where not in" clause to the query.
     */
    public function whereNotIn(string $column, $values, string $boolean = 'and'): static
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * Set the "limit" value of the query.
     */
    public function take(int $value): static
    {
        return $this->limit($value);
    }

    /**
     * Alias to set the "limit" value of the query.
     */
    public function limit(int $value): static
    {
        $this->query->limit($value);

        return $this;
    }

    /**
     * Set the "offset" value of the query.
     */
    public function offset(int $value): static
    {
        $this->query->offset($value);

        return $this;
    }

    /**
     * Set the "offset" value of the query.
     */
    public function skip(int $value): static
    {
        return $this->offset($value);
    }

    /**
     * Add an "order by" clause to the query.
     */
    public function orderBy($column, string $direction = 'asc'): static
    {
        $this->query->orderBy($column, $direction);

        return $this;
    }

    /**
     * Save a new model and return the instance.
     */
    public function create(array $attributes = []): Model
    {
        return tap($this->newModelInstance($attributes), function ($instance) {
            $instance->save();
        });
    }

    /**
     * Create a new instance of the model being queried.
     */
    public function newModelInstance(array $attributes = []): Model
    {
        return $this->model->newInstance($attributes);
    }

    /**
     * Update records in the database.
     */
    public function update(array $values): int
    {
        return $this->toBase()->update($this->addUpdatedAtColumn($values));
    }

    /**
     * Insert new records into the database.
     */
    public function insert(array $values): bool
    {
        // Since every insert gets treated like a batch insert, we will make sure the
        // bindings are structured in a way that is convenient when building the
        // SQL statements for the insert operation.
        if (empty($values)) {
            return true;
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        }

        return $this->toBase()->insert($values);
    }

    /**
     * Insert new records into the database while ignoring errors.
     */
    public function insertGetId(array $values, ?string $sequence = null)
    {
        return $this->toBase()->insertGetId($values, $sequence);
    }

    /**
     * Delete records from the database.
     */
    public function delete(): mixed
    {
        if (isset($this->onDelete)) {
            return call_user_func($this->onDelete, $this);
        }

        return $this->toBase()->delete();
    }

    /**
     * Add the "updated at" column to an array of values.
     */
    protected function addUpdatedAtColumn(array $values): array
    {
        if (!$this->model->usesTimestamps() ||
            is_null($this->model->getUpdatedAtColumn())) {
            return $values;
        }

        $column = $this->model->getUpdatedAtColumn();

        $values = array_merge(
            [$column => $this->model->freshTimestampString()],
            $values
        );

        return $values;
    }

    /**
     * Get the underlying query builder instance.
     */
    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    /**
     * Set the underlying query builder instance.
     */
    public function setQuery(QueryBuilder $query): static
    {
        $this->query = $query;

        return $this;
    }

    /**
     * Get a base query builder instance.
     */
    public function toBase(): QueryBuilder
    {
        return $this->applyScopes()->getQuery();
    }

    /**
     * Apply the scopes to the Eloquent builder instance and return it.
     */
    public function applyScopes(): static
    {
        if (!$this->scopes) {
            return $this;
        }

        $builder = clone $this;

        foreach ($this->scopes as $identifier => $scope) {
            if (!isset($builder->scopes[$identifier]) || in_array($identifier, $this->removedScopes)) {
                continue;
            }

            if ($scope instanceof Scope) {
                $scope->apply($builder, $this->getModel());
            } elseif ($scope instanceof Closure) {
                $builder->callScope($scope);
            }
        }

        return $builder;
    }

    /**
     * The global scopes applied to the builder.
     */
    protected array $scopes = [];

    /**
     * The scopes that should be removed from the query.
     */
    protected array $removedScopes = [];

    /**
     * Add a global scope to the builder.
     */
    public function withGlobalScope($identifier, $scope): static
    {
        $this->scopes[$identifier] = $scope;
        
        if (method_exists($scope, 'extend')) {
            $scope->extend($this);
        }
        
        return $this;
    }

    /**
     * Remove a global scope from the builder.
     */
    public function withoutGlobalScope($scope): static
    {
        if (!is_array($scope)) {
            $scope = [$scope];
        }

        foreach ($scope as $identifier) {
            unset($this->scopes[$identifier]);
            $this->removedScopes[] = $identifier;
        }

        return $this;
    }

    /**
     * Remove all global scopes from the builder.
     */
    public function withoutGlobalScopes(?array $scopes = null): static
    {
        if (is_null($scopes)) {
            $this->scopes = [];
            $this->removedScopes = array_keys($this->scopes);
        } else {
            return $this->withoutGlobalScope($scopes);
        }

        return $this;
    }

    /**
     * Get the global scopes for this builder instance.
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /**
     * Apply the given scope on the current builder instance.
     */
    protected function callScope(callable $scope, array $parameters = []): mixed
    {
        array_unshift($parameters, $this);

        return $scope(...array_values($parameters)) ?? $this;
    }

    /**
     * Register a custom macro.
     */
    public function macro(string $name, callable $macro): void
    {
        static::$macros[$name] = $macro;
    }

    /**
     * Register a replacement for the default delete function.
     */
    public function onDelete(callable $callback): void
    {
        $this->onDelete = $callback;
    }

    /**
     * Dynamically handle calls into the query instance.
     */
    public function __call(string $method, array $parameters)
    {
        // Check for registered macros first
        if (isset(static::$macros[$method])) {
            $macro = static::$macros[$method];
            if ($macro instanceof Closure) {
                return $macro($this, ...$parameters);
            }
            return $macro(...$parameters);
        }

        // Check for model scopes
        if (method_exists($this->model, $scope = 'scope' . ucfirst($method))) {
            return $this->callScope([$this->model, $scope], $parameters);
        }

        // Forward to query builder
        $this->forwardCallTo($this->query, $method, $parameters);

        return $this;
    }

    /**
     * Forward a method call to the given object.
     */
    protected function forwardCallTo($object, string $method, array $parameters)
    {
        try {
            return $object->{$method}(...$parameters);
        } catch (Error|BadMethodCallException $e) {
            $pattern = '~^Call to undefined method (?P<class>[^:]+)::(?P<method>[^\(]+)\(\)$~';

            if (!preg_match($pattern, $e->getMessage(), $matches)) {
                throw $e;
            }

            if ($matches['class'] != get_class($object) ||
                $matches['method'] != $method) {
                throw $e;
            }

            throw new BadMethodCallException(sprintf(
                'Call to undefined method %s::%s()',
                static::class, $method
            ));
        }
    }

    /**
     * Dynamically handle calls into the query instance.
     */
    public function __clone()
    {
        $this->query = clone $this->query;
    }

    /**
     * Increment a column's value by a given amount.
     */
    public function increment(string $column, $amount = 1, array $extra = []): int
    {
        return $this->query->increment($column, $amount, $extra);
    }

    /**
     * Decrement a column's value by a given amount.
     */
    public function decrement(string $column, $amount = 1, array $extra = []): int
    {
        return $this->query->decrement($column, $amount, $extra);
    }

    /**
     * Order by the latest records (usually by created_at desc).
     */
    public function latest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Order by the oldest records (usually by created_at asc).
     */
    public function oldest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'asc');
    }

    /**
     * Retrieve the "count" result of the query.
     */
    public function count(string $columns = '*'): int
    {
        return $this->toBase()->count($columns);
    }

    /**
     * Retrieve the minimum value of a given column.
     */
    public function min(string $column)
    {
        return $this->toBase()->min($column);
    }

    /**
     * Retrieve the maximum value of a given column.
     */
    public function max(string $column)
    {
        return $this->toBase()->max($column);
    }

    /**
     * Retrieve the sum of the values of a given column.
     */
    public function sum(string $column)
    {
        return $this->toBase()->sum($column);
    }

    /**
     * Retrieve the average of the values of a given column.
     */
    public function avg(string $column)
    {
        return $this->toBase()->avg($column);
    }

    /**
     * Alias for the "avg" method.
     */
    public function average(string $column)
    {
        return $this->avg($column);
    }

    /**
     * Determine if any rows exist for the current query.
     */
    public function exists(): bool
    {
        return $this->toBase()->exists();
    }

    /**
     * Determine if no rows exist for the current query.
     */
    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    /**
     * Chunk the results of the query.
     */
    public function chunk(int $count, callable $callback): bool
    {
        $page = 1;

        do {
            // We'll execute the query for the given page and get the results. If there are
            // no results we can break from the loop. When there are results
            // we will call the callback with the current chunk of these results here.
            $results = $this->forPage($page, $count)->get();

            $countResults = $results->count();

            if ($countResults == 0) {
                break;
            }

            // On each chunk result set, we will pass them to the callback and then let the
            // developer take care of everything within the callback, which allows us to
            // keep the memory low for spinning through large result sets for working.
            if ($callback($results, $page) === false) {
                return false;
            }

            unset($results);

            $page++;
        } while ($countResults == $count);

        return true;
    }

    /**
     * Get a single column's value from the first result of a query.
     */
    public function value(string $column)
    {
        if ($result = $this->first([$column])) {
            return $result->{$column};
        }
    }

    /**
     * Execute a query for a single record by ID.
     */
    public function findOrNew($id, array $columns = ['*']): Model
    {
        if (!is_null($model = $this->find($id, $columns))) {
            return $model;
        }

        return $this->newModelInstance();
    }

    /**
     * Get the first record matching the attributes or instantiate it.
     */
    public function firstOrNew(array $attributes = [], array $values = []): Model
    {
        if (!is_null($instance = $this->where($attributes)->first())) {
            return $instance;
        }

        return $this->newModelInstance(array_merge($attributes, $values));
    }

    /**
     * Get the first record matching the attributes or create it.
     */
    public function firstOrCreate(array $attributes = [], array $values = []): Model
    {
        if (!empty($attributes)) {
            if (!is_null($instance = $this->where($attributes)->first())) {
                return $instance;
            }
        } else {
            // If no attributes specified, just check if any record exists
            if (!is_null($instance = $this->first())) {
                return $instance;
            }
        }

        return tap($this->newModelInstance(array_merge($attributes, $values)), function ($instance) {
            $instance->save();
        });
    }

    /**
     * Create or update a record matching the attributes, and fill it with values.
     */
    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        return tap($this->firstOrNew($attributes), function ($instance) use ($values) {
            $instance->fill($values)->save();
        });
    }

    /**
     * Execute the query and get all results.
     */
    public function pluck(string $column, ?string $key = null)
    {
        // First we will need to select the columns to be sure we have them.
        $queryResult = $this->toBase()->pluck($column, $key);

        // If the model has a mutator for the requested column, we need to spin through
        // the results and mutate the values so that the mutated version is returned
        // as these values will not be retrieved in the mutated form from the table.
        if (!$this->model->hasGetMutator($column) &&
            !$this->model->hasCast($column) &&
            !in_array($column, $this->model->getDates())) {
            return $queryResult;
        }

        return $queryResult->map(function ($value) use ($column) {
            return $this->model->newFromBuilder([$column => $value])->{$column};
        });
    }
}

// Utility function
if (!function_exists('tap')) {
    function tap($value, $callback = null)
    {
        if (is_null($callback)) {
            return new class($value) {
                public $target;

                public function __construct($target)
                {
                    $this->target = $target;
                }

                public function __call($method, $parameters)
                {
                    $this->target->{$method}(...$parameters);

                    return $this->target;
                }
            };
        }

        $callback($value);

        return $value;
    }
}