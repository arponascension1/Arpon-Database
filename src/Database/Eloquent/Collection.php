<?php

namespace Arpon\Database\Eloquent;

use Arpon\Database\Support\Collection as BaseCollection;
use ArrayAccess;
use LogicException;

class Collection extends BaseCollection
{
    /**
     * Find a model in the collection by key.
     */
    public function find($key, $default = null): ?Model
    {
        if ($key instanceof Model) {
            $key = $key->getKey();
        }

        if ($key instanceof Collection) {
            $key = $key->modelKeys();
        }

        if (is_array($key)) {
            if ($this->isEmpty()) {
                return new static;
            }

            return $this->whereIn($this->first()->getKeyName(), $key);
        }

        return $this->first(function ($model) use ($key) {
            return $model->getKey() == $key;
        }, $default);
    }

    /**
     * Load a set of relationships onto the collection.
     */
    public function load($relations): static
    {
        if ($this->isNotEmpty()) {
            if (is_string($relations)) {
                $relations = func_get_args();
            }

            $query = $this->first()->newQueryWithoutRelationships()->with($relations);

            $this->items = $query->eagerLoadRelations($this->items);
        }

        return $this;
    }

    /**
     * Add an item to the collection.
     */
    public function add($item): static
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * Determine if a key exists in the collection.
     */
    public function contains($key, $operator = null, $value = null): bool
    {
        if (func_num_args() === 1) {
            if ($this->useAsCallable($key)) {
                $placeholder = new \stdClass;

                return $this->first($key, $placeholder) !== $placeholder;
            }

            return in_array($key, $this->items);
        }

        return $this->contains($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Get the array of primary keys.
     */
    public function modelKeys(): array
    {
        return array_map(function ($model) {
            return $model->getKey();
        }, $this->items);
    }

    /**
     * Merge the collection with the given items.
     */
    public function merge($items): static
    {
        $dictionary = $this->getDictionary();

        foreach ($items as $item) {
            $dictionary[$item->getKey()] = $item;
        }

        return new static(array_values($dictionary));
    }

    /**
     * Run a map over each of the items.
     */
    public function map(callable $callback): BaseCollection
    {
        $result = parent::map($callback);

        return $result->contains(function ($item) {
            return ! $item instanceof Model;
        }) ? $result->toBase() : $result;
    }

    /**
     * Run an associative map over each of the items.
     */
    public function mapWithKeys(callable $callback): BaseCollection
    {
        $result = parent::mapWithKeys($callback);

        return $result->contains(function ($item) {
            return ! $item instanceof Model;
        }) ? $result->toBase() : $result;
    }

    /**
     * Reload a fresh model instance from the database for all the entities.
     */
    public function fresh(?string $with = null): static
    {
        if ($this->isEmpty()) {
            return new static;
        }

        $model = $this->first();

        $freshModels = $model->newQueryWithoutScopes()
            ->with(is_string($with) ? func_get_args() : $with)
            ->whereIn($model->getKeyName(), $this->modelKeys())
            ->get()
            ->getDictionary();

        return $this->filter(function ($model) use ($freshModels) {
            return $model->exists && isset($freshModels[$model->getKey()]);
        })->map(function ($model) use ($freshModels) {
            return $freshModels[$model->getKey()];
        });
    }

    /**
     * Diff the collection with the given items.
     */
    public function diff($items): static
    {
        $diff = new static;

        $dictionary = $this->getDictionary($items);

        foreach ($this->items as $item) {
            if (!isset($dictionary[$item->getKey()])) {
                $diff->add($item);
            }
        }

        return $diff;
    }

    /**
     * Intersect the collection with the given items.
     */
    public function intersect($items): static
    {
        $intersect = new static;

        if (empty($items)) {
            return $intersect;
        }

        $dictionary = $this->getDictionary($items);

        foreach ($this->items as $item) {
            if (isset($dictionary[$item->getKey()])) {
                $intersect->add($item);
            }
        }

        return $intersect;
    }

    /**
     * Return only unique items from the collection.
     */
    public function unique($key = null, $strict = false): static
    {
        if (!is_null($key)) {
            return parent::unique($key, $strict);
        }

        return new static(array_values($this->getDictionary()));
    }

    /**
     * Returns only the models from the collection with the specified keys.
     */
    public function only($keys): static
    {
        if (is_null($keys)) {
            return new static($this->items);
        }

        $dictionary = array_flip($keys);

        return $this->filter(function ($model) use ($dictionary) {
            return isset($dictionary[$model->getKey()]);
        });
    }

    /**
     * Returns all models in the collection except the models with specified keys.
     */
    public function except($keys): static
    {
        $dictionary = array_flip($keys);

        return $this->filter(function ($model) use ($dictionary) {
            return !isset($dictionary[$model->getKey()]);
        });
    }

    /**
     * Make the given, typically visible, attributes hidden across the entire collection.
     */
    public function makeHidden($attributes): static
    {
        return $this->each(function ($model) use ($attributes) {
            $model->makeHidden($attributes);
        });
    }

    /**
     * Make the given, typically hidden, attributes visible across the entire collection.
     */
    public function makeVisible($attributes): static
    {
        return $this->each(function ($model) use ($attributes) {
            $model->makeVisible($attributes);
        });
    }

    /**
     * Append an attribute across the entire collection.
     */
    public function append($attributes): static
    {
        return $this->each(function ($model) use ($attributes) {
            $model->append($attributes);
        });
    }

    /**
     * Get a dictionary keyed by the collection models' keys.
     */
    public function getDictionary($items = null): array
    {
        $items = is_null($items) ? $this->items : $items;

        $dictionary = [];

        foreach ($items as $value) {
            $dictionary[$value->getKey()] = $value;
        }

        return $dictionary;
    }

    /**
     * The following methods are intercepted to always return base collections.
     */

    /**
     * Get a base Support collection instance from this collection.
     */
    public function toBase(): BaseCollection
    {
        return new BaseCollection($this->items);
    }

    /**
     * Transform each item in the collection using a callback.
     */
    public function transform(callable $callback): static
    {
        $this->items = $this->map($callback)->all();

        return $this;
    }

    /**
     * Get an array with the values of a given key.
     */
    public function pluck($value, $key = null): BaseCollection
    {
        return $this->toBase()->pluck($value, $key);
    }

        /**
     * Get the keys of the collection items.
     */
    public function keys(): BaseCollection
    {
        return new BaseCollection(array_keys($this->items));
    }

    /**
     * Push an item onto the end of the collection.
     *
     * @param  mixed  $value
     * @return $this
     */
    public function push($value)
    {
        $this->offsetSet(null, $value);

        return $this;
    }

    /**
     * Sort the collection by a given key.
     *
     * @param  callable|string  $callback
     * @param  int  $options
     * @param  bool  $descending
     * @return static
     */
    public function sortBy($callback, $options = SORT_REGULAR, $descending = false)
    {
        $results = [];

        $callback = $this->valueRetriever($callback);

        // First we will loop through the items and get the comparator from a callback
        // function which we were given. Then, we will sort the returned values and
        // and grab the corresponding values for the sorted keys from this array.
        foreach ($this->items as $key => $value) {
            $results[$key] = $callback($value, $key);
        }

        $descending ? arsort($results, $options) : asort($results, $options);

        // Once we have sorted all of the keys in the array, we will loop through them
        // and grab the corresponding model so we can set the underlying items list
        // to the sorted version. Then we'll just return the collection instance.
        foreach (array_keys($results) as $key) {
            $results[$key] = $this->items[$key];
        }

        return new static($results);
    }

    /**
     * Sort the collection in descending order by a given key.
     *
     * @param  callable|string  $callback
     * @param  int  $options
     * @return static
     */
    public function sortByDesc($callback, $options = SORT_REGULAR)
    {
        return $this->sortBy($callback, $options, true);
    }

    /**
     * Group the collection's items by a given key.
     *
     * @param  callable|string  $groupBy
     * @param  bool  $preserveKeys
     * @return \Arpon\Database\Support\Collection
     */
    public function groupBy($groupBy, $preserveKeys = false)
    {
        if (! $this->useAsCallable($groupBy) && is_array($groupBy)) {
            return $this->groupByMultiple($groupBy, $preserveKeys);
        }

        $groupBy = $this->valueRetriever($groupBy);

        $results = [];

        foreach ($this->items as $key => $value) {
            $groupKeys = $groupBy($value, $key);

            if (! is_array($groupKeys)) {
                $groupKeys = [$groupKeys];
            }

            foreach ($groupKeys as $groupKey) {
                $groupKey = is_bool($groupKey) ? (int) $groupKey : $groupKey;

                if (! array_key_exists($groupKey, $results)) {
                    $results[$groupKey] = new static;
                }

                $results[$groupKey]->offsetSet($preserveKeys ? $key : null, $value);
            }
        }

        return new BaseCollection($results);
    }

    /**
     * Group the collection's items by multiple keys.
     *
     * @param  array  $groupBy
     * @param  bool  $preserveKeys
     * @return \Arpon\Database\Support\Collection
     */
    protected function groupByMultiple($groupBy, $preserveKeys = false)
    {
        if (empty($groupBy)) {
            return new BaseCollection([$this]);
        }

        $groupBy = array_values($groupBy);

        $key = array_shift($groupBy);

        return $this->groupBy($key, $preserveKeys)->map(function ($grouped) use ($groupBy, $preserveKeys) {
            return $grouped->groupByMultiple($groupBy, $preserveKeys);
        });
    }

    /**
     * Get a value retrieving callback.
     *
     * @param  callable|string|null  $value
     * @return callable
     */
    protected function valueRetriever($value)
    {
        if ($this->useAsCallable($value)) {
            return $value;
        }

        return function ($item) use ($value) {
            return data_get($item, $value);
        };
    }

    /**
     * Zip the collection together with one or more arrays.
     */
    public function zip($items): BaseCollection
    {
        return $this->toBase()->zip(...func_get_args());
    }

    /**
     * Collapse the collection of items into a single array.
     */
    public function collapse(): BaseCollection
    {
        return $this->toBase()->collapse();
    }

    /**
     * Get a flattened array of the items in the collection.
     */
    public function flatten(int $depth = INF): BaseCollection
    {
        return $this->toBase()->flatten($depth);
    }

    /**
     * Flip the items in the collection.
     */
    public function flip(): BaseCollection
    {
        return $this->toBase()->flip();
    }

    /**
     * Pad collection to the specified length with a value.
     */
    public function pad(int $size, $value): BaseCollection
    {
        return $this->toBase()->pad($size, $value);
    }

    /**
     * Get the comparison function to detect duplicates.
     */
    protected function duplicateComparator(bool $strict): \Closure
    {
        return function ($a, $b) {
            return $a->is($b);
        };
    }

    /**
     * Get the type of the entities being paginated.
     */
    public function getQueueableClass(): ?string
    {
        if ($this->isEmpty()) {
            return null;
        }

        $class = get_class($this->first());

        $this->each(function ($model) use ($class) {
            if (get_class($model) !== $class) {
                throw new LogicException('Queueing collections with multiple model types is not supported.');
            }
        });

        return $class;
    }

    /**
     * Get the identifiers for all of the entities.
     */
    public function getQueueableIds(): array
    {
        if ($this->isEmpty()) {
            return [];
        }

        return $this->where($this->first()->getKeyName(), '!=', null)->modelKeys();
    }

    /**
     * Get the relationships for all of the entities.
     */
    public function getQueueableRelations(): array
    {
        if ($this->isEmpty()) {
            return [];
        }

        $relations = array_filter([$this->first()->getQueueableRelations()]);

        $this->each(function ($model) use (&$relations) {
            $relations[] = $model->getQueueableRelations();
        });

        return array_unique(array_merge(...$relations));
    }

    /**
     * Get the connection of the entity.
     */
    public function getQueueableConnection(): ?string
    {
        if ($this->isEmpty()) {
            return null;
        }

        $connection = $this->first()->getConnectionName();

        $this->each(function ($model) use ($connection) {
            if ($model->getConnectionName() !== $connection) {
                throw new LogicException('Queueing collections with multiple model connections is not supported.');
            }
        });

        return $connection;
    }

    /**
     * Key an associative array by a field or using a callback.
     */
    public function keyBy($keyBy): static
    {
        return $this->keyByInternal($keyBy, true);
    }

    /**
     * Key the collection by the given key.
     */
    protected function keyByInternal($keyBy, bool $preserveKeys): BaseCollection
    {
        $results = [];

        $valueRetriever = $this->valueRetriever($keyBy);

        foreach ($this->items as $key => $item) {
            $resolvedKey = $valueRetriever($item);

            if (is_object($resolvedKey)) {
                $resolvedKey = (string) $resolvedKey;
            }

            if ($preserveKeys) {
                $results[$resolvedKey] = $item;
            } else {
                $results[$resolvedKey][] = $item;
            }
        }

        return new static($results);
    }

    /**
     * Get the collection as an array.
     */
    public function toArray(): array
    {
        return array_map(function ($value) {
            return $value instanceof Model ? $value->toArray() : $value;
        }, $this->items);
    }

    /**
     * Get the collection as JSON.
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
}