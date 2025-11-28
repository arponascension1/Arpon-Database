<?php

namespace Arpon\Database\Eloquent;

use Arpon\Database\DatabaseManager;
use Arpon\Database\ConnectionInterface;
use Arpon\Database\ConnectionResolverInterface;
use Arpon\Database\Query\Builder as QueryBuilder;
use Arpon\Database\Support\Collection as BaseCollection;
use Arpon\Database\Eloquent\Relations\HasOne;
use Arpon\Database\Eloquent\Relations\HasMany;
use Arpon\Database\Eloquent\Relations\BelongsTo;
use Arpon\Database\Eloquent\Relations\HasOneThrough;
use Arpon\Database\Eloquent\Relations\HasManyThrough;
use Arpon\Database\Eloquent\Relations\MorphOne;
use Arpon\Database\Eloquent\Relations\MorphMany;
use Arpon\Database\Eloquent\Relations\MorphTo;
use Arpon\Database\Eloquent\Relations\MorphToMany;
use Arpon\Database\Eloquent\Relations\BelongsToMany;
use Arpon\Database\Eloquent\EloquentBuilder;
use Arpon\Database\Eloquent\Scopes\Scope;
use ArrayAccess;
use JsonSerializable;
use Exception;
use Closure;

abstract class Model implements ArrayAccess, JsonSerializable
{
    /**
     * The connection name for the model.
     */
    protected ?string $connection = null;

    /**
     * The table associated with the model.
     */
    protected ?string $table = null;

    /**
     * The primary key for the model.
     */
    protected string $primaryKey = 'id';

    /**
     * The "type" of the auto-incrementing ID.
     */
    protected string $keyType = 'int';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public bool $incrementing = true;

    /**
     * Indicates if the model should be timestamped.
     */
    public bool $timestamps = true;

    /**
     * The name of the "created at" column.
     */
    const CREATED_AT = 'created_at';

    /**
     * The name of the "updated at" column.
     */
    const UPDATED_AT = 'updated_at';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [];

    /**
     * The attributes that aren't mass assignable.
     */
    protected array $guarded = ['*'];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected array $hidden = [];

    /**
     * The attributes that should be visible in serialization.
     */
    protected array $visible = [];

    /**
     * The attributes that should be cast.
     */
    protected array $casts = [];

    /**
     * The built-in, primitive cast types supported.
     */
    protected static array $primitiveCastTypes = [
        'array', 'bool', 'boolean', 'collection', 'custom_datetime', 'date', 'datetime', 
        'decimal', 'double', 'float', 'int', 'integer', 'json', 'object', 'real', 'string', 'timestamp',
    ];

    /**
     * The storage format of the model's date columns.
     */
    protected string $dateFormat = 'Y-m-d H:i:s';

    /**
     * The accessors to append to the model's array form.
     */
    protected array $appends = [];

    /**
     * The model's attributes.
     */
    protected array $attributes = [];

    /**
     * The model attribute's original state.
     */
    protected array $original = [];

    /**
     * The model's dirty attributes.
     */
    protected array $changes = [];

    /**
     * Indicates if the model exists.
     */
    public bool $exists = false;

    /**
     * Indicates if the model was inserted during the current request lifecycle.
     */
    public bool $wasRecentlyCreated = false;

    /**
     * The database manager instance.
     */
    protected static ?DatabaseManager $resolver = null;

    /**
     * The morph map for polymorphic relations.
     */
    protected static array $morphMap = [];

    /**
     * The global scopes for the model.
     */
    protected static array $globalScopes = [];

    /**
     * Indicates if the model should be booted.
     */
    protected static array $booted = [];

    /**
     * The array of trait initializers that will be called on each new instance.
     */
    protected static array $traitInitializers = [];

