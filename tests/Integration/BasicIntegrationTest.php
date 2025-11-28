<?php

namespace Arpon\Database\Tests\Integration;

use Arpon\Database\Tests\TestCase;
use Arpon\Database\Tests\Models\User;
use Arpon\Database\Tests\Models\Post;

/**
 * Basic Integration Test
 * 
 * Validates the core functionality works in PHPUnit
 */
class BasicIntegrationTest extends TestCase
{
    /**
     * @test
     */
    public function it_can_create_and_retrieve_users()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'age' => 30
        ]);

        $this->assertNotNull($user);
        $this->assertNotNull($user->id);
        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertEquals(30, $user->age);

        // Test retrieval
        $foundUser = User::find($user->id);
        $this->assertNotNull($foundUser);
        $this->assertEquals($user->id, $foundUser->id);
        $this->assertEquals($user->name, $foundUser->name);
    }

    /**
     * @test
     */
    public function it_can_handle_relationships()
    {
        // Create user
        $user = User::create([
            'name' => 'Author',
            'email' => 'author@example.com'
        ]);

        // Create posts
        $post1 = Post::create([
            'title' => 'First Post',
            'content' => 'Content of first post',
            'user_id' => $user->id
        ]);

        $post2 = Post::create([
            'title' => 'Second Post', 
            'content' => 'Content of second post',
            'user_id' => $user->id
        ]);

        // Test HasMany relationship
        $posts = $user->posts;
        $this->assertGreaterThanOrEqual(2, $posts->count());

        // Test BelongsTo relationship
        $postUser = $post1->user;
        $this->assertNotNull($postUser);
        $this->assertEquals($user->id, $postUser->id);
    }

    /**
     * @test
     */
    public function it_can_handle_query_builder()
    {
        // Create test data
        User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'age' => 25]);
        User::create(['name' => 'Bob', 'email' => 'bob@example.com', 'age' => 30]);
        User::create(['name' => 'Charlie', 'email' => 'charlie@example.com', 'age' => 35]);

        // Test basic queries
        $users = User::all();
        $this->assertGreaterThanOrEqual(3, $users->count());

        $youngUsers = User::where('age', '<', 30)->get();
        $this->assertGreaterThan(0, $youngUsers->count());

        $alice = User::where('name', 'Alice')->first();
        $this->assertNotNull($alice);
        $this->assertEquals('alice@example.com', $alice->email);

        // Test count using query builder  
        $allUsers = User::all();
        $this->assertGreaterThanOrEqual(3, $allUsers->count());
    }

    /**
     * @test
     */
    public function it_can_handle_model_operations()
    {
        $user = User::create([
            'name' => 'Update Test',
            'email' => 'update@example.com',
            'age' => 25
        ]);

        // Test update
        $user->name = 'Updated Name';
        $user->age = 26;
        $user->save();
        $this->assertEquals('Updated Name', $user->name);
        $this->assertEquals(26, $user->age);

        // Test save
        $user->email = 'newemail@example.com';
        $user->save();
        $this->assertEquals('newemail@example.com', $user->email);

        // Test delete
        $userId = $user->id;
        $result = $user->delete();
        $this->assertTrue($result);

        $deletedUser = User::find($userId);
        $this->assertNull($deletedUser);
    }

    /**
     * @test
     */
    public function it_validates_database_helper_methods()
    {
        $user = User::create([
            'name' => 'Helper Test',
            'email' => 'helper@example.com'
        ]);

        // Test assertDatabaseHas
        $this->assertDatabaseHas('users', [
            'name' => 'Helper Test',
            'email' => 'helper@example.com'
        ]);

        // Test assertDatabaseCount
        $allUsers = User::all();
        $this->assertDatabaseCount('users', $allUsers->count());

        // Delete and test assertDatabaseMissing
        $user->delete();
        $this->assertDatabaseMissing('users', [
            'id' => $user->id
        ]);
    }
}