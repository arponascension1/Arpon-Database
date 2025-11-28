<?php

namespace Arpon\Database\Tests\Feature\Connection; // Changed namespace

use Arpon\Database\Query\Expression;
use Exception;

/**
 * MySQL Database Integration Tests
 *
 * Converted from test-mysql.php to professional PHPUnit format
 * Note: These tests require MySQL to be available and configured
 */
class MySqlConnectionTest extends BaseConnectionTest // Changed parent class
{
    protected string $connection = 'mysql';

    public function setUp(): void
    {
        try {
            parent::setUp();
        } catch (Exception $e) {
            $this->markTestSkipped('MySQL connection not available: ' . $e->getMessage());
        }
    }

    /**
     * @test
     */
    public function it_establishes_mysql_connection()
    {
        $connection = $this->getConnection();
        $this->assertNotNull($connection);

        // Test basic query
        $result = $connection->select('SELECT 1 as test');
        $this->assertEquals(1, $result[0]->test);
    }

    /**
     * @test
     */
    public function it_handles_mysql_specific_features()
    {
        $connection = $this->getConnection();

        // Test MySQL version
        $version = $connection->select('SELECT VERSION() as version');
        $this->assertNotEmpty($version[0]->version);

        // Test AUTO_INCREMENT functionality
        $userId1 = $connection->table('users')->insertGetId(['name' => 'MySQL User 1', 'email' => 'mysql1@example.com']);
        $userId2 = $connection->table('users')->insertGetId(['name' => 'MySQL User 2', 'email' => 'mysql2@example.com']);

        $this->assertIsInt($userId1);
        $this->assertIsInt($userId2);
        $this->assertGreaterThan($userId1, $userId2);
    }

    /**
     * @test
     */
    public function it_handles_mysql_charset_and_collation()
    {
        $connection = $this->getConnection();

        // Test UTF-8 support
        $connection->table('users')->insert([
            'name' => 'Unicode Test 测试 🚀',
            'email' => 'unicode@example.com'
        ]);

        $user = $connection->table('users')->where('email', 'unicode@example.com')->first();
        $this->assertEquals('Unicode Test 测试 🚀', $user->name);
    }