    /**
     * Create a new Eloquent model instance.
     */
    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();
        $this->initializeTraits();
        $this->syncOriginal();
        $this->fill($attributes);
    }

    /**
     * Check if the model needs to be booted and if so, do it.
     */
    protected function bootIfNotBooted(): void
    {
        if (!isset(static::$booted[static::class])) {
            static::$booted[static::class] = true;
            static::boot();
        }
    }

    /**
     * The "boot" method of the model.
     */
    protected static function boot(): void
    {
        static::bootTraits();
    }

    /**
     * Boot all of the bootable traits on the model.
     */
    protected static function bootTraits(): void
    {
        $class = static::class;

        $booted = [];

        static::$traitInitializers[$class] = [];

        foreach (class_uses_recursive($class) as $trait) {
            $method = 'boot'.class_basename($trait);

            if (method_exists($class, $method) && ! in_array($method, $booted)) {
                forward_static_call([$class, $method]);

                $booted[] = $method;
            }

            if (method_exists($class, $method = 'initialize'.class_basename($trait))) {
                static::$traitInitializers[$class][] = $method;

                static::$traitInitializers[$class] = array_unique(
                    static::$traitInitializers[$class]
                );
            }
        }
    }

    /**
     * Initialize any initializable traits on the model.
     */
    protected function initializeTraits(): void
    {
        foreach (static::$traitInitializers[static::class] as $method) {
            $this->{$method}();
        }
    }

    /**
     * Register a new global scope on the model.
     */
    public static function addGlobalScope($scope, $implementation = null): void
    {
        if (is_string($scope) && $implementation !== null) {
            static::$globalScopes[static::class][$scope] = $implementation;
        } elseif ($scope instanceof Scope) {
            static::$globalScopes[static::class][get_class($scope)] = $scope;
        } elseif ($scope instanceof Closure) {
            static::$globalScopes[static::class][spl_object_id($scope)] = $scope;
        }
    }

    /**
     * Determine if a model has a global scope.
     */
    public static function hasGlobalScope($scope): bool
    {
        return !is_null(static::getGlobalScope($scope));
    }

    /**
     * Get a global scope registered with the model.
     */
    public static function getGlobalScope($scope)
    {
        if (is_string($scope)) {
            return static::$globalScopes[static::class][$scope] ?? null;
        }

        return static::$globalScopes[static::class][get_class($scope)] ?? null;
    }

    /**
     * Get the global scopes for this model instance.
     */
    public function getGlobalScopes(): array
    {
        return static::$globalScopes[static::class] ?? [];
    }

    /**
     * Remove a global scope from the model.
     */
    public static function removeGlobalScope($scope): void
    {
        if (is_string($scope)) {
            unset(static::$globalScopes[static::class][$scope]);
        } elseif ($scope instanceof Scope) {
            unset(static::$globalScopes[static::class][get_class($scope)]);
        }
    }

    /**
     * Remove all global scopes from the model.
     */
    public static function clearGlobalScopes(): void
    {
        static::$globalScopes[static::class] = [];
    }

    /**
     * Get all the global scopes for all models.
     */
    public static function getAllGlobalScopes(): array
    {
        return static::$globalScopes;
    }

    /**
     * Fill the model with an array of attributes.
     */
    public function fill(array $attributes): static
    {
        $totallyGuarded = $this->totallyGuarded();

        foreach ($this->fillableFromArray($attributes) as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            } elseif ($totallyGuarded) {
                throw new Exception("Mass assignment is not allowed for attribute: {$key}");
            }
        }

        return $this;
    }

    /**
     * Get the fillable attributes of a given array.
     */
    protected function fillableFromArray(array $attributes): array
    {
        if (count($this->getFillable()) > 0 && !static::$unguarded) {
            return array_intersect_key($attributes, array_flip($this->getFillable()));
        }

        return $attributes;
    }

    /**
     * Get the fillable attributes for the model.
     */
    public function getFillable(): array
    {
        return $this->fillable;
    }

    /**
     * Determine if the given attribute may be mass assigned.
     */
    public function isFillable(string $key): bool
    {
        if (static::$unguarded) {
            return true;
        }

        // If the key is in the fillable array, it's fillable
        if (in_array($key, $this->getFillable())) {
            return true;
        }

        // If fillable is empty and the key is not guarded, it's fillable
        if (empty($this->getFillable()) && !$this->isGuarded($key)) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the given key is guarded.
     */
    public function isGuarded(string $key): bool
    {
        return in_array($key, $this->getGuarded()) || $this->getGuarded() === ['*'];
    }

    /**
     * Get the guarded attributes for the model.
     */
    public function getGuarded(): array
    {
        return $this->guarded;
    }

    /**
     * Determine if the model is totally guarded.
     */
    public function totallyGuarded(): bool
    {
        return count($this->getFillable()) === 0 && $this->getGuarded() === ['*'];
    }

    /**
     * Disable all mass assignable restrictions.
     */
    protected static bool $unguarded = false;

    /**
     * Set an attribute on the model.
     */
    public function setAttribute(string $key, $value): static
    {
        // First we will check for the presence of a mutator for the set operation
        // which simply lets the developers tweak the attribute as it is set on
        // the model, such as "json_encoding" a listing of data for storage.
        if ($this->hasSetMutator($key)) {
            return $this->setMutatedAttributeValue($key, $value);
        }

        // If an attribute is listed as a "date", we'll convert it from a DateTime
        // instance into a form proper for storage on the database tables using
        // the connection grammar's date format. We will auto set the values.
        elseif ($value && $this->isDateAttribute($key)) {
            $value = $this->fromDateTime($value);
        }

        if ($this->isJsonCastable($key) && !is_null($value)) {
            $value = $this->castAttributeAsJson($key, $value);
        }

        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Get an attribute from the model.
     */
    public function getAttribute(string $key)
    {
        if (!$key) {
            return null;
        }

        // If the attribute exists in the attribute array or has a "get" mutator we will
        // get the attribute's value. Otherwise, we will proceed as if the developers
        // are asking for a relationship's value. This covers both types of values.
        if (array_key_exists($key, $this->attributes) ||
            $this->hasGetMutator($key)) {
            return $this->getAttributeValue($key);
        }

        // Here we will determine if the model base class itself contains this given key
        // since we don't want to treat any of those methods as relationships because
        // they are all intended as helper methods and none of these are relations.
        if (method_exists(self::class, $key)) {
            return null;
        }

        // If the attribute is already loaded as a relationship, return it
        if ($this->relationLoaded($key)) {
            return $this->relations[$key];
        }

        // Check if this key corresponds to a relationship method
        if (method_exists($this, $key)) {
            return $this->getRelationshipFromMethod($key);
        }

        return null;
    }

    /**
     * Get a plain attribute (not a relationship).
     */
    public function getAttributeValue(string $key)
    {
        $value = $this->getAttributeFromArray($key);

        // If the attribute has a get mutator, we will call that then return what
        // it returns as the value, which is useful for transforming values on
        // retrieval from the model to a form that is more useful for usage.
        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        }

        // If the attribute exists within the cast array, we will convert it to
        // an appropriate native PHP type dependent upon the associated value
        // given with the key in the pair. Dayle made this comment line up.
        if ($this->hasCast($key)) {
            return $this->castAttribute($key, $value);
        }

        // If the attribute is listed as a date, we will convert it to a DateTime
        // instance on retrieval, which makes it quite convenient to work with
        // date fields without having to create a mutator for each property.
        if (in_array($key, $this->getDates()) &&
            !is_null($value)) {
            return $this->asDateTime($value);
        }

        return $value;
    }

    /**
     * Get an attribute from the $attributes array.
     */
    public function getAttributeFromArray(string $key)
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Get a raw attribute from the $attributes array (bypassing mutators).
     */
    public function getRawAttribute(string $key)
    {
        return $this->getAttributeFromArray($key);
    }

    /**
     * Get all the current attributes on the model.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Set the array of model attributes. No checking is done.
     */
    public function setRawAttributes(array $attributes, bool $sync = false): static
    {
        $this->attributes = $attributes;

        if ($sync) {
            $this->syncOriginal();
        }

        return $this;
    }

    /**
     * Get the model's original attribute values.
     */
    public function getOriginal(?string $key = null)
    {
        return $key ? ($this->original[$key] ?? null) : $this->original;
    }

    /**
     * Sync the original attributes with the current.
     */
    public function syncOriginal(): static
    {
        $this->original = $this->attributes;

        return $this;
    }

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        if (!isset($this->table)) {
            return $this->generateTableName();
        }

        return $this->table;
    }

    /**
     * Get the qualified column name for the model.
     *
     * @param  string  $column
     * @return string
     */
    public function qualifyColumn(string $column): string
    {
        if (str_contains($column, '.')) {
            return $column;
        }

        return $this->getTable() . '.' . $column;
    }

    /**
     * Generate the table name from the model class name.
     */
    protected function generateTableName(): string
    {
        $className = class_basename($this);
        
        // Convert to snake_case and pluralize
        $tableName = snake_case($className);
        
        return $this->pluralize($tableName);
    }

    /**
     * Simple pluralization for table names.
     */
    protected function pluralize(string $word): string
    {
        // Simple pluralization rules - can be extended
        $word = strtolower($word);
        
        // Common irregular plurals and special cases
        $irregulars = [
            'person' => 'people',
            'man' => 'men',
            'woman' => 'women',
            'child' => 'children',
            'foot' => 'feet',
            'tooth' => 'teeth',
            'goose' => 'geese',
            'mouse' => 'mice',
            'ox' => 'oxen',
            // Special 'o' endings that just add 's'
            'piano' => 'pianos',
            'photo' => 'photos',
            'radio' => 'radios',
            'video' => 'videos',
            'studio' => 'studios',
            'auto' => 'autos',
            'memo' => 'memos',
        ];
        
        if (isset($irregulars[$word])) {
            return $irregulars[$word];
        }
        
        // Words ending in 'y' preceded by a consonant -> 'ies'
        if (preg_match('/[bcdfghjklmnpqrstvwxyz]y$/', $word)) {
            return substr($word, 0, -1) . 'ies';
        }
        
        // Words ending in 's', 'ss', 'sh', 'ch', 'x', 'z' -> add 'es'
        if (preg_match('/(s|ss|sh|ch|x|z)$/', $word)) {
            return $word . 'es';
        }
        
        // Words ending in 'fe' -> 'ves'
        if (preg_match('/fe$/', $word)) {
            return substr($word, 0, -2) . 'ves';
        }
        
        // Words ending in 'f' -> 'ves'
        if (preg_match('/f$/', $word)) {
            return substr($word, 0, -1) . 'ves';
        }
        
        // Words ending in 'o'
        if (preg_match('/o$/', $word)) {
            // Special cases for vowel + 'o' (like piano, radio)
            if (preg_match('/[aeiou]o$/', $word)) {
                return $word . 's';
            }
            // Consonant + 'o' usually gets 'es' (like hero, potato)
            else {
                return $word . 'es';
            }
        }
        
        // Default: just add 's'
        return $word . 's';
    }

    /**
     * Set the table associated with the model.
     */
    public function setTable(string $table): static
    {
        $this->table = $table;
        
        return $this;
    }

    /**
     * Get the primary key for the model.
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Get the auto-incrementing key type.
     */
    public function getKeyType(): string
    {
        return $this->keyType;
    }

    /**
     * Get the class name for polymorphic relations.
     *
     * @return string
     */
    public function getMorphClass()
    {
        $morphMap = static::$morphMap ?? [];

        if (! empty($morphMap) && in_array(static::class, $morphMap)) {
            return array_search(static::class, $morphMap, true);
        }

        return static::class;
    }

    /**
     * Get the actual class name for a given morph alias.
     *
     * @param  string  $alias
     * @return string
     */
    public static function getActualClassNameForMorph($alias)
    {
        return static::$morphMap[$alias] ?? $alias;
    }

    /**
     * Get the value of the model's primary key.
     */
    public function getKey()
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Get the queueable identity for the entity.
     */
    public function getQueueableId()
    {
        return $this->getKey();
    }

    /**
     * Get the relationships for the entity.
     */
    public function getQueueableRelations(): array
    {
        $relations = [];

        foreach ($this->getAttributes() as $key => $value) {
            if (method_exists($this, $key)) {
                $relations[] = $key;
            }
        }

        return $relations;
    }

    /**
     * Get the name of the "updated at" column.
     */
    public function getUpdatedAtColumn(): ?string
    {
        return static::UPDATED_AT;
    }

    /**
     * Get the name of the "created at" column.
     */
    public function getCreatedAtColumn(): ?string
    {
        return static::CREATED_AT;
    }

    /**
     * Get a fresh timestamp for the model.
     */
    public function freshTimestampString(): string
    {
        return $this->freshTimestamp();
    }

    /**
     * Make the given, typically visible, attributes hidden.
     */
    public function makeHidden($attributes): static
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();

        $this->hidden = array_merge($this->hidden, $attributes);

        return $this;
    }

    /**
     * Make the given, typically hidden, attributes visible.
     */
    public function makeVisible($attributes): static
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();

        $this->hidden = array_diff($this->hidden, $attributes);

        return $this;
    }

    /**
     * Append attributes to query when building a query.
     */
    public function append($attributes): static
    {
        $this->appends = array_unique(
            array_merge($this->appends ?? [], is_string($attributes) ? [$attributes] : $attributes)
        );

        return $this;
    }

    /**
     * Determine if the model or any of the given attribute(s) have been modified.
     */
    public function is(?Model $model): bool
    {
        return !is_null($model) &&
               $this->getKey() === $model->getKey() &&
               $this->getTable() === $model->getTable() &&
               $this->getConnectionName() === $model->getConnectionName();
    }

    /**
     * Determine if a get mutator exists for an attribute.
     */
    public function hasGetMutator(string $key): bool
    {
        return method_exists($this, 'get' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $key))) . 'Attribute');
    }

    /**
     * Get the value of an attribute using its mutator.
     */
    protected function mutateAttribute(string $key, $value)
    {
        return $this->{'get' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $key))) . 'Attribute'}($value);
    }

    /**
     * Determine if a set mutator exists for an attribute.
     */
    public function hasSetMutator(string $key): bool
    {
        return method_exists($this, 'set' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $key))) . 'Attribute');
    }

    /**
     * Set the value of an attribute using its mutator.
     */
    protected function setMutatedAttributeValue(string $key, $value): static
    {
        return $this->{'set' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $key))) . 'Attribute'}($value);
    }

    /**
     * Determine whether an attribute should be cast to a native type.
     */
    public function hasCast(string $key, ?array $types = null): bool
    {
        if (array_key_exists($key, $this->getCasts())) {
            return $types ? in_array($this->getCastType($key), (array) $types) : true;
        }

        return false;
    }

    /**
     * Get the casts array.
     */
    public function getCasts(): array
    {
        if ($this->getIncrementing()) {
            return array_merge([$this->getKeyName() => $this->getKeyType()], $this->casts);
        }

        return $this->casts;
    }

    /**
     * Get the type of cast for a model attribute.
     */
    protected function getCastType(string $key): string
    {
        if ($this->isCustomDateTimeCast($this->getCasts()[$key])) {
            return 'custom_datetime';
        }

        if ($this->isDecimalCast($this->getCasts()[$key])) {
            return 'decimal';
        }

        return trim(strtolower($this->getCasts()[$key]));
    }

    /**
     * Determine if the cast type is a custom date time cast.
     */
    protected function isCustomDateTimeCast(string $cast): bool
    {
        return strncmp($cast, 'date:', 5) === 0 ||
               strncmp($cast, 'datetime:', 9) === 0;
    }

    /**
     * Determine if the cast type is a decimal cast.
     */
    protected function isDecimalCast(string $cast): bool
    {
        return strncmp($cast, 'decimal:', 8) === 0;
    }

    /**
     * Cast an attribute to a native PHP type.
     */
    protected function castAttribute(string $key, $value)
    {
        $castType = $this->getCastType($key);

        if (is_null($value) && in_array($castType, static::$primitiveCastTypes)) {
            return $value;
        }

        switch ($castType) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return $this->fromFloat($value);
            case 'decimal':
                return $this->asDecimal($value, explode(':', $this->getCasts()[$key], 2)[1]);
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'object':
                return $this->fromJson($value, true);
            case 'array':
            case 'json':
                return $this->fromJson($value);
            case 'collection':
                return new BaseCollection($this->fromJson($value));
            case 'date':
                return $this->asDate($value);
            case 'datetime':
            case 'custom_datetime':
                return $this->asDateTime($value);
            case 'timestamp':
                return $this->asTimestamp($value);
        }

        return $value;
    }

    /**
     * Get the attributes that should be converted to dates.
     */
    public function getDates(): array
    {
        $defaults = [static::CREATED_AT, static::UPDATED_AT];

        return $this->usesTimestamps() ? array_unique(array_merge($this->dates ?? [], $defaults)) : $this->dates ?? [];
    }

    /**
     * The attributes that should be mutated to dates.
     */
    protected array $dates = [];

    /**
     * Determine whether a value is Date / DateTime castable for inbound manipulation.
     */
    protected function isDateAttribute(string $key): bool
    {
        return in_array($key, $this->getDates(), true) ||
               $this->isDateCastable($key);
    }

    /**
     * Determine whether a value is Date / DateTime castable for inbound manipulation.
     */
    protected function isDateCastable(string $key): bool
    {
        return $this->hasCast($key, ['date', 'datetime']);
    }

    /**
     * Determine whether a value is JSON castable for inbound manipulation.
     */
    protected function isJsonCastable(string $key): bool
    {
        return $this->hasCast($key, ['array', 'json', 'object', 'collection']);
    }

    /**
     * Cast the given attribute to JSON.
     */
    protected function castAttributeAsJson(string $key, $value): string
    {
        $value = $this->asJson($value);

        if ($value === false) {
            throw new \InvalidArgumentException("Unable to encode attribute [{$key}] for model [" . static::class . '] to JSON: ' . json_last_error_msg());
        }

        return $value;
    }

    /**
     * Encode the given value as JSON.
     */
    protected function asJson($value): string
    {
        return json_encode($value);
    }

    /**
     * Decode the given JSON back into an array or object.
     */
    public function fromJson(string $value, bool $asObject = false)
    {
        return json_decode($value, !$asObject);
    }

    /**
     * Return a decimal as string.
     */
    protected function asDecimal($value, int $decimals): string
    {
        return number_format($value, $decimals, '.', '');
    }

    /**
     * Return a timestamp as unix timestamp.
     */
    protected function asTimestamp($value): int
    {
        return $this->asDateTime($value)->getTimestamp();
    }

    /**
     * Return a timestamp as DateTime object.
     */
    protected function asDateTime($value): \DateTime
    {
        // If this value is already a DateTime instance, we shall just return it as is.
        // This prevents us having to re-instantiate a DateTime instance when we know
        // it already is one, which wouldn't be fulfilled by the DateTime check.
        if ($value instanceof \DateTime) {
            return $value;
        }

        // If the value is numeric, we will assume it is a UNIX timestamp's value
        // and format a DateTime object from this timestamp. This allows flexibility
        // when defining your date fields as they might be UNIX timestamps here.
        if (is_numeric($value)) {
            return (new \DateTime())->setTimestamp($value);
        }

        // If the value is in simply year, month, day format, we will instantiate the
        // DateTime instances from that format. Again, this provides for simple date
        // fields on the database, while still supporting Dates for complex needs.
        if ($this->isStandardDateFormat($value)) {
            return \DateTime::createFromFormat('Y-m-d', $value);
        }

        // Finally, we will just assume this date is in the format used by default on
        // the database connection and use that format to create the DateTime object
        // that is returned back out to the developers after we convert it here.
        return \DateTime::createFromFormat($this->getDateFormat(), $value);
    }

    /**
     * Return a timestamp as DateTime object with date set to 1970-01-01.
     */
    protected function asDate($value): \DateTime
    {
        return $this->asDateTime($value)->setTime(0, 0, 0);
    }

    /**
     * Determine if the given value is a standard date format.
     */
    protected function isStandardDateFormat(string $value): bool
    {
        return preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value);
    }

    /**
     * Convert a DateTime to a storable string.
     */
    public function fromDateTime($value): ?string
    {
        return empty($value) ? $value : $this->asDateTime($value)->format($this->getDateFormat());
    }

    /**
     * Return a timestamp as float.
     */
    protected function fromFloat($value): float
    {
        switch ((string) $value) {
            case 'Infinity':
                return INF;
            case '-Infinity':
                return -INF;
            case 'NaN':
                return NAN;
            default:
                return (float) $value;
        }
    }

    /**
     * Get the format for database stored dates.
     */
    public function getDateFormat(): string
    {
        return $this->dateFormat ?: $this->getConnection()->getQueryGrammar()->getDateFormat();
    }

    /**
     * Get the database connection for the model.
     */
    public function getConnection(): ConnectionInterface
    {
        return static::resolveConnection($this->getConnectionName());
    }

    /**
     * Get the current connection name for the model.
     */
    public function getConnectionName(): ?string
    {
        return $this->connection ?: static::$defaultConnection;
    }

    /**
     * Set the connection associated with the model.
     */
    public function setConnection(?string $name): static
    {
        $this->connection = $name;

        return $this;
    }

    /**
     * Resolve a connection instance.
     */
    public static function resolveConnection(?string $connection = null): ConnectionInterface
    {
        return static::$resolver->connection($connection);
    }

    /**
     * Get the connection resolver instance.
     */
    public static function getConnectionResolver(): ?DatabaseManager
    {
        return static::$resolver;
    }

        /**
     * Set the connection resolver instance.
     */
    public static function setConnectionResolver(ConnectionResolverInterface $resolver): void
    {
        static::$resolver = $resolver;
    }

    /**
     * Set the default connection name.
     *
     * @param  string  $name
     * @return void
     */
    public static function setDefaultConnection($name)
    {
        static::$defaultConnection = $name;
    }

    /**
     * Get the default connection name.
     *
     * @return string|null
     */
    public static function getDefaultConnection()
    {
        return static::$defaultConnection;
    }

    /**
     * The default connection name for models.
     *
     * @var string|null
     */
    protected static $defaultConnection;

    /**
     * Begin querying the model.
     */
    public static function query(): EloquentBuilder
    {
        return (new static)->newQuery();
    }



    /**
     * Get the first record matching the attributes or create it.
     */
    public static function firstOrCreate(array $attributes, array $values = []): static
    {
        if (!is_null($instance = static::where($attributes)->first())) {
            return $instance;
        }

        return static::create(array_merge($attributes, $values));
    }

    /**
     * Handle dynamic static method calls into the method.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return (new static)->newQuery()->$method(...$parameters);
    }

    /**
     * Handle dynamic method calls into the model.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->forwardCallTo($this->newQuery(), $method, $parameters);
    }

    /**
     * Forward a method call to the given object.
     *
     * @param  mixed  $object
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    protected function forwardCallTo($object, $method, $parameters)
    {
        try {
            return $object->{$method}(...$parameters);
        } catch (\Error|\BadMethodCallException $e) {
            $pattern = '~^Call to undefined method (?P<class>[^:]+)::(?P<method>[^\(]+)\(\)$~';

            if (! preg_match($pattern, $e->getMessage(), $matches)) {
                throw $e;
            }

            if ($matches['class'] != get_class($object) ||
                $matches['method'] != $method) {
                throw $e;
            }

            throw new \BadMethodCallException(sprintf(
                'Call to undefined method %s::%s()', static::class, $method
            ));
        }
    }

    /**
     * Get a new query builder for the model's table.
     */
    public function newQuery(): EloquentBuilder
    {
        return $this->newEloquentBuilder(
            $this->newBaseQueryBuilder()
        )->setModel($this);
    }

    /**
     * Get a new query builder that doesn't have any global scopes or eager loading.
     */
    public function newQueryWithoutRelationships(): EloquentBuilder
    {
        return $this->newEloquentBuilder(
            $this->newBaseQueryBuilder()
        )->setModel($this);
    }

    /**
     * Get a new query builder with no relationships loaded.
     */
    public function newQueryWithoutScopes(): EloquentBuilder
    {
        $builder = $this->newEloquentBuilder($this->newBaseQueryBuilder())->setModel($this);

        // Remove all global scopes that were applied in newEloquentBuilder.
        $scopes = array_keys($this->getGlobalScopes());

        if (!empty($scopes)) {
            $builder->withoutGlobalScope($scopes);
        }

        return $builder;
    }

    /**
     * Create a new Eloquent query builder for the model.
     */
    public function newEloquentBuilder(QueryBuilder $query): EloquentBuilder
    {
        $builder = new EloquentBuilder($query);
        
        // Apply global scopes to the builder
        foreach ($this->getGlobalScopes() as $identifier => $scope) {
            $builder->withGlobalScope($identifier, $scope);
        }
        
        return $builder;
    }

    /**
     * Create a new instance of the given model.
     */
    public function newInstance(array $attributes = [], bool $exists = false): static
    {
        // This method just provides a convenient way for us to generate fresh model
        // instances of this current model. It is particularly useful during the
        // hydration of new objects via the Eloquent query builder instances.
        $model = new static((array) $attributes);

        $model->exists = $exists;

        $model->setConnection(
            $this->getConnectionName()
        );

        $model->setTable($this->getTable());

        return $model;
    }

    /**
     * Create a new model instance that is existing.
     */
    public function newFromBuilder(array $attributes = [], ?string $connection = null): static
    {
        $model = $this->newInstance([], true);

        $model->setRawAttributes((array) $attributes, true);

        $model->setConnection($connection ?: $this->getConnectionName());

        return $model;
    }

    /**
     * Create a collection of models from plain arrays.
     */
    public static function hydrate(array $items): Collection
    {
        $instance = new static;

        $models = array_map(function ($item) use ($instance) {
            // Convert stdClass objects to arrays for compatibility
            $attributes = is_object($item) ? (array) $item : $item;
            return $instance->newFromBuilder($attributes);
        }, $items);

        return $instance->newCollection($models);
    }

    /**
     * Create a new Eloquent Collection instance.
     */
    public function newCollection(array $models = []): Collection
    {
        return new Collection($models);
    }

    /**
     * Get a new query builder instance for the connection.
     */
    protected function newBaseQueryBuilder(): QueryBuilder
    {
        $connection = $this->getConnection();

        return $connection->table($this->getTable());
    }

    /**
     * Save the model to the database.
     */
    public function save(): bool
    {
        if ($this->exists) {
            $saved = $this->performUpdate();
        } else {
            $saved = $this->performInsert();

            if (!$this->getConnectionName() &&
                $connection = $this->getConnection()) {
                $this->setConnection($connection->getName());
            }
        }

        if ($saved) {
            $this->finishSave();
        }

        return $saved;
    }

    /**
     * Delete the model from the database.
     *
     * @return bool
     * @throws \Exception
     */
    public function delete(): bool
    {
        if (is_null($this->getKeyName())) {
            throw new \Exception('No primary key defined on model.');
        }

        if (!$this->exists) {
            return false;
        }

        $this->performDeleteOnModel();

        $this->exists = false;

        return true;
    }

    /**
     * Perform the actual delete query on this model instance.
     *
     * @return void
     */
    protected function performDeleteOnModel(): void
    {
        $this->newQueryWithoutRelationships()
            ->where($this->getKeyName(), $this->getKey())
            ->delete();
    }

    /**
     * Perform a model insert operation.
     */
    protected function performInsert(): bool
    {
        if ($this->timestamps) {
            $this->updateTimestamps();
        }

        $attributes = $this->attributes;

        if ($this->getIncrementing()) {
            $this->insertAndSetId($attributes);
        } else {
            if (empty($attributes)) {
                return true;
            }

            $this->newQuery()->insert($attributes);
        }

        $this->exists = true;
        $this->wasRecentlyCreated = true;

        return true;
    }

    /**
     * Insert the given attributes and set the ID on the model.
     */
    protected function insertAndSetId(array $attributes): void
    {
        $id = $this->newQuery()->insertGetId($attributes, $keyName = $this->getKeyName());

        $this->setAttribute($keyName, $id);
    }

    /**
     * Perform a model update operation.
     */
    protected function performUpdate(): bool
    {
        if ($this->timestamps) {
            $this->updateTimestamps();
        }

        $dirty = $this->getDirty();

        if (count($dirty) > 0) {
            // Use a query without global scopes when saving to ensure updates
            // such as restoring soft-deleted models are applied to the
            // underlying row even if global scopes would otherwise filter it.
            $this->setKeysForSaveQuery($this->newQueryWithoutScopes())->update($dirty);

            $this->syncChanges();
        }

        return true;
    }

    /**
     * Set the keys for a save update query.
     */
    protected function setKeysForSaveQuery(EloquentBuilder $query): EloquentBuilder
    {
        $query->where($this->getKeyName(), '=', $this->getKeyForSaveQuery());

        return $query;
    }

    /**
     * Get the primary key value for a save query.
     */
    protected function getKeyForSaveQuery()
    {
        return $this->original[$this->getKeyName()] ?? $this->getKey();
    }

    /**
     * Finish processing on a successful save operation.
     */
    protected function finishSave(): void
    {
        $this->syncOriginal();

        $this->syncChanges();
    }

    /**
     * Sync the changed attributes.
     */
    public function syncChanges(): static
    {
        $this->changes = $this->getDirty();

        return $this;
    }

    /**
     * Get the attributes that have been changed since last sync.
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->getAttributes() as $key => $value) {
            if (!$this->originalIsEquivalent($key, $value)) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Determine if the new and old values for a given key are equivalent.
     */
    public function originalIsEquivalent(string $key, $current): bool
    {
        if (!array_key_exists($key, $this->original)) {
            return false;
        }

        $original = $this->getOriginal($key);

        if ($current === $original) {
            return true;
        } elseif (is_null($current)) {
            return false;
        }

        return false;
    }

    /**
     * Update the model's update timestamp.
     */
    public function touch(): bool
    {
        if (!$this->timestamps) {
            return false;
        }

        $this->updateTimestamps();

        return $this->save();
    }

    /**
     * Update the creation and update timestamps.
     */
    protected function updateTimestamps(): void
    {
        $time = $this->freshTimestamp();

        if (!is_null(static::UPDATED_AT) && !$this->isDirty(static::UPDATED_AT)) {
            $this->setUpdatedAt($time);
        }

        if (!$this->exists && !is_null(static::CREATED_AT) &&
            !$this->isDirty(static::CREATED_AT)) {
            $this->setCreatedAt($time);
        }
    }

    /**
     * Set the value of the "created at" attribute.
     */
    public function setCreatedAt($value): static
    {
        $this->{static::CREATED_AT} = $value;

        return $this;
    }

    /**
     * Set the value of the "updated at" attribute.
     */
    public function setUpdatedAt($value): static
    {
        $this->{static::UPDATED_AT} = $value;

        return $this;
    }

    /**
     * Get a fresh timestamp for the model.
     */
    public function freshTimestamp(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Determine if the given attribute is dirty.
     */
    public function isDirty(?string $attributes = null): bool
    {
        return $this->hasChanges(
            $this->getDirty(),
            is_array($attributes) ? $attributes : func_get_args()
        );
    }

    /**
     * Determine if any of the given attributes were changed.
     */
    protected function hasChanges(array $changes, ?array $attributes = null): bool
    {
        // If no specific attributes were provided, we just need to determine if
        // any attributes have been changed in any way and will return that result.
        if (empty($attributes)) {
            return count($changes) > 0;
        }

        // Otherwise, we will check each of the attributes and return true if any of
        // them have been changed. If no changes have been made to any of the given
        // attributes we can return false here and short circuit the other checks.
        foreach (array_wrap($attributes) as $attribute) {
            if (array_key_exists($attribute, $changes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the model or any of the given attribute(s) have been modified.
     */
    public function wasChanged($attributes = null): bool
    {
        return $this->hasChanges(
            $this->getChanges(),
            is_array($attributes) ? $attributes : func_get_args()
        );
    }

    /**
     * Get the attributes that were changed.
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * Determine if the model uses timestamps.
     */
    public function usesTimestamps(): bool
    {
        return $this->timestamps;
    }

    /**
     * Get the value indicating whether the IDs are incrementing.
     */
    public function getIncrementing(): bool
    {
        return $this->incrementing;
    }

    /**
     * Convert the model instance to an array.
     */
    public function toArray(): array
    {
        return $this->attributesToArray();
    }

    /**
     * Convert the model's attributes to an array.
     */
    public function attributesToArray(): array
    {
        $attributes = $this->getAttributes();

        // If we have some attributes to hide from the array
        if (count($this->getHidden()) > 0) {
            $attributes = array_diff_key($attributes, array_flip($this->getHidden()));
        }

        // If we have visible attributes defined, only include those
        if (count($this->getVisible()) > 0) {
            $attributes = array_intersect_key($attributes, array_flip($this->getVisible()));
        }

        return $attributes;
    }

    /**
     * Convert the model instance to JSON.
     */
    public function toJson(int $options = 0): string
    {
        $json = json_encode($this->jsonSerialize(), $options);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new Exception('Error encoding model to JSON: ' . json_last_error_msg());
        }

        return $json;
    }

    /**
     * Convert the object into something JSON serializable.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Get the hidden attributes for the model.
     */
    public function getHidden(): array
    {
        return $this->hidden;
    }

    /**
     * Get the visible attributes for the model.
     */
    public function getVisible(): array
    {
        return $this->visible;
    }

    /**
     * Dynamically retrieve attributes on the model.
     */
    public function __get(string $key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the model.
     */
    public function __set(string $key, $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Determine if the given attribute exists.
     */
    public function offsetExists($offset): bool
    {
        return !is_null($this->getAttribute($offset));
    }

    /**
     * Get the value for a given offset.
     */
    public function offsetGet($offset)
    {
        return $this->getAttribute($offset);
    }

    /**
     * Set the value for a given offset.
     */
    public function offsetSet($offset, $value): void
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * Unset the value for a given offset.
     */
    public function offsetUnset($offset): void
    {
        unset($this->attributes[$offset]);
    }

    /**
     * Determine if an attribute or relation exists on the model.
     */
    public function __isset(string $key): bool
    {
        return $this->offsetExists($key);
    }

    /**
     * Unset an attribute on the model.
     */
    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * Convert the model to its string representation.
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * The loaded relationships for the model.
     */
    protected array $relations = [];

    /**
     * Define a one-to-one relationship.
     *
     * @param  string  $related
     * @param  string|null  $foreignKey
     * @param  string|null  $localKey
     * @return \Database\Eloquent\Relations\HasOne
     */
    public function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null)
    {
        $instance = $this->newRelatedInstance($related);

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        return $this->newHasOne($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
     * Define a one-to-many relationship.
     *
     * @param  string  $related
     * @param  string|null  $foreignKey
     * @param  string|null  $localKey
     * @return \Database\Eloquent\Relations\HasMany
     */
    public function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null)
    {
        $instance = $this->newRelatedInstance($related);

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        return $this->newHasMany(
            $instance->newQuery(), $this, $foreignKey, $localKey
        );
    }

    /**
     * Define an inverse one-to-one or many relationship.
     *
     * @param  string  $related
     * @param  string|null  $foreignKey
     * @param  string|null  $ownerKey
     * @param  string|null  $relation
     * @return \Database\Eloquent\Relations\BelongsTo
     */
    public function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null, ?string $relation = null)
    {
        $relation = $relation ?: $this->guessBelongsToRelation();

        $instance = $this->newRelatedInstance($related);

        if (is_null($foreignKey)) {
            $foreignKey = snake_case($relation).'_'.$instance->getKeyName();
        }

        $ownerKey = $ownerKey ?: $instance->getKeyName();

        return $this->newBelongsTo(
            $instance->newQuery(), $this, $foreignKey, $ownerKey, $relation
        );
    }

    /**
     * Define a has-one-through relationship.
     *
     * @param  string  $related
     * @param  string  $through
     * @param  string|null  $firstKey
     * @param  string|null  $secondKey
     * @param  string|null  $localKey
     * @param  string|null  $secondLocalKey
     * @return \Arpon\Database\Eloquent\Relations\HasOneThrough
     */
    public function hasOneThrough(string $related, string $through, ?string $firstKey = null, ?string $secondKey = null, ?string $localKey = null, ?string $secondLocalKey = null)
    {
        $through = new $through;

        $firstKey = $firstKey ?: $this->getForeignKey();
        $secondKey = $secondKey ?: $through->getForeignKey();
        $localKey = $localKey ?: $this->getKeyName();
        $secondLocalKey = $secondLocalKey ?: $through->getKeyName();

        return $this->newHasOneThrough(
            $this->newRelatedInstance($related)->newQuery(),
            $this, $through, $firstKey, $secondKey, $localKey, $secondLocalKey
        );
    }

    /**
     * Define a has-many-through relationship.
     *
     * @param  string  $related
     * @param  string  $through
     * @param  string|null  $firstKey
     * @param  string|null  $secondKey
     * @param  string|null  $localKey
     * @param  string|null  $secondLocalKey
     * @return \Arpon\Database\Eloquent\Relations\HasManyThrough
     */
    public function hasManyThrough(string $related, string $through, ?string $firstKey = null, ?string $secondKey = null, ?string $localKey = null, ?string $secondLocalKey = null)
    {
        $through = new $through;

        $firstKey = $firstKey ?: $this->getForeignKey();
        $secondKey = $secondKey ?: $through->getForeignKey();
        $localKey = $localKey ?: $this->getKeyName();
        $secondLocalKey = $secondLocalKey ?: $through->getKeyName();

        return $this->newHasManyThrough(
            $this->newRelatedInstance($related)->newQuery(),
            $this, $through, $firstKey, $secondKey, $localKey, $secondLocalKey
        );
    }

    /**
     * Define a polymorphic one-to-one relationship.
     *
     * @param  string  $related
     * @param  string  $name
     * @param  string|null  $type
     * @param  string|null  $id
     * @param  string|null  $localKey
     * @return \Arpon\Database\Eloquent\Relations\MorphOne
     */
    public function morphOne(string $related, string $name, ?string $type = null, ?string $id = null, ?string $localKey = null)
    {
        $instance = $this->newRelatedInstance($related);

        [$type, $id] = $this->getMorphs($name, $type, $id);

        $table = $instance->getTable();

        $localKey = $localKey ?: $this->getKeyName();

        return $this->newMorphOne($instance->newQuery(), $this, $type, $id, $localKey);
    }

    /**
     * Define a polymorphic one-to-many relationship.
     *
     * @param  string  $related
     * @param  string  $name
     * @param  string|null  $type
     * @param  string|null  $id
     * @param  string|null  $localKey
     * @return \Arpon\Database\Eloquent\Relations\MorphMany
     */
    public function morphMany(string $related, string $name, ?string $type = null, ?string $id = null, ?string $localKey = null)
    {
        $instance = $this->newRelatedInstance($related);

        [$type, $id] = $this->getMorphs($name, $type, $id);

        $table = $instance->getTable();

        $localKey = $localKey ?: $this->getKeyName();

        return $this->newMorphMany($instance->newQuery(), $this, $type, $id, $localKey);
    }

    /**
     * Define a polymorphic, inverse one-to-one or many relationship.
     *
     * @param  string|null  $name
     * @param  string|null  $type
     * @param  string|null  $id
     * @param  string|null  $ownerKey
     * @return \Arpon\Database\Eloquent\Relations\MorphTo
     */
    public function morphTo(?string $name = null, ?string $type = null, ?string $id = null, ?string $ownerKey = null)
    {
        $name = $name ?: $this->guessBelongsToRelation();

        [$type, $id] = $this->getMorphs(snake_case($name), $type, $id);

        return $this->morphEagerTo($name, $type, $id, $ownerKey);
    }

    /**
     * Define a many-to-many polymorphic relationship.
     *
     * @param  string  $related
     * @param  string  $name
     * @param  string|null  $table
     * @param  string|null  $foreignPivotKey
     * @param  string|null  $relatedPivotKey
     * @param  string|null  $parentKey
     * @param  string|null  $relatedKey
     * @param  bool  $inverse
     * @return \Arpon\Database\Eloquent\Relations\MorphToMany
     */
    public function morphToMany(string $related, string $name, ?string $table = null, ?string $foreignPivotKey = null, ?string $relatedPivotKey = null, ?string $parentKey = null, ?string $relatedKey = null, bool $inverse = false)
    {
        $caller = $this->guessBelongsToRelation();

        $instance = $this->newRelatedInstance($related);

        $foreignPivotKey = $foreignPivotKey ?: $name.'_id';
        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();

        $table = $table ?: str_plural($name);

        return $this->newMorphToMany(
            $instance->newQuery(), $this, $name, $table,
            $foreignPivotKey, $relatedPivotKey, $parentKey ?: $this->getKeyName(),
            $relatedKey ?: $instance->getKeyName(), $caller, $inverse
        );
    }

    /**
     * Define a polymorphic many-to-many inverse relationship.
     *
     * @param  string  $related
     * @param  string  $name
     * @param  string|null  $table
     * @param  string|null  $foreignPivotKey
     * @param  string|null  $relatedPivotKey
     * @param  string|null  $parentKey
     * @param  string|null  $relatedKey
     * @return \Arpon\Database\Eloquent\Relations\MorphToMany
     */
    public function morphedByMany(string $related, string $name, ?string $table = null, ?string $foreignPivotKey = null, ?string $relatedPivotKey = null, ?string $parentKey = null, ?string $relatedKey = null)
    {
        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();
        $relatedPivotKey = $relatedPivotKey ?: $name.'_id';

        return $this->morphToMany(
            $related, $name, $table, $foreignPivotKey,
            $relatedPivotKey, $parentKey, $relatedKey, true
        );
    }

    /**
     * Define a many-to-many relationship.
     *
     * @param string $related
     * @param string|null $table
     * @param string|null $foreignPivotKey
     * @param string|null $relatedPivotKey
     * @param string|null $parentKey
     * @param string|null $relatedKey
     * @param string|null $relation
     * @return \Arpon\Database\Eloquent\Relations\BelongsToMany
     */
    public function belongsToMany(string $related, ?string $table = null, ?string $foreignPivotKey = null, ?string $relatedPivotKey = null, ?string $parentKey = null, ?string $relatedKey = null, ?string $relation = null)
    {
        $instance = $this->newRelatedInstance($related);

        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();
        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();

        $table = $table ?: $this->getTable().'_'. $instance->getTable();

        return $this->newBelongsToMany(
            $instance->newQuery(), $this, $table, $foreignPivotKey,
            $relatedPivotKey, $parentKey ?: $this->getKeyName(), $relatedKey ?: $instance->getKeyName(), $relation
        );
    }

    /**
     * Get the morphs for a polymorphic relationship.
     *
     * @param  string  $name
     * @param  string|null  $type
     * @param  string|null  $id
     * @return array
     */
    protected function getMorphs(string $name, ?string $type, ?string $id): array
    {
        return [$type ?: $name.'_type', $id ?: $name.'_id'];
    }

    /**
     * Create a new model instance for a related model.
     *
     * @param  string  $class
     * @return mixed
     */
    protected function newRelatedInstance(string $class)
    {
        return tap(new $class, function ($instance) {
            if (! $instance->getConnectionName()) {
                $instance->setConnection($this->connection);
            }
        });
    }

    /**
     * Get the default foreign key name for the model.
     *
     * @return string
     */
    public function getForeignKey(): string
    {
        return snake_case(class_basename($this)).'_'.$this->getKeyName();
    }

    /**
     * Guess the "belongs to" relationship name.
     *
     * @return string
     */
    protected function guessBelongsToRelation(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        
        // Look for the calling method that isn't a magic method or this method
        foreach ($trace as $frame) {
            if (isset($frame['function']) && 
                isset($frame['class']) &&
                $frame['class'] !== __CLASS__ &&
                !in_array($frame['function'], [
                    '__call', '__callStatic', 'forwardCallTo', 
                    'guessBelongsToRelation', 'belongsTo', 'getAttribute', 
                    'getRelationshipFromMethod', '__get'
                ])) {
                return $frame['function'];
            }
        }

        // If we can't find a good method name, look for any method that's not internal
        foreach ($trace as $frame) {
            if (isset($frame['function']) && 
                !str_starts_with($frame['function'], '__') &&
                !in_array($frame['function'], [
                    'guessBelongsToRelation', 'belongsTo', 'getAttribute', 
                    'getRelationshipFromMethod', 'getAttributeValue'
                ])) {
                return $frame['function'];
            }
        }

        return 'relation'; // fallback
    }

    /**
     * Instantiate a new HasOne relationship.
     *
     * @param  \Arpon\Database\Eloquent\EloquentBuilder  $query
     * @param  \Arpon\Database\Eloquent\Model  $parent
     * @param  string  $foreignKey
     * @param  string  $localKey
     * @return \Arpon\Database\Eloquent\Relations\HasOne
     */
    protected function newHasOne(EloquentBuilder $query, Model $parent, string $foreignKey, string $localKey)
    {
        return new HasOne($query, $parent, $foreignKey, $localKey);
    }

    /**
     * Instantiate a new HasMany relationship.
     *
     * @param  \Arpon\Database\Eloquent\EloquentBuilder  $query
     * @param  \Arpon\Database\Eloquent\Model  $parent
     * @param  string  $foreignKey
     * @param  string  $localKey
     * @return \Arpon\Database\Eloquent\Relations\HasMany
     */
    protected function newHasMany(EloquentBuilder $query, Model $parent, string $foreignKey, string $localKey)
    {
        return new HasMany($query, $parent, $foreignKey, $localKey);
    }

    /**
     * Instantiate a new BelongsTo relationship.
     *
     * @param  \Arpon\Database\Eloquent\EloquentBuilder  $query
     * @param  \Arpon\Database\Eloquent\Model  $child
     * @param  string  $foreignKey
     * @param  string  $ownerKey
     * @param  string  $relation
     * @return \Arpon\Database\Eloquent\Relations\BelongsTo
     */
    protected function newBelongsTo(EloquentBuilder $query, Model $child, string $foreignKey, string $ownerKey, string $relation)
    {
        return new BelongsTo($query, $child, $foreignKey, $ownerKey, $relation);
    }

    /**
     * Instantiate a new HasOneThrough relationship.
     *
     * @param  \Arpon\Database\Eloquent\EloquentBuilder  $query
     * @param  \Arpon\Database\Eloquent\Model  $farParent
     * @param  \Arpon\Database\Eloquent\Model  $throughParent
     * @param  string  $firstKey
     * @param  string  $secondKey
     * @param  string  $localKey
     * @param  string  $secondLocalKey
     * @return \Arpon\Database\Eloquent\Relations\HasOneThrough
     */
    protected function newHasOneThrough(EloquentBuilder $query, Model $farParent, Model $throughParent, string $firstKey, string $secondKey, string $localKey, string $secondLocalKey)
    {
        return new HasOneThrough($query, $farParent, $throughParent, $firstKey, $secondKey, $localKey, $secondLocalKey);
    }

    /**
     * Instantiate a new HasManyThrough relationship.
     *
     * @param  \Arpon\Database\Eloquent\EloquentBuilder  $query
     * @param  \Arpon\Database\Eloquent\Model  $farParent
     * @param  \Arpon\Database\Eloquent\Model  $throughParent
     * @param  string  $firstKey
     * @param  string  $secondKey
     * @param  string  $localKey
     * @param  string  $secondLocalKey
     * @return \Arpon\Database\Eloquent\Relations\HasManyThrough
     */
    protected function newHasManyThrough(EloquentBuilder $query, Model $farParent, Model $throughParent, string $firstKey, string $secondKey, string $localKey, string $secondLocalKey)
    {
        return new HasManyThrough($query, $farParent, $throughParent, $firstKey, $secondKey, $localKey, $secondLocalKey);
    }

    /**
     * Instantiate a new MorphOne relationship.
     *
     * @param  \Arpon\Database\Eloquent\EloquentBuilder  $query
     * @param  \Arpon\Database\Eloquent\Model  $parent
     * @param  string  $type
     * @param  string  $id
     * @param  string  $localKey
     * @return \Arpon\Database\Eloquent\Relations\MorphOne
     */
    protected function newMorphOne(EloquentBuilder $query, Model $parent, string $type, string $id, string $localKey)
    {
        return new MorphOne($query, $parent, $type, $id, $localKey);
    }

    /**
     * Instantiate a new MorphMany relationship.
     *
     * @param  \Arpon\Database\Eloquent\EloquentBuilder  $query
     * @param  \Arpon\Database\Eloquent\Model  $parent
     * @param  string  $type
     * @param  string  $id
     * @param  string  $localKey
     * @return \Arpon\Database\Eloquent\Relations\MorphMany
     */
    protected function newMorphMany(EloquentBuilder $query, Model $parent, string $type, string $id, string $localKey)
    {
        return new MorphMany($query, $parent, $type, $id, $localKey);
    }

    /**
     * Instantiate a new MorphTo relationship.
     *
     * @param  string  $name
     * @param  string  $type
     * @param  string  $id
     * @param  string|null  $ownerKey
     * @return \Arpon\Database\Eloquent\Relations\MorphTo
     */
    protected function morphEagerTo(string $name, string $type, string $id, ?string $ownerKey)
    {
        return new MorphTo(
            $this->newQuery(),
            $this,
            $id,
            $ownerKey ?: $this->getKeyName(),
            $type,
            $name
        );
    }

    /**
     * Instantiate a new MorphToMany relationship.
     *
     * @param  \Arpon\Database\Eloquent\EloquentBuilder  $query
     * @param  \Arpon\Database\Eloquent\Model  $parent
     * @param  string  $name
     * @param  string  $table
     * @param  string  $foreignPivotKey
     * @param  string  $relatedPivotKey
     * @param  string  $parentKey
     * @param  string  $relatedKey
     * @param  string  $relationName
     * @param  bool  $inverse
     * @return \Arpon\Database\Eloquent\Relations\MorphToMany
     */
    protected function newMorphToMany(EloquentBuilder $query, Model $parent, string $name, string $table, string $foreignPivotKey, string $relatedPivotKey, string $parentKey, string $relatedKey, string $relationName, bool $inverse = false)
    {
        return new MorphToMany($query, $parent, $name, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName, $inverse);
    }

    /**
     * Instantiate a new BelongsToMany relationship.
     *
     * @param  \Arpon\Database\Eloquent\EloquentBuilder  $query
     * @param  \Arpon\Database\Eloquent\Model  $parent
     * @param  string  $table
     * @param  string  $foreignPivotKey
     * @param  string  $relatedPivotKey
     * @param  string  $parentKey
     * @param  string  $relatedKey
     * @param  string|null $relationName
     * @return \Arpon\Database\Eloquent\Relations\BelongsToMany
     */
    protected function newBelongsToMany(EloquentBuilder $query, Model $parent, string $table, string $foreignPivotKey, string $relatedPivotKey, string $parentKey, string $relatedKey, ?string $relationName = null)
    {
        return new BelongsToMany($query, $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName);
    }

    /**
     * Get a relationship value from a method.
     *
     * @param  string  $method
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    protected function getRelationshipFromMethod(string $method)
    {
        // Temporary debug: throw exception to inspect relation method and parent attributes
        // (will be removed once debugging is complete).
        // throw new \Exception(sprintf("DEBUG getRelationshipFromMethod: method=%s, parent=%s", $method, json_encode($this->attributes)));

        $relation = $this->$method();

        if (! $relation instanceof \Arpon\Database\Eloquent\Relations\Relation) {
            if (is_null($relation)) {
                throw new \LogicException(sprintf(
                    '%s::%s must return a relationship instance, but "null" was returned. Was the "return" keyword used?', static::class, $method
                ));
            }

            throw new \LogicException(sprintf(
                '%s::%s must return a relationship instance.', static::class, $method
            ));
        }

        return tap($relation->getResults(), function ($results) use ($method) {
            $this->setRelation($method, $results);
        });
    }

    /**
     * Get a relationship.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getRelation(string $key)
    {
        return $this->relations[$key] ?? null;
    }

    /**
     * Set the given relationship on the model.
     *
     * @param  string  $relation
     * @param  mixed  $value
     * @return $this
     */
    public function setRelation(string $relation, $value): self
    {
        $this->relations[$relation] = $value;

        return $this;
    }

    /**
     * Unset a loaded relationship.
     *
     * @param  string  $relation
     * @return $this
     */
    public function unsetRelation(string $relation): self
    {
        unset($this->relations[$relation]);

        return $this;
    }

    /**
     * Get all the loaded relations for the instance.
     *
     * @return array
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Set the entire relations array on the model.
     *
     * @param  array  $relations
     * @return $this
     */
    public function setRelations(array $relations): self
    {
        $this->relations = $relations;

        return $this;
    }

    /**
     * Determine if the given relation is loaded.
     *
     * @param  string  $key
     * @return bool
     */
    public function relationLoaded(string $key): bool
    {
        return array_key_exists($key, $this->relations);
    }

    /**
     * Load a set of relationships onto the model.
     *
     * @param  array|string  $relations
     * @return $this
     */
    public function load($relations): self
    {
        $query = $this->newQueryWithoutRelationships()->with(
            is_string($relations) ? func_get_args() : $relations
        );

        $query->eagerLoadRelations([$this]);

        return $this;
    }

    /**
     * Update the model in the database.
     */
    public function update(array $attributes = []): bool
    {
        if (! $this->exists) {
            return false;
        }

        return $this->fill($attributes)->save();
    }

    /**
     * Increment a column's value by a given amount.
     */
    public function increment(string $column, $amount = 1, array $extra = []): int
    {
        return $this->incrementOrDecrement($column, $amount, $extra, 'increment');
    }

    /**
     * Decrement a column's value by a given amount.
     */
    public function decrement(string $column, $amount = 1, array $extra = []): int
    {
        return $this->incrementOrDecrement($column, $amount, $extra, 'decrement');
    }

    /**
     * Run the increment or decrement method on the model.
     */
    protected function incrementOrDecrement(string $column, $amount, array $extra, string $method): int
    {
        $query = $this->newQuery();

        if (! $this->exists) {
            return $query->{$method}($column, $amount, $extra);
        }

        $this->setKeysForSaveQuery($query);

        $result = $query->{$method}($column, $amount, $extra);

        // Update the model's attributes to reflect the change
        if ($method === 'increment') {
            $this->attributes[$column] = ($this->attributes[$column] ?? 0) + $amount;
        } else {
            $this->attributes[$column] = ($this->attributes[$column] ?? 0) - $amount;
        }

        // Update any extra attributes
        foreach ($extra as $key => $value) {
            $this->attributes[$key] = $value;
        }

        return $result;
    }

    /**
     * Refresh the model from the database.
     */
    public function refresh(): static
    {
        if (! $this->exists) {
            return $this;
        }

        $this->setRawAttributes(
            static::find($this->getKey())->attributes
        );

        return $this;
    }

    /**
     * Determine if the model and all the given attribute(s) have remained the same.
     */
    public function isClean($attributes = null): bool
    {
        return ! $this->isDirty(...func_get_args());
    }

    /**
     * The event map for the model.
     *
     * @var array
     */
    protected static $events = [];

    /**
     * Register a model event with the dispatcher.
     *
     * @param  string  $event
     * @param  callable  $callback
     * @return void
     */
    public static function registerModelEvent($event, $callback)
    {
        $class = static::class;
        if (!isset(static::$events[$class])) {
            static::$events[$class] = [];
        }
        if (!isset(static::$events[$class][$event])) {
            static::$events[$class][$event] = [];
        }
        static::$events[$class][$event][] = $callback;
    }

    /**
     * Fire the given event for the model.
     *
     * @param  string  $event
     * @param  bool  $halt
     * @return mixed
     */
    protected function fireModelEvent($event, $halt = true)
    {
        if (!isset(static::$events[static::class][$event])) {
            return true;
        }

        $result = true;
        foreach (static::$events[static::class][$event] as $callback) {
            $response = call_user_func($callback, $this);
            if ($halt && $response === false) {
                $result = false;
                break;
            }
        }

        return $result;
    }

    /**
     * Remove all of the event listeners for the model.
     *
     * @return void
     */
    public static function flushEventListeners()
    {
        unset(static::$events[static::class]);
    }

}

// Helper functions
if (!function_exists('snake_case')) {
    function snake_case(string $value): string
    {
        $value = preg_replace('/\s+/u', '', $value);
        $value = preg_replace('/(.)(?=[A-Z])/u', '$1_', $value);
        return strtolower($value);
    }
}

if (!function_exists('class_basename')) {
    function class_basename($class): string
    {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }
}

if (!function_exists('array_wrap')) {
    function array_wrap($value): array
    {
        if (is_null($value)) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }
}

if (!function_exists('data_get')) {
    function data_get($target, $key, $default = null)
    {
        if (is_null($key)) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        foreach ($key as $i => $segment) {
            unset($key[$i]);

            if (is_null($segment)) {
                return $target;
            }

            if ($segment === '*') {
                if (! is_array($target)) {
                    return value($default);
                }

                $result = [];

                foreach ($target as $item) {
                    $result[] = data_get($item, $key);
                }

                return in_array('*', $key) ? array_collapse($result) : $result;
            }

            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return value($default);
            }
        }

        return $target;
    }
}

if (!function_exists('value')) {
    function value($value)
    {
        return $value instanceof \Closure ? $value() : $value;
    }
}

if (!function_exists('class_uses_recursive')) {
    function class_uses_recursive($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        $results = [];

        foreach (array_reverse(class_parents($class)) + [$class => $class] as $class) {
            $results += trait_uses_recursive($class);
        }

        return array_unique($results);
    }
}

if (!function_exists('trait_uses_recursive')) {
    function trait_uses_recursive($trait)
    {
        $traits = class_uses($trait);

        foreach ($traits as $trait) {
            $traits += trait_uses_recursive($trait);
        }

        return $traits;
    }
}

