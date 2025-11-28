<?php

namespace Arpon\Database\Tests\Feature\Connection; // Corrected namespace

use Arpon\Database\Query\Expression;
use Exception;

/**
 * Comprehensive SQLite Database Tests
 *
 * Converted from test-sqlite.php to professional PHPUnit format
 */
class SqliteConnectionTest extends BaseConnectionTest // Corrected parent class
{
    protected string $connection = 'sqlite';

    /**
     * @test
     */
    public function it_establishes_sqlite_connection()
    {
        $connection = $this->getConnection();
        $this->assertNotNull($connection);

        // Test basic query
        $result = $connection->select('SELECT 1 as test');
        $first = $this->toObject($result[0]);
        $this->assertEquals(1, $first->test);
    }

    /**
     * @test
     */
    public function it_creates_and_drops_tables()
    {
        $connection = $this->getConnection();

        // Create test table
        $connection->statement('
            CREATE TABLE test_table (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                value INTEGER
            )
        ');

        // Verify table exists by inserting data
        $connection->insert('INSERT INTO test_table (name, value) VALUES (?, ?)', ['test', 123]);
        $result = $connection->select('SELECT * FROM test_table WHERE name = ?', ['test']);

        $this->assertEquals(1, count($result));
        $first = $this->toObject($result[0]);
        $this->assertEquals('test', $first->name);
        $this->assertEquals(123, $first->value);

        // Drop table
        $connection->statement('DROP TABLE test_table');

        // Verify table is dropped
        $this->expectException(Exception::class);
        $connection->select('SELECT * FROM test_table');
    }

    /**
     * @test
     */
    public function it_handles_crud_operations()
    {
        $connection = $this->getConnection();

        // Create - Insert data
        $insertResult = $connection->insert('INSERT INTO users (name, email) VALUES (?, ?)', ['John Doe', 'john@example.com']);
        $this->assertTrue($insertResult);

        // Read - Select data
        $users = $connection->select('SELECT * FROM users WHERE name = ?', ['John Doe']);
        $this->assertEquals(1, count($users));
        $user = $this->toObject($users[0]);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);

        $userId = $user->id;

        // Update - Modify data
        $updateResult = $connection->update('UPDATE users SET email = ? WHERE id = ?', ['updated@example.com', $userId]);
        $this->assertEquals(1, $updateResult);

        // Verify update
        $updatedUsers = $connection->select('SELECT * FROM users WHERE id = ?', [$userId]);
        $updatedUser = $this->toObject($updatedUsers[0]);
        $this->assertEquals('updated@example.com', $updatedUser->email);

        // Delete - Remove data
        $deleteResult = $connection->delete('DELETE FROM users WHERE id = ?', [$userId]);
        $this->assertEquals(1, $deleteResult);

        // Verify deletion
        $deletedUsers = $connection->select('SELECT * FROM users WHERE id = ?', [$userId]);
        $this->assertEquals(0, count($deletedUsers));
    }

    /**
     * @test
     */
    public function it_handles_query_builder_operations()
    {
        $connection = $this->getConnection();

        // Insert test data using query builder
        $connection->table('users')->insert([
            ['name' => 'Alice', 'email' => 'alice@example.com', 'age' => 25],
            ['name' => 'Bob', 'email' => 'bob@example.com', 'age' => 30],
            ['name' => 'Charlie', 'email' => 'charlie@example.com', 'age' => 35],
        ]);

        // Test basic select
        $users = $connection->table('users')->get();
        $this->assertGreaterThanOrEqual(3, count($users));

        // Test where clause
        $alice = $connection->table('users')->where('name', 'Alice')->first();
        $alice = $this->toObject($alice);
        $this->assertNotNull($alice);
        $this->assertEquals('alice@example.com', $alice->email);

        // Test multiple where conditions
        $youngUsers = $connection->table('users')->where('age', '<', 30)->get();
        $this->assertGreaterThan(0, count($youngUsers));

        // Test orderBy
        $orderedUsers = $connection->table('users')->orderBy('age', 'desc')->get();
        $this->assertGreaterThanOrEqual(3, count($orderedUsers));

        // Test limit and offset
        $limitedUsers = $connection->table('users')->limit(2)->get();
        $this->assertEquals(2, count($limitedUsers));

        $offsetUsers = $connection->table('users')->offset(1)->limit(2)->get();
        $this->assertLessThanOrEqual(2, count($offsetUsers));

        // Test count
        $userCount = $connection->table('users')->count();
        $this->assertIsInt($userCount);
        $this->assertGreaterThanOrEqual(3, $userCount);

        // Test distinct
        $distinctAges = $connection->table('users')->distinct()->pluck('age');
        $this->assertContains(25, $distinctAges);
        $this->assertContains(30, $distinctAges);
    }

