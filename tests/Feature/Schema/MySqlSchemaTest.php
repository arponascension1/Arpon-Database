<?php

namespace Arpon\Database\Tests\Feature\Schema;

use Exception;

class MySqlSchemaTest extends BaseSchemaTest
{
    protected string $connection = 'mysql';

    public function setUp(): void
    {
        try {
            parent::setUp();
        } catch (Exception $e) {
            $this->markTestSkipped('MySQL connection not available for Schema tests: ' . $e->getMessage());
        }
    }
}
