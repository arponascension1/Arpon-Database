<?php

namespace Arpon\Database;

use Arpon\Database\Query\Grammars\MySqlGrammar as QueryGrammar;
use Arpon\Database\Query\Processors\MySqlProcessor as QueryProcessor;
use Arpon\Database\Schema\Grammars\MySqlGrammar as SchemaGrammar;
use Arpon\Database\Schema\Builder;

class MySqlConnection extends Connection
{
    /**
     * Get the default query grammar instance.
     *
     * @return Grammar
     */
    protected function getDefaultQueryGrammar(): Grammar
    {
        return $this->withTablePrefix(new QueryGrammar);
    }

    /**
     * Get a schema builder instance for the connection.
     *
     * @return Builder
     */
    public function getSchemaBuilder(): Builder
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new Builder($this);
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return Grammar
     */
    protected function getDefaultSchemaGrammar(): Grammar
    {
        return $this->withTablePrefix(new SchemaGrammar);
    }

    /**
     * Get the default post processor instance.
     *
     * @return QueryProcessor
     */
    protected function getDefaultPostProcessor(): QueryProcessor
    {
        return new QueryProcessor;
    }

    /**
     * Get the Doctrine DBAL driver.
     *
     * @return DoctrineDriver
     */
    protected function getDoctrineDriver()
    {
        return new DoctrineDriver;
    }
}