    /**
     * @test
     */
    public function it_handles_aggregate_functions()
    {
        $connection = $this->getConnection();

        // Insert test data
        $connection->table('users')->insert([
            ['name' => 'User1', 'email' => 'user1@example.com', 'age' => 20],
            ['name' => 'User2', 'email' => 'user2@example.com', 'age' => 25],
            ['name' => 'User3', 'email' => 'user3@example.com', 'age' => 30],
        ]);

        // Test count
        $count = $connection->table('users')->count();
        $this->assertGreaterThanOrEqual(3, $count);

        // Test sum
        $ageSum = $connection->table('users')->sum('age');
        $this->assertGreaterThan(0, $ageSum);

        // Test avg
        $avgAge = $connection->table('users')->avg('age');
        $this->assertIsFloat($avgAge);
        $this->assertGreaterThan(0, $avgAge);

        // Test min and max
        $minAge = $connection->table('users')->min('age');
        $maxAge = $connection->table('users')->max('age');

        $this->assertIsInt($minAge);
        $this->assertIsInt($maxAge);
        $this->assertLessThanOrEqual($maxAge, $avgAge);
        $this->assertGreaterThanOrEqual($minAge, $avgAge);
    }

    /**
     * @test
     */
    public function it_handles_joins()
    {
        $connection = $this->getConnection();

        // Create and populate users
        $userId1 = $connection->table('users')->insertGetId(['name' => 'Author1', 'email' => 'author1@example.com']);
        $userId2 = $connection->table('users')->insertGetId(['name' => 'Author2', 'email' => 'author2@example.com']);

        // Create and populate posts
        $connection->table('posts')->insert([
            ['title' => 'Post 1', 'content' => 'Content 1', 'user_id' => $userId1],
            ['title' => 'Post 2', 'content' => 'Content 2', 'user_id' => $userId1],
            ['title' => 'Post 3', 'content' => 'Content 3', 'user_id' => $userId2],
        ]);

        // Test inner join
        $postsWithAuthors = $connection->table('posts')
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->select('posts.title', 'users.name as author_name')
            ->get();

        $this->assertGreaterThanOrEqual(3, count($postsWithAuthors));

        foreach ($postsWithAuthors as $post) {
            $post = $this->toObject($post);
            $this->assertNotEmpty($post->title);
            $this->assertNotEmpty($post->author_name);
        }

        // Test left join
        $allUsersWithPosts = $connection->table('users')
            ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
            ->select('users.name', 'posts.title')
            ->get();

        $this->assertGreaterThan(0, count($allUsersWithPosts));
    }

    /**
     * @test
     */
    public function it_handles_grouping_and_having()
    {
        $connection = $this->getConnection();

        // Create test data
        $connection->table('users')->insert([
            ['name' => 'John', 'email' => 'john1@example.com', 'age' => 25],
            ['name' => 'John', 'email' => 'john2@example.com', 'age' => 25],
            ['name' => 'Jane', 'email' => 'jane1@example.com', 'age' => 30],
        ]);

        // Test group by
        $groupedUsers = $connection->table('users')
            ->select('name', $connection->raw('COUNT(*) as count'))
            ->groupBy('name')
            ->get();

        $this->assertGreaterThan(0, count($groupedUsers));

        // Find John's group
        $johnGroup = null;
        foreach ($groupedUsers as $group) {
            $group = $this->toObject($group);
            if ($group->name === 'John') {
                $johnGroup = $group;
                break;
            }
        }

        $this->assertNotNull($johnGroup);
        $this->assertEquals(2, $johnGroup->count);

        // Test having clause
        $multipleUsers = $connection->table('users')
            ->select('name', $connection->raw('COUNT(*) as count'))
            ->groupBy('name')
            ->having('count', '>', 1)
            ->get();

        $this->assertGreaterThan(0, count($multipleUsers));
    }