    /**
     * @test
     */
    public function it_handles_mysql_data_types()
    {
        $connection = $this->getConnection();

        // Test various MySQL data types
        $connection->statement('
            CREATE TEMPORARY TABLE mysql_types_test (
                id INT AUTO_INCREMENT PRIMARY KEY,
                text_col TEXT,
                json_col JSON,
                datetime_col DATETIME,
                decimal_col DECIMAL(10,2),
                boolean_col BOOLEAN
            )
        ');

        $testData = [
            'text_col' => 'Sample text',
            'json_col' => '{"key": "value", "number": 123}',
            'datetime_col' => '2023-01-01 12:30:45',
            'decimal_col' => 123.45,
            'boolean_col' => true
        ];

        $connection->table('mysql_types_test')->insert($testData);

        $result = $connection->table('mysql_types_test')->first();

        $this->assertEquals($testData['text_col'], $result->text_col);
        $this->assertIsString($result->json_col);
        $this->assertEquals($testData['datetime_col'], $result->datetime_col);
        $this->assertEquals($testData['decimal_col'], (float)$result->decimal_col);
    }

    /**
     * @test
     */
    public function it_handles_mysql_functions()
    {
        $connection = $this->getConnection();

        // Test MySQL string functions
        $result = $connection->select("SELECT CONCAT('Hello', ' ', 'World') as greeting");
        $this->assertEquals('Hello World', $result[0]->greeting);

        // Test MySQL date functions
        $result = $connection->select("SELECT NOW() as now_time, CURDATE() as cur_date"); // Changed aliases
        $this->assertNotEmpty($result[0]->now_time);
        $this->assertNotEmpty($result[0]->cur_date);

        // Test MySQL math functions
        $result = $connection->select("SELECT ROUND(3.14159, 2) as rounded");
        $this->assertEquals(3.14, $result[0]->rounded);
    }

    /**
     * @test
     */
    public function it_handles_mysql_advanced_queries()
    {
        $connection = $this->getConnection();

        // Insert test data
        $connection->table('users')->insert([
            ['name' => 'Alice MySQL', 'email' => 'alice.mysql@example.com', 'age' => 25],
            ['name' => 'Bob MySQL', 'email' => 'bob.mysql@example.com', 'age' => 30],
            ['name' => 'Charlie MySQL', 'email' => 'charlie.mysql@example.com', 'age' => 35],
        ]);

        // Test LIMIT and OFFSET
        $users = $connection->table('users')
            ->where('name', 'like', '%MySQL%')
            ->orderBy('age')
            ->limit(2)
            ->offset(1)
            ->get();

        $this->assertLessThanOrEqual(2, count($users));

        // Test complex WHERE conditions
        $users = $connection->table('users')
            ->where('name', 'like', '%MySQL%')
            ->where(function($query) {
                $query->where('age', '>', 25)->orWhere('age', '<', 30);
            })
            ->get();

        $this->assertGreaterThan(0, count($users));
    }

    /**
     * @test
     */
    public function it_handles_mysql_transactions()
    {
        $connection = $this->getConnection();

        // Test transaction with rollback
        $initialCount = $connection->table('users')->count();

        $connection->beginTransaction();
        try {
            $connection->table('users')->insert(['name' => 'Transaction Test', 'email' => 'trans@example.com']);

            // Simulate an error condition
            throw new Exception('Simulated error');

            $connection->commit();
        } catch (Exception $e) {
            $connection->rollback();
        }

        $finalCount = $connection->table('users')->count();
        $this->assertEquals($initialCount, $finalCount);

        // Test successful transaction
        $connection->beginTransaction();
        try {
            $connection->table('users')->insert(['name' => 'Success Transaction', 'email' => 'success@example.com']);
            $connection->commit();

            $user = $connection->table('users')->where('email', 'success@example.com')->first();
            $this->assertNotNull($user);
            $this->assertEquals('Success Transaction', $user->name);
        } catch (Exception $e) {
            $connection->rollback();
            $this->fail('Transaction should succeed');
        }
    }

    /**
     * @test
     */
    public function it_handles_mysql_indexing()
    {
        $connection = $this->getConnection();

        // Create a temporary table with indexes
        $connection->statement('
            CREATE TEMPORARY TABLE indexed_table (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255),
                email VARCHAR(255),
                age INT,
                INDEX idx_name (name),
                INDEX idx_age (age),
                UNIQUE INDEX idx_email (email)
            )
        ');

        // Insert test data
        $connection->table('indexed_table')->insert([
            ['name' => 'Index Test 1', 'email' => 'index1@example.com', 'age' => 25],
            ['name' => 'Index Test 2', 'email' => 'index2@example.com', 'age' => 30],
        ]);

        // Test queries that should use indexes
        $users = $connection->table('indexed_table')->where('name', 'Index Test 1')->get();
        $this->assertEquals(1, count($users));

        $users = $connection->table('indexed_table')->where('age', '>', 20)->get();
        $this->assertEquals(2, count($users));

        // Test unique constraint
        $this->expectException(Exception::class);
        $connection->table('indexed_table')->insert(['name' => 'Duplicate', 'email' => 'index1@example.com', 'age' => 40]);
    }

    /**
     * @test
     */
    public function it_handles_mysql_joins_and_relationships()
    {
        $connection = $this->getConnection();

        // Create test data with relationships
        $userId1 = $connection->table('users')->insertGetId(['name' => 'Join Author 1', 'email' => 'joinauthor1@example.com']);
        $userId2 = $connection->table('users')->insertGetId(['name' => 'Join Author 2', 'email' => 'joinauthor2@example.com']);

        $connection->table('posts')->insert([
            ['title' => 'MySQL Post 1', 'content' => 'MySQL Content 1', 'user_id' => $userId1],
            ['title' => 'MySQL Post 2', 'content' => 'MySQL Content 2', 'user_id' => $userId1],
            ['title' => 'MySQL Post 3', 'content' => 'MySQL Content 3', 'user_id' => $userId2],
        ]);

        // Test complex joins
        $results = $connection->table('users')
            ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
            ->select('users.name', $connection->raw('COUNT(posts.id) as post_count'))
            ->where('users.name', 'like', 'Join Author%')
            ->groupBy('users.id', 'users.name')
            ->having('post_count', '>', 0)
            ->get();

        $this->assertGreaterThan(0, count($results));

        foreach ($results as $result) {
            $this->assertGreaterThan(0, $result->post_count);
        }
    }

    /**
     * @test
     */
    public function it_handles_mysql_performance_features()
    {
        $connection = $this->getConnection();

        // Test EXPLAIN query (MySQL specific)
        try {
            $explain = $connection->select('EXPLAIN SELECT * FROM users WHERE name = ?', ['test']);
            $this->assertIsArray($explain);
        } catch (Exception $e) {
            // EXPLAIN might not work in all MySQL configurations
            $this->markTestSkipped('EXPLAIN queries not supported in this MySQL configuration');
        }

        // Test query caching behavior
        $start = microtime(true);
        $users1 = $connection->table('users')->get();
        $time1 = microtime(true) - $start;

        $start = microtime(true);
        $users2 = $connection->table('users')->get();
        $time2 = microtime(true) - $start;

        // Just verify both queries return same data
        $this->assertEquals(count($users1), count($users2));
    }
}