<?php

namespace Arpon\Database\Tests\Feature\Connection;

use Arpon\Database\Tests\TestCase;
use Exception;

abstract class BaseConnectionTest extends TestCase
{
    // Common connection test setup/teardown or helper methods can go here
    // Currently, TestCase already provides most common setup.

    // Helper to convert array results to objects for easier testing
    protected function toObject($data) {
        if (is_array($data)) {
            return (object)$data;
        }
        return $data;
    }

    /**
     * Override migration to use specific syntax for these tests if needed.
     * But for now, we rely on parent::migrateTestDatabase().
     */
    protected function migrateTestDatabase(): void
    {
        parent::migrateTestDatabase();
        // Add any connection-specific schema setup here if necessary
    }
}