    /**
     * @test
     */
    public function it_handles_transactions()
    {
        $connection = $this->getConnection();

        // Test successful transaction
        $connection->beginTransaction();

        try {
            $connection->table('users')->insert(['name' => 'Transaction Test 1', 'email' => 'trans1@example.com']);
            $connection->table('users')->insert(['name' => 'Transaction Test 2', 'email' => 'trans2@example.com']);

            $connection->commit();

            // Verify data was committed
            $users = $connection->table('users')->where('name', 'like', 'Transaction Test%')->get();
            $this->assertEquals(2, count($users));
        } catch (Exception $e) {
            $connection->rollback();
            $this->fail('Transaction should not fail');
        }

        // Test rollback transaction
        $initialCount = $connection->table('users')->count();

        $connection->beginTransaction();

        try {
            $connection->table('users')->insert(['name' => 'Rollback Test 1', 'email' => 'rollback1@example.com']);

            // Force an error by inserting duplicate email (if unique constraint exists)
            // For this test, we'll just manually rollback
            $connection->rollback();

            // Verify data was not committed
            $finalCount = $connection->table('users')->count();
            $this->assertEquals($initialCount, $finalCount);

        } catch (Exception $e) {
            $connection->rollback();
        }
    }

    /**
     * @test
     */
    public function it_handles_raw_expressions()
    {
        $connection = $this->getConnection();

        // Insert test data
        $connection->table('users')->insert([
            ['name' => 'Raw Test 1', 'email' => 'raw1@example.com', 'age' => 25],
            ['name' => 'Raw Test 2', 'email' => 'raw2@example.com', 'age' => 30],
        ]);

        // Test raw select
        $users = $connection->table('users')
            ->select($connection->raw('COUNT(*) as total'))
            ->where('name', 'like', 'Raw Test%')
            ->first();

        $users = $this->toObject($users);
        $this->assertEquals(2, $users->total);

        // Test raw where
        $users = $connection->table('users')
            ->whereRaw('LENGTH(name) > ?', [5])
            ->get();

        $this->assertGreaterThan(0, count($users));

        // Test Expression class
        $users = $connection->table('users')
            ->select('id', 'name', $connection->raw('UPPER(name) as upper_name'))
            ->where('name', 'like', 'Raw Test%')
            ->get();

        $this->assertGreaterThan(0, count($users));
        foreach ($users as $user) {
            $user = $this->toObject($user);
            $this->assertNotEmpty($user->upper_name);
            $this->assertEquals(strtoupper($user->name), $user->upper_name);
        }
    }

    /**
     * @test
     */
    public function it_handles_bulk_operations()
    {
        $connection = $this->getConnection();

        // Test bulk insert
        $users = [
            ['name' => 'Bulk User 1', 'email' => 'bulk1@example.com', 'age' => 20],
            ['name' => 'Bulk User 2', 'email' => 'bulk2@example.com', 'age' => 25],
            ['name' => 'Bulk User 3', 'email' => 'bulk3@example.com', 'age' => 30],
            ['name' => 'Bulk User 4', 'email' => 'bulk4@example.com', 'age' => 35],
        ];

        $result = $connection->table('users')->insert($users);
        $this->assertTrue($result);

        // Verify bulk insert
        $insertedUsers = $connection->table('users')->where('name', 'like', 'Bulk User%')->get();
        $this->assertEquals(4, count($insertedUsers));

        // Test bulk update
        $updateResult = $connection->table('users')
            ->where('name', 'like', 'Bulk User%')
            ->update(['age' => 99]);

        $this->assertGreaterThan(0, $updateResult);

        // Verify bulk update
        $updatedUsers = $connection->table('users')
            ->where('name', 'like', 'Bulk User%')
            ->where('age', 99)
            ->get();

        $this->assertEquals(4, count($updatedUsers));

        // Test bulk delete
        $deleteResult = $connection->table('users')->where('name', 'like', 'Bulk User%')->delete();
        $this->assertEquals(4, $deleteResult);

        // Verify bulk delete
        $remainingUsers = $connection->table('users')->where('name', 'like', 'Bulk User%')->get();
        $this->assertEquals(0, count($remainingUsers));
    }

