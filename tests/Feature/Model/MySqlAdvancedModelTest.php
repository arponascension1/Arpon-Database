<?php

namespace Arpon\Database\Tests\Feature\Model;

use Exception;

class MySqlAdvancedModelTest extends BaseAdvancedModelTest
{
    protected string $connection = 'mysql';

    public function setUp(): void
    {
        try {
            parent::setUp();
        } catch (Exception $e) {
            $this->markTestSkipped('MySQL connection not available for Advanced Model tests: ' . $e->getMessage());
        }
    }
}
