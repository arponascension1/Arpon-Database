<?php

namespace Arpon\Database\Eloquent\Scopes;

use Arpon\Database\Eloquent\EloquentBuilder;
use Arpon\Database\Eloquent\Model;

/**
 * Global scope to filter soft deleted records
 */
class SoftDeleteScope implements Scope
{
    /**
     * All of the extensions to be added to the builder.
     */
    protected array $extensions = ['Restore', 'RestoreOrCreate', 'CreateOrRestore', 'WithTrashed', 'WithoutTrashed', 'OnlyTrashed'];

    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(EloquentBuilder $builder, Model $model): void
    {
        $builder->whereNull($model->getQualifiedDeletedAtColumn());
    }

    /**
     * Extend the query builder with the needed functions.
     */
    public function extend(EloquentBuilder $builder): void
    {
        foreach ($this->extensions as $extension) {
            $this->{"add{$extension}"}($builder);
        }

        $builder->onDelete(function (EloquentBuilder $builder) {
            $column = $this->getDeletedAtColumn($builder);

            return $builder->update([
                $column => $builder->getModel()->freshTimestampString(),
            ]);
        });
    }

    /**
     * Get the "deleted at" column for the builder.
     */
    protected function getDeletedAtColumn(EloquentBuilder $builder): string
    {
        if (count((array) $builder->getQuery()->joins) > 0) {
            return $builder->getModel()->getQualifiedDeletedAtColumn();
        }

        return $builder->getModel()->getDeletedAtColumn();
    }

    /**
     * Add the restore extension to the builder.
     */
    protected function addRestore(EloquentBuilder $builder): void
    {
        $builder->macro('restore', function (EloquentBuilder $builder) {
            $builder->withTrashed();

            return $builder->update([$builder->getModel()->getDeletedAtColumn() => null]);
        });
    }

    /**
     * Add the restore-or-create extension to the builder.
     */
    protected function addRestoreOrCreate(EloquentBuilder $builder): void
    {
        $builder->macro('restoreOrCreate', function (EloquentBuilder $builder, array $attributes = [], array $values = []) {
            $builder->withTrashed();

            return tap($builder->firstOrCreate($attributes, $values), function ($instance) {
                $instance->restore();
            });
        });
    }

    /**
     * Add the create-or-restore extension to the builder.
     */
    protected function addCreateOrRestore(EloquentBuilder $builder): void
    {
        $builder->macro('createOrRestore', function (EloquentBuilder $builder, array $attributes = [], array $values = []) {
            return $builder->restoreOrCreate($attributes, $values);
        });
    }

    /**
     * Add the with-trashed extension to the builder.
     */
    protected function addWithTrashed(EloquentBuilder $builder): void
    {
        $builder->macro('withTrashed', function (EloquentBuilder $builder, bool $withTrashed = true) {
            if (!$withTrashed) {
                return $builder->withoutTrashed();
            }

            return $builder->withoutGlobalScope(SoftDeleteScope::class);
        });
    }

    /**
     * Add the without-trashed extension to the builder.
     */
    protected function addWithoutTrashed(EloquentBuilder $builder): void
    {
        $builder->macro('withoutTrashed', function (EloquentBuilder $builder) {
            $model = $builder->getModel();

            $builder->withoutGlobalScope($this)->whereNull(
                $model->getQualifiedDeletedAtColumn()
            );

            return $builder;
        });
    }

    /**
     * Add the only-trashed extension to the builder.
     */
    protected function addOnlyTrashed(EloquentBuilder $builder): void
    {
        $builder->macro('onlyTrashed', function (EloquentBuilder $builder) {
            $model = $builder->getModel();

            $builder->withoutGlobalScope(SoftDeleteScope::class)->whereNotNull(
                $model->getQualifiedDeletedAtColumn()
            );

            return $builder;
        });
    }
}