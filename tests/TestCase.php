<?php

namespace Arpon\Database\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Arpon\Database\DatabaseManager;
use Arpon\Database\Connectors\ConnectionFactory;
use Arpon\Database\Eloquent\Model;

/**
 * Base test class for all database tests
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Database manager instance
     */
    protected DatabaseManager $db;

    /**
     * Database connection name
     */
    protected string $connection = 'sqlite';

    /**
     * Setup database for each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupDatabase();
        $this->migrateTestDatabase();
    }

    /**
     * Clean up after each test
     */
    protected function tearDown(): void
    {
        $this->cleanupDatabase();
        parent::tearDown();
    }

    /**
     * Setup database manager and connection
     */
    protected function setupDatabase(): void
    {
        $config = [
            // Ensure PDO returns objects (->property) instead of associative arrays
            'database.fetch' => \PDO::FETCH_OBJ,
            'default' => $this->connection,
            'connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                    'prefix' => '',
                ],
                'mysql' => [
                    'driver' => 'mysql',
                    'host' => 'localhost',
                    'port' => '3306',
                    'database' => 'db_tool_test',
                    'username' => 'valet',
                    'password' => '',
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => '',
                ],
            ],
        ];

        $factory = new ConnectionFactory();
        $this->db = new DatabaseManager($config, $factory);
        Model::setConnectionResolver($this->db);
    }

    /**
     * Create test database tables
     */
    protected function migrateTestDatabase(): void
    {
        $connection = $this->db->connection($this->connection);
        $isMySQL = $this->connection === 'mysql';

        if ($isMySQL) {
            // Disable foreign key checks for MySQL
            $connection->statement('SET FOREIGN_KEY_CHECKS = 0');
            
            // Create database if not exists
            $connection->statement('CREATE DATABASE IF NOT EXISTS test_db');
            $connection->statement('USE test_db');
        }

        // Drop tables in reverse order (children first)
        $connection->statement('DROP TABLE IF EXISTS comments');
        $connection->statement('DROP TABLE IF EXISTS profiles');
        $connection->statement('DROP TABLE IF EXISTS posts');
        $connection->statement('DROP TABLE IF EXISTS users');

        if ($isMySQL) {
            // Create users table for MySQL
            $connection->statement('
                CREATE TABLE users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    age INT,
                    settings JSON,
                    is_active BOOLEAN DEFAULT 1,
                    created_at TIMESTAMP NULL DEFAULT NULL,
                    updated_at TIMESTAMP NULL DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');

            // Create posts table for MySQL
            $connection->statement('
                CREATE TABLE posts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    content TEXT,
                    user_id INT,
                    published BOOLEAN DEFAULT 0,
                    published_at TIMESTAMP NULL DEFAULT NULL,
                    created_at TIMESTAMP NULL DEFAULT NULL,
                    updated_at TIMESTAMP NULL DEFAULT NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');

            // Create profiles table for MySQL
            $connection->statement('
                CREATE TABLE profiles (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNIQUE,
                    bio TEXT,
                    website VARCHAR(255),
                    created_at TIMESTAMP NULL DEFAULT NULL,
                    updated_at TIMESTAMP NULL DEFAULT NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');

            // Create comments table for MySQL
            $connection->statement('
                CREATE TABLE comments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    post_id INT,
                    content TEXT,
                    created_at TIMESTAMP NULL DEFAULT NULL,
                    updated_at TIMESTAMP NULL DEFAULT NULL,
                    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');

            // Re-enable foreign key checks for MySQL
            $connection->statement('SET FOREIGN_KEY_CHECKS = 1');
        } else {
            // Create tables for SQLite
            $connection->statement('
                CREATE TABLE users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    age INTEGER,
                    settings TEXT,
                    is_active BOOLEAN DEFAULT 1,
                    created_at DATETIME,
                    updated_at DATETIME
                )
            ');

            $connection->statement('
                CREATE TABLE posts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title VARCHAR(255) NOT NULL,
                    content TEXT,
                    user_id INTEGER,
                    published BOOLEAN DEFAULT 0,
                    published_at DATETIME,
                    created_at DATETIME,
                    updated_at DATETIME,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )
            ');

            // Create profiles table for SQLite
            $connection->statement('
                CREATE TABLE profiles (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER UNIQUE,
                    bio TEXT,
                    website VARCHAR(255),
                    created_at DATETIME,
                    updated_at DATETIME,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )
            ');

            // Create comments table for SQLite
            $connection->statement('
                CREATE TABLE comments (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    post_id INTEGER,
                    content TEXT,
                    created_at DATETIME,
                    updated_at DATETIME,
                    FOREIGN KEY (post_id) REFERENCES posts(id)
                )
            ');
        }
    }

    /**
     * Clean up database after test
     */
    protected function cleanupDatabase(): void
    {
        if (isset($this->db)) {
            $connection = $this->db->connection($this->connection);

            // If using MySQL, disable foreign key checks while dropping tables
            if ($connection->getDriverName() === 'mysql') {
                $connection->statement('SET FOREIGN_KEY_CHECKS = 0');
            }

            // Drop child tables first to avoid FK constraint violations
            $connection->statement('DROP TABLE IF EXISTS comments');
            $connection->statement('DROP TABLE IF EXISTS posts');
            $connection->statement('DROP TABLE IF EXISTS profiles');
            $connection->statement('DROP TABLE IF EXISTS users');
            $connection->statement('DROP TABLE IF EXISTS categories');
            $connection->statement('DROP TABLE IF EXISTS tags');

            if ($connection->getDriverName() === 'mysql') {
                $connection->statement('SET FOREIGN_KEY_CHECKS = 1');
            }
        }
    }

    /**
     * Get database connection
     */
    protected function getConnection()
    {
        return $this->db->connection($this->connection);
    }

    /**
     * Create sample test data
     */
    protected function createTestData(): array
    {
        $connection = $this->getConnection();

        // Insert users
        $connection->table('users')->insert([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
            'settings' => '{"theme":"dark","notifications":true}',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $connection->table('users')->insert([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'age' => 25,
            'settings' => '{"theme":"light","notifications":false}',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Get user IDs
        $users = $connection->table('users')->get();
        
        // Insert posts
        $connection->table('posts')->insert([
            'title' => 'First Post',
            'content' => 'This is the first post content.',
            'user_id' => $users[0]->id,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $connection->table('posts')->insert([
            'title' => 'Second Post',
            'content' => 'This is the second post content.',
            'user_id' => $users[0]->id,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $connection->table('posts')->insert([
            'title' => 'Third Post',
            'content' => 'This is the third post content.',
            'user_id' => $users[1]->id,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'users' => $users,
            'posts' => $connection->table('posts')->get()
        ];
    }

    /**
     * Assert that a database table has records
     */
    protected function assertDatabaseHas(string $table, array $data): void
    {
        $connection = $this->getConnection();
        $query = $connection->table($table);

        foreach ($data as $key => $value) {
            $query->where($key, $value);
        }

        $this->assertGreaterThan(
            0,
            $query->count(),
            "Failed asserting that table [{$table}] contains matching record."
        );
    }

    /**
     * Assert that a database table does not have records
     */
    protected function assertDatabaseMissing(string $table, array $data): void
    {
        $connection = $this->getConnection();
        $query = $connection->table($table);

        foreach ($data as $key => $value) {
            $query->where($key, $value);
        }

        $this->assertEquals(
            0,
            $query->count(),
            "Failed asserting that table [{$table}] does not contain matching record."
        );
    }

    /**
     * Assert that a database table has a specific count of records
     */
    protected function assertDatabaseCount(string $table, int $count): void
    {
        $connection = $this->getConnection();
        $actual = $connection->table($table)->count();

        $this->assertEquals(
            $count,
            $actual,
            "Failed asserting that table [{$table}] has [{$count}] records. Found [{$actual}]."
        );
    }

    /**
     * Convert array to object (for fixing query result format issues)
     */
    protected function arrayToObject($array)
    {
        if (is_array($array)) {
            return (object) $array;
        }
        return $array;
    }

    /**
     * Assert string contains (for PHPUnit compatibility)
     */
    protected function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertStringContainsString($needle, $haystack, $message);
    }
}