    /**
     * @test
     */
    public function it_handles_subqueries()
    {
        $connection = $this->getConnection();

        // Create test data
        $connection->table('users')->insert([
            ['name' => 'SubQuery User 1', 'email' => 'sub1@example.com', 'age' => 25],
            ['name' => 'SubQuery User 2', 'email' => 'sub2@example.com', 'age' => 30],
            ['name' => 'SubQuery User 3', 'email' => 'sub3@example.com', 'age' => 35],
        ]);

        // Test whereIn with subquery
        $avgAge = $connection->table('users')->avg('age');
        $aboveAverageUsers = $connection->table('users')
            ->where('age', '>', $avgAge)
            ->get();

        $this->assertGreaterThan(0, count($aboveAverageUsers));

        // Test exists-style query simulation
        $usersWithPosts = $connection->table('users')
            ->whereExists(function($query) use ($connection) {
                $query->select($connection->raw(1))
                      ->from('posts')
                      ->whereRaw('posts.user_id = users.id');
            })
            ->get();

        // Since we may not have posts, just verify the query structure works
        $this->assertInstanceOf('Arpon\Database\Support\Collection', $usersWithPosts);
    }

    /**
     * @test
     */
    public function it_handles_database_schema_operations()
    {
        $connection = $this->getConnection();

        // Test table existence check (SQLite specific)
        $tables = $connection->select("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        $this->assertGreaterThan(0, count($tables));
        $tables[0] = $this->toObject($tables[0]);
        $this->assertEquals('users', $tables[0]->name);

        // Test column information
        $columns = $connection->select("PRAGMA table_info(users)");
        $this->assertGreaterThan(0, count($columns));

        // Verify expected columns exist
        $columnNames = array_map(function($col) { return $this->toObject($col)->name; }, $columns);
        $this->assertContains('id', $columnNames);
        $this->assertContains('name', $columnNames);
        $this->assertContains('email', $columnNames);
    }

    /**
     * @test
     */
    public function it_handles_special_sqlite_features()
    {
        $connection = $this->getConnection();

        // Test SQLite version
        $version = $connection->select('SELECT sqlite_version() as version');
        $version[0] = $this->toObject($version[0]);
        $this->assertNotEmpty($version[0]->version);

        // Test AUTOINCREMENT functionality
        $userId1 = $connection->table('users')->insertGetId(['name' => 'Auto 1', 'email' => 'auto1@example.com']);
        $userId2 = $connection->table('users')->insertGetId(['name' => 'Auto 2', 'email' => 'auto2@example.com']);

        $this->assertIsInt($userId1);
        $this->assertIsInt($userId2);
        $this->assertGreaterThan($userId1, $userId2);

        // Test CASE WHEN expressions
        $users = $connection->table('users')
            ->select(['name',
                $connection->raw("CASE WHEN age >= 30 THEN 'Senior' ELSE 'Junior' END as category")
            ])
            ->whereNotNull('age')
            ->get();

        if (count($users) > 0) {
            $this->assertTrue(in_array($users[0]->category, ['Senior', 'Junior']));
        }

        // Test COALESCE function
        $result = $connection->select('SELECT COALESCE(NULL, NULL, "default") as test');
        $result[0] = $this->toObject($result[0]);
        $this->assertEquals('default', $result[0]->test);
    }

    /**
     * @test
     */
    public function it_handles_error_conditions_gracefully()
    {
        $connection = $this->getConnection();

        // Test invalid SQL
        $this->expectException(Exception::class);
        $connection->select('SELECT invalid_column FROM nonexistent_table');
    }

    /**
     * @test
     */
    public function it_maintains_data_integrity()
    {
        $connection = $this->getConnection();

        // Insert data with relationships
        $userId = $connection->table('users')->insertGetId(['name' => 'Integrity User', 'email' => 'integrity@example.com']);

        $connection->table('posts')->insert([
            'title' => 'Integrity Post',
            'content' => 'Testing data integrity',
            'user_id' => $userId
        ]);

        // Verify referential integrity by checking relationships
        $userWithPosts = $connection->table('users')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->where('users.id', $userId)
            ->select('users.name', 'posts.title')
            ->get();

        $this->assertEquals(1, count($userWithPosts));
        $userWithPosts[0] = $this->toObject($userWithPosts[0]);
        $this->assertEquals('Integrity User', $userWithPosts[0]->name);
        $this->assertEquals('Integrity Post', $userWithPosts[0]->title);

        // Test cascade behavior simulation (manual cleanup)
        $connection->table('posts')->where('user_id', $userId)->delete();
        $connection->table('users')->where('id', $userId)->delete();

        // Verify cleanup
        $this->assertEquals(0, $connection->table('posts')->where('user_id', $userId)->count());
        $this->assertEquals(0, $connection->table('users')->where('id', $userId)->count());
    }
}
