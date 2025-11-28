<?php

namespace Arpon\Database;

use Arpon\Database\Query\Grammars\SqliteGrammar as QueryGrammar;
use Arpon\Database\Query\Processors\SqliteProcessor as QueryProcessor;
use Arpon\Database\Schema\Grammars\SqliteGrammar as SchemaGrammar;
use Arpon\Database\Schema\Builder;

class SQLiteConnection extends Connection
{
    /**
     * Get the default query grammar instance.
     *
     * @return \Arpon\Database\Query\Grammars\SqliteGrammar
     */
    protected function getDefaultQueryGrammar()
    {
        return $this->withTablePrefix(new QueryGrammar);
    }

    /**
     * Get a schema builder instance for the connection.
     *
     * @return \Arpon\Database\Schema\Builder
     */
    public function getSchemaBuilder()
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new Builder($this);
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return \Arpon\Database\Schema\Grammars\SqliteGrammar
     */
    protected function getDefaultSchemaGrammar()
    {
        return $this->withTablePrefix(new SchemaGrammar);
    }

    /**
     * Get the default post processor instance.
     *
     * @return \Arpon\Database\Query\Processors\SqliteProcessor
     */
    protected function getDefaultPostProcessor()
    {
        return new QueryProcessor;
    }

    /**
     * Get the Doctrine DBAL driver.
     *
     * @return \Doctrine\DBAL\Driver\PDOSqlite\Driver
     */
    protected function getDoctrineDriver()
    {
        return new DoctrineDriver;
    }
}