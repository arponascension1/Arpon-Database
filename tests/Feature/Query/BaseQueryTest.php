<?php

namespace Arpon\Database\Tests\Feature\Query;

use Arpon\Database\Tests\TestCase;

abstract class BaseQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestData();
    }

    /** @test */
    public function it_can_select_all_rows()
    {
        $users = $this->getConnection()->table('users')->get();
        $this->assertCount(2, $users);
        $this->assertEquals('John Doe', $users[0]->name);
        $this->assertEquals('Jane Smith', $users[1]->name);
    }

    /** @test */
    public function it_can_select_specific_columns()
    {
        $user = $this->getConnection()->table('users')->select(['name', 'email'])->first();
        
        $this->assertTrue(isset($user->name));
        $this->assertTrue(isset($user->email));
        $this->assertFalse(isset($user->age));
    }

    /** @test */
    public function it_can_filter_with_where_clause()
    {
        $john = $this->getConnection()->table('users')->where('name', 'John Doe')->first();
        $this->assertNotNull($john);
        $this->assertEquals('John Doe', $john->name);

        $jane = $this->getConnection()->table('users')->where('age', '>', 28)->first();
        $this->assertNotNull($jane);
        $this->assertEquals('John Doe', $jane->name);
    }

    /** @test */
    public function it_can_use_or_where_clauses()
    {
        $users = $this->getConnection()->table('users')
            ->where('name', 'John Doe')
            ->orWhere('name', 'Jane Smith')
            ->get();

        $this->assertCount(2, $users);
    }

    /** @test */
    public function it_can_order_results()
    {
        $users = $this->getConnection()->table('users')->orderBy('age', 'asc')->get();
        $this->assertEquals('Jane Smith', $users[0]->name);
        $this->assertEquals('John Doe', $users[1]->name);

        $usersDesc = $this->getConnection()->table('users')->orderBy('age', 'desc')->get();
        $this->assertEquals('John Doe', $usersDesc[0]->name);
        $this->assertEquals('Jane Smith', $usersDesc[1]->name);
    }

    /** @test */
    public function it_can_limit_and_offset_results()
    {
        $users = $this->getConnection()->table('users')->orderBy('id')->limit(1)->get();
        $this->assertCount(1, $users);
        $this->assertEquals('John Doe', $users[0]->name);

        $usersOffset = $this->getConnection()->table('users')->orderBy('id')->limit(1)->offset(1)->get();
        $this->assertCount(1, $usersOffset);
        $this->assertEquals('Jane Smith', $usersOffset[0]->name);
    }

    /** @test */
    public function it_can_join_tables()
    {
        $results = $this->getConnection()->table('users')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->select('users.name', 'posts.title')
            ->get();

        $this->assertGreaterThanOrEqual(3, count($results));
        $this->assertTrue(isset($results[0]->name));
        $this->assertTrue(isset($results[0]->title));
    }

    /** @test */
    public function it_can_left_join_tables()
    {
        // Add a user without posts
        $this->getConnection()->table('users')->insert([
            'name' => 'No Post User', 
            'email' => 'nopost@example.com',
            'age' => 20
        ]);

        $results = $this->getConnection()->table('users')
            ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
            ->where('users.name', 'No Post User')
            ->select('users.name', 'posts.title')
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('No Post User', $results[0]->name);
        $this->assertNull($results[0]->title);
    }

    /** @test */
    public function it_can_insert_a_row()
    {
        $this->getConnection()->table('users')->insert([
            'name' => 'New User',
            'email' => 'new@example.com',
            'age' => 40
        ]);

        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    }

    /** @test */
    public function it_can_update_rows()
    {
        $this->getConnection()->table('users')
            ->where('name', 'John Doe')
            ->update(['age' => 31]);

        $this->assertDatabaseHas('users', ['name' => 'John Doe', 'age' => 31]);
    }

    /** @test */
    public function it_can_delete_rows()
    {
        $this->getConnection()->table('users')
            ->where('name', 'Jane Smith')
            ->delete();

        $this->assertDatabaseMissing('users', ['name' => 'Jane Smith']);
    }

    /** @test */
    public function it_can_count_results()
    {
        $count = $this->getConnection()->table('users')->count();
        $this->assertEquals(2, $count);

        $countFiltered = $this->getConnection()->table('users')->where('age', '>', 28)->count();
        $this->assertEquals(1, $countFiltered);
    }

    /** @test */
    public function it_can_get_aggregates()
    {
        $maxAge = $this->getConnection()->table('users')->max('age');
        $this->assertEquals(30, $maxAge);

        $minAge = $this->getConnection()->table('users')->min('age');
        $this->assertEquals(25, $minAge);
        
        $avgAge = $this->getConnection()->table('users')->avg('age');
        $this->assertEquals(27.5, $avgAge);
    }

    /** @test */
    public function it_can_select_distinct_rows()
    {
        $this->getConnection()->table('users')->insert([
            'name' => 'John Doe',
            'email' => 'john.duplicate@example.com',
            'age' => 30
        ]);

        $users = $this->getConnection()->table('users')->distinct()->select('name')->get();
        // John Doe (2 rows but distinct 1) + Jane Smith (1 row) = 2 distinct names
        $this->assertCount(2, $users);
    }

    /** @test */
    public function it_can_group_results()
    {
        // John: 30, Jane: 25. Add another John age 30
        $this->getConnection()->table('users')->insert([
            'name' => 'John Doe',
            'email' => 'john2@example.com',
            'age' => 30
        ]);

        $results = $this->getConnection()->table('users')
            ->select('age', $this->getConnection()->raw('count(*) as total'))
            ->groupBy('age')
            ->orderBy('age')
            ->get();

        $this->assertCount(2, $results); // 25 and 30
        $this->assertEquals(25, $results[0]->age);
        $this->assertEquals(1, $results[0]->total);
        $this->assertEquals(30, $results[1]->age);
        $this->assertEquals(2, $results[1]->total);
    }

    /** @test */
    public function it_can_filter_groups_with_having()
    {
        $this->getConnection()->table('users')->insert([
            'name' => 'John Doe',
            'email' => 'john2@example.com',
            'age' => 30
        ]);

        $results = $this->getConnection()->table('users')
            ->select('age', $this->getConnection()->raw('count(*) as total'))
            ->groupBy('age')
            ->having('total', '>', 1)
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals(30, $results[0]->age);
    }

    /** @test */
    public function it_can_filter_with_where_in()
    {
        $results = $this->getConnection()->table('users')
            ->whereIn('age', [25, 30])
            ->get();

        $this->assertCount(2, $results);
        
        $results = $this->getConnection()->table('users')
            ->whereIn('age', [25])
            ->get();
            
        $this->assertCount(1, $results);
        $this->assertEquals('Jane Smith', $results[0]->name);
    }

    /** @test */
    public function it_can_filter_with_where_not_in()
    {
        $results = $this->getConnection()->table('users')
            ->whereNotIn('age', [25])
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]->name);
    }

    /** @test */
    public function it_can_filter_with_where_between()
    {
        $results = $this->getConnection()->table('users')
            ->whereBetween('age', [20, 28])
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Jane Smith', $results[0]->name);
    }

    /** @test */
    public function it_can_use_nested_where_clauses()
    {
        // (name = John Doe) OR (age < 26 AND name = Jane Smith)
        
        $results = $this->getConnection()->table('users')
            ->where(function ($query) {
                $query->where('name', 'John Doe')
                      ->orWhere('age', '<', 26);
            })
            ->get();

        // John Doe (matches first part), Jane Smith (matches second part 25 < 26)
        $this->assertCount(2, $results);
        
        // Let's try something that excludes one
        $results = $this->getConnection()->table('users')
            ->where('age', '>', 20) // Both
            ->where(function ($query) {
                $query->where('name', 'Jane Smith')
                      ->orWhere('email', 'john@example.com');
            })
            ->get();
            
        $this->assertCount(2, $results);
    }
}
