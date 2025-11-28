<?php

namespace Arpon\Database\Eloquent;

use Exception;

class ModelNotFoundException extends Exception
{
    /**
     * The name of the affected Eloquent model.
     */
    protected string $model;

    /**
     * The affected model IDs.
     */
    protected array $ids;

    /**
     * Set the affected Eloquent model and instance ids.
     */
    public function setModel(string $model, $ids = []): static
    {
        $this->model = $model;
        $this->ids = array_wrap($ids);

        $this->message = "No query results for model [{$model}]";

        if (count($this->ids) > 0) {
            $this->message .= ' ' . implode(', ', $this->ids);
        } else {
            $this->message .= '.';
        }

        return $this;
    }

    /**
     * Get the affected Eloquent model.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get the affected Eloquent model IDs.
     */
    public function getIds(): array
    {
        return $this->ids;
    }
}