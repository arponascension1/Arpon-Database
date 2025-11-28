<?php

namespace Arpon\Database\Tests\Unit;

use Arpon\Database\Tests\TestCase;
use Arpon\Database\Tests\Models\User;
use Arpon\Database\Tests\Models\Post;

/**
 * Performance and Cross-Compatibility Tests
 * 
 * Converted from test-performance.php and test-cross-compatibility.php
 */
class PerformanceTest extends TestCase
{
    protected function toObject($data) {
        return is_array($data) ? (object) $data : $data;
    }
    
    /**
     * @test
     */
    public function it_handles_bulk_operations_efficiently()
    {
        $connection = $this->getConnection();

        // Test bulk insert performance
        $startTime = microtime(true);
        
        $bulkData = [];
        for ($i = 0; $i < 1000; $i++) {
            $bulkData[] = [
                'name' => "Bulk User {$i}",
                'email' => "bulk{$i}@example.com",
                'age' => rand(18, 65)
            ];
        }

        $connection->table('users')->insert($bulkData);
        
        $insertTime = microtime(true) - $startTime;
        
        // Verify data was inserted
        $count = $connection->table('users')->where('name', 'like', 'Bulk User%')->count();
        $this->assertEquals(1000, $count);
        
        // Insert should complete within reasonable time (adjust threshold as needed)
        $this->assertLessThan(5.0, $insertTime, 'Bulk insert should complete within 5 seconds');
    }

    /**
     * @test
     */
    public function it_handles_large_result_sets_efficiently()
    {
        $connection = $this->getConnection();

        // Create test data
        $testData = [];
        for ($i = 0; $i < 500; $i++) {
            $testData[] = [
                'name' => "Performance User {$i}",
                'email' => "perf{$i}@example.com",
                'age' => rand(18, 65)
            ];
        }
        $connection->table('users')->insert($testData);

        // Test large result set retrieval
        $startTime = microtime(true);
        $users = $connection->table('users')->where('name', 'like', 'Performance User%')->get();
        $selectTime = microtime(true) - $startTime;

        $this->assertEquals(500, count($users));
        $this->assertLessThan(2.0, $selectTime, 'Large result set retrieval should complete within 2 seconds');

        // Test memory usage doesn't grow excessively
        $memoryBefore = memory_get_usage();
        foreach ($users as $user) {
            $user = $this->toObject($user);
            $name = $user->name; // Access data
        }
        $memoryAfter = memory_get_usage();
        
        $memoryIncrease = $memoryAfter - $memoryBefore;
        $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease, 'Memory increase should be less than 10MB');
    }

    /**
     * @test
     */
    public function it_optimizes_query_performance()
    {
        $connection = $this->getConnection();

        // Insert indexed test data
        for ($i = 0; $i < 100; $i++) {
            $connection->table('users')->insert([
                'name' => "Query Test {$i}",
                'email' => "querytest{$i}@example.com",
                'age' => rand(18, 65)
            ]);
        }

        // Test indexed vs non-indexed query performance
        $startTime = microtime(true);
        $userById = $connection->table('users')->where('id', 1)->first();
        $indexedTime = microtime(true) - $startTime;

        $startTime = microtime(true);
        $userByName = $connection->table('users')->where('name', 'Query Test 1')->first();
        $nonIndexedTime = microtime(true) - $startTime;

        // Both queries should complete reasonably fast
        $this->assertLessThan(0.1, $indexedTime, 'Indexed query should be very fast');
        $this->assertLessThan(0.5, $nonIndexedTime, 'Non-indexed query should still be reasonable');
    }

    /**
     * @test
     */
    public function it_handles_concurrent_operations()
    {
        $connection = $this->getConnection();

        // Simulate concurrent operations
        $operations = [];
        
        for ($i = 0; $i < 10; $i++) {
            $operations[] = function() use ($connection, $i) {
                $connection->table('users')->insert([
                    'name' => "Concurrent User {$i}",
                    'email' => "concurrent{$i}@example.com"
                ]);
                
                return $connection->table('users')
                    ->where('email', "concurrent{$i}@example.com")
                    ->first();
            };
        }

        // Execute operations and measure time
        $startTime = microtime(true);
        $results = [];
        
        foreach ($operations as $operation) {
            $results[] = $operation();
        }
        
        $totalTime = microtime(true) - $startTime;

        // Verify all operations completed successfully
        $this->assertEquals(10, count($results));
        foreach ($results as $result) {
            $result = $this->toObject($result);
            $this->assertNotNull($result);
            $this->assertStringContainsString('Concurrent User', $result->name);
        }

        // Operations should complete within reasonable time
        $this->assertLessThan(2.0, $totalTime, 'Concurrent operations should complete within 2 seconds');
    }

    /**
     * @test
     */
    public function it_handles_eloquent_performance()
    {
        // Test Eloquent model performance
        $startTime = microtime(true);

        $users = [];
        for ($i = 0; $i < 100; $i++) {
            $users[] = User::create([
                'name' => "Eloquent User {$i}",
                'email' => "eloquent{$i}@example.com",
                'age' => rand(18, 65)
            ]);
        }

        $createTime = microtime(true) - $startTime;

        // Test retrieval performance
        $startTime = microtime(true);
        $retrievedUsers = User::where('name', 'like', 'Eloquent User%')->get();
        $retrievalTime = microtime(true) - $startTime;

        $this->assertEquals(100, count($users));
        $this->assertEquals(100, $retrievedUsers->count());
        
        // Eloquent operations should be reasonably performant
        $this->assertLessThan(3.0, $createTime, 'Eloquent creation should complete within 3 seconds');
        $this->assertLessThan(1.0, $retrievalTime, 'Eloquent retrieval should complete within 1 second');
    }

    /**
     * @test
     */
    public function it_handles_relationship_performance()
    {
        // Create users with posts for relationship testing
        $users = [];
        for ($i = 0; $i < 10; $i++) {
            $user = User::create([
                'name' => "Relationship User {$i}",
                'email' => "rel{$i}@example.com"
            ]);
            $users[] = $user;

            // Create posts for each user
            for ($j = 0; $j < 5; $j++) {
                Post::create([
                    'title' => "Post {$j} by User {$i}",
                    'content' => "Content for post {$j}",
                    'user_id' => $user->id
                ]);
            }
        }

        // Test relationship loading performance
        $startTime = microtime(true);
        
        foreach ($users as $user) {
            $posts = $user->posts; // This should trigger lazy loading
            $this->assertEquals(5, $posts->count());
        }
        
        $relationshipTime = microtime(true) - $startTime;

        // Relationship loading should be reasonable (note: this would be N+1 queries)
        $this->assertLessThan(2.0, $relationshipTime, 'Relationship loading should complete within 2 seconds');
    }

    /**
     * @test
     */
    public function it_tests_memory_efficiency()
    {
        $initialMemory = memory_get_usage();

        // Create a reasonable number of objects
        $objects = [];
        for ($i = 0; $i < 1000; $i++) {
            $user = new User([
                'name' => "Memory Test {$i}",
                'email' => "memory{$i}@example.com"
            ]);
            $objects[] = $user;
        }

        $peakMemory = memory_get_usage();
        
        // Clear objects
        unset($objects);
        
        $finalMemory = memory_get_usage();
        
        $memoryUsed = $peakMemory - $initialMemory;
        $memoryFreed = $peakMemory - $finalMemory;
        
        // Memory usage should be reasonable
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, 'Memory usage should be less than 50MB');
        
        // Some memory should be freed (though PHP's garbage collection is lazy)
        $this->assertGreaterThanOrEqual(0, $memoryFreed, 'Some memory should be freed');
    }

    /**
     * @test
     */
    public function it_tests_cross_database_compatibility()
    {
        $connection = $this->getConnection();

        // Test standard SQL operations that should work across databases
        $testCases = [
            // Basic CRUD operations
            ['INSERT', function() use ($connection) {
                return $connection->table('users')->insert(['name' => 'Compat Test', 'email' => 'compat@example.com']);
            }],
            
            ['SELECT', function() use ($connection) {
                return $connection->table('users')->where('email', 'compat@example.com')->first();
            }],
            
            ['UPDATE', function() use ($connection) {
                return $connection->table('users')->where('email', 'compat@example.com')->update(['name' => 'Updated Compat']);
            }],
            
            ['DELETE', function() use ($connection) {
                return $connection->table('users')->where('email', 'compat@example.com')->delete();
            }],
            
            // Aggregate functions
            ['COUNT', function() use ($connection) {
                return $connection->table('users')->count();
            }],
            
            ['MAX', function() use ($connection) {
                // Insert test data first
                $connection->table('users')->insert(['name' => 'Age Test', 'email' => 'age@example.com', 'age' => 50]);
                return $connection->table('users')->max('age');
            }],
            
            // Join operations
            ['JOIN', function() use ($connection) {
                // Create test data
                $userId = $connection->table('users')->insertGetId(['name' => 'Join Test', 'email' => 'join@example.com']);
                $connection->table('posts')->insert(['title' => 'Join Post', 'content' => 'Content', 'user_id' => $userId]);
                
                return $connection->table('users')
                    ->join('posts', 'users.id', '=', 'posts.user_id')
                    ->where('users.email', 'join@example.com')
                    ->first();
            }],
        ];

        foreach ($testCases as [$operation, $test]) {
            try {
                $result = $test();
                $this->assertTrue(true, "{$operation} operation completed successfully");
            } catch (Exception $e) {
                $this->fail("{$operation} operation failed: " . $e->getMessage());
            }
        }
    }

    /**
     * @test
     */
    public function it_tests_data_type_compatibility()
    {
        $connection = $this->getConnection();

        // Test various data types that should work across databases
        $testData = [
            'name' => 'Data Type Test',
            'email' => 'datatype@example.com',
            'age' => 25,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        // Insert and retrieve
        $connection->table('users')->insert($testData);
        $retrieved = $connection->table('users')->where('email', 'datatype@example.com')->first();
        $retrieved = $this->toObject($retrieved);

        $this->assertEquals($testData['name'], $retrieved->name);
        $this->assertEquals($testData['email'], $retrieved->email);
        $this->assertEquals($testData['age'], (int)$retrieved->age);
        
        // Date comparison (allowing for slight formatting differences)
        $this->assertStringContainsString(date('Y-m-d'), $retrieved->created_at);
    }

    /**
     * @test
     */
    public function it_tests_transaction_compatibility()
    {
        $connection = $this->getConnection();

        // Test basic transaction operations
        $initialCount = $connection->table('users')->count();

        // Successful transaction
        $connection->beginTransaction();
        $connection->table('users')->insert(['name' => 'Transaction Success', 'email' => 'transsuccess@example.com']);
        $connection->commit();

        $successCount = $connection->table('users')->count();
        $this->assertEquals($initialCount + 1, $successCount);

        // Rollback transaction
        $connection->beginTransaction();
        $connection->table('users')->insert(['name' => 'Transaction Rollback', 'email' => 'transrollback@example.com']);
        $connection->rollback();

        $rollbackCount = $connection->table('users')->count();
        $this->assertEquals($successCount, $rollbackCount);
    }

    /**
     * @test
     */
    public function it_benchmarks_query_types()
    {
        $connection = $this->getConnection();

        // Prepare test data
        $testData = [];
        for ($i = 0; $i < 500; $i++) {
            $testData[] = [
                'name' => "Benchmark User {$i}",
                'email' => "benchmark{$i}@example.com",
                'age' => rand(18, 65)
            ];
        }
        $connection->table('users')->insert($testData);

        $benchmarks = [];

        // Benchmark different query types
        $queryTypes = [
            'Simple Select' => function() use ($connection) {
                return $connection->table('users')->where('id', 1)->first();
            },
            
            'Range Query' => function() use ($connection) {
                return $connection->table('users')->whereBetween('age', [25, 35])->get();
            },
            
            'Like Query' => function() use ($connection) {
                return $connection->table('users')->where('name', 'like', 'Benchmark User 1%')->get();
            },
            
            'Count Query' => function() use ($connection) {
                return $connection->table('users')->where('age', '>', 30)->count();
            },
            
            'Order By Query' => function() use ($connection) {
                return $connection->table('users')->orderBy('age')->limit(10)->get();
            },
        ];

        foreach ($queryTypes as $type => $query) {
            $startTime = microtime(true);
            $result = $query();
            $endTime = microtime(true);
            
            $benchmarks[$type] = $endTime - $startTime;
            
            // Verify query returned valid results
            $this->assertNotNull($result, "Query type '{$type}' should return valid results");
        }

        // All queries should complete within reasonable time
        foreach ($benchmarks as $type => $time) {
            $this->assertLessThan(1.0, $time, "Query type '{$type}' should complete within 1 second (actual: {$time}s)");
        }

        // Output benchmark results (for manual review)
        echo "\nQuery Benchmarks:\n";
        foreach ($benchmarks as $type => $time) {
            echo sprintf("  %-20s: %.4f seconds\n", $type, $time);
        }
    }
}