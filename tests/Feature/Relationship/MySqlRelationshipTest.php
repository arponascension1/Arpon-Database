<?php

namespace Arpon\Database\Tests\Feature\Relationship;

use Exception;

class MySqlRelationshipTest extends BaseRelationshipTest
{
    protected string $connection = 'mysql';

    public function setUp(): void
    {
        try {
            parent::setUp();
        } catch (Exception $e) {
            $this->markTestSkipped('MySQL connection not available for Relationship tests: ' . $e->getMessage());
        }
    }
}
