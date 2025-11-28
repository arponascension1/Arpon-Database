<?php

namespace Arpon\Database\Eloquent\Concerns;

use Arpon\Database\Eloquent\Scopes\SoftDeleteScope;

/**
 * Trait for implementing soft delete functionality
 */
trait SoftDeletes
{
    /**
     * Indicates if the model is currently force deleting.
     */
    protected bool $forceDeleting = false;

    /**
     * Boot the soft deleting trait for a model.
     */
    public static function bootSoftDeletes(): void
    {
        static::addGlobalScope(new SoftDeleteScope());
    }

    /**
     * Initialize the soft deletes trait for an instance.
     */
    public function initializeSoftDeletes(): void
    {
        if (!isset($this->casts[$this->getDeletedAtColumn()])) {
            $this->casts[$this->getDeletedAtColumn()] = 'datetime';
        }
    }

    /**
     * Force a hard delete on a soft deleted model.
     */
    public function forceDelete(): bool
    {
        $this->forceDeleting = true;

        return tap($this->delete(), function ($deleted) {
            $this->forceDeleting = false;

            if ($deleted) {
                $this->exists = false;
            }
        });
    }

    /**
     * Perform the actual delete query on this model instance.
     */
    protected function performDeleteOnModel(): void
    {
        if ($this->forceDeleting) {
            $this->exists = false;
            return;
        }

        $this->{$this->getDeletedAtColumn()} = $this->freshTimestamp();
        $this->save();
    }

    /**
     * Restore a soft-deleted model instance.
     */
    public function restore(): bool
    {
        // If the restoring event returns false, cancel the restore operation
        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }

        $this->{$this->getDeletedAtColumn()} = null;

        // Once we have saved the model, we need to fire the "restored" event so
        // developers can hook into post-restore operations. We will return the
        // result of the save operation so that callers can act on its result.
        $this->exists = true;

        $result = $this->save();

        $this->fireModelEvent('restored', false);

        return $result;
    }

    /**
     * Determine if the model instance has been soft-deleted.
     */
    public function trashed(): bool
    {
        return !is_null($this->{$this->getDeletedAtColumn()});
    }

    /**
     * Register a "restoring" model event callback with the dispatcher.
     */
    public static function restoring(callable $callback): void
    {
        static::registerModelEvent('restoring', $callback);
    }

    /**
     * Register a "restored" model event callback with the dispatcher.
     */
    public static function restored(callable $callback): void
    {
        static::registerModelEvent('restored', $callback);
    }

    /**
     * Determine if the model is currently force deleting.
     */
    public function isForceDeleting(): bool
    {
        return $this->forceDeleting;
    }

    /**
     * Get the name of the "deleted at" column.
     */
    public function getDeletedAtColumn(): string
    {
        return defined(static::class . '::DELETED_AT') ? static::DELETED_AT : 'deleted_at';
    }

    /**
     * Get the fully qualified "deleted at" column.
     */
    public function getQualifiedDeletedAtColumn(): string
    {
        return $this->qualifyColumn($this->getDeletedAtColumn());
    }
}