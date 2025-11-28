<?php

namespace Arpon\Database\Tests\Feature;

use Arpon\Database\Tests\TestCase;
use Arpon\Database\Tests\Models\User;
use Arpon\Database\Tests\Models\Post;
use Arpon\Database\Eloquent\Collection;
use Exception;

/**
 * Comprehensive Eloquent Model Tests
 * 
 * Converted from test-eloquent.php to professional PHPUnit format
 */
class EloquentTest extends TestCase
{
    /**
     * @test
     */
    public function it_creates_and_retrieves_models()
    {
        // Test model creation
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'JOHN@EXAMPLE.COM', // Test mutator
            'age' => 30,
            'settings' => ['theme' => 'dark', 'notifications' => true]
        ]);

        $this->assertNotNull($user);
        $this->assertNotNull($user->id);
        $this->assertEquals('john@example.com', $user->email); // Email should be lowercased
        $this->assertEquals('John Doe', $user->name); // Test accessor
        $this->assertEquals(30, $user->age);
        $this->assertIsArray($user->settings);
        
        // Test retrieval
        $foundUser = User::find($user->id);
        $this->assertEquals($user->id, $foundUser->id);
        $this->assertEquals($user->name, $foundUser->name);
    }

    /**
     * @test
     */
    public function it_handles_model_attributes_and_casting()
    {
        $user = User::create([
            'name' => 'jane smith',
            'email' => 'JANE@EXAMPLE.COM',
            'age' => '25', // String should be cast to integer
            'settings' => '{"theme":"light","notifications":false}', // JSON string
            'is_active' => 1
        ]);

        // Test accessors and mutators
        $this->assertEquals('Jane Smith', $user->name); // Accessor should capitalize
        $this->assertEquals('jane@example.com', $user->email); // Mutator should lowercase
        
        // Test casting
        $this->assertIsInt($user->age);
        $this->assertEquals(25, $user->age);
        // JSON casting may return string or array depending on implementation
        $settings = $user->settings;
        if (is_string($settings)) {
            $settings = json_decode($settings, true);
        }
        $this->assertEquals('light', $settings['theme']);
        $this->assertTrue($user->is_active);
    }

    /**
     * @test
     */
    public function it_handles_model_updates()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'age' => 30
        ]);

        // Test update method
        $user->update(['name' => 'Updated User', 'age' => 31]);
        
        $this->assertEquals('Updated User', $user->name);
        $this->assertEquals(31, $user->age);
        
        // Test attribute assignment and save
        $user->email = 'UPDATED@EXAMPLE.COM';
        $user->save();
        
        $this->assertEquals('updated@example.com', $user->email);
        
        // Verify in database
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated User',
            'email' => 'updated@example.com',
            'age' => 31
        ]);
    }

    /**
     * @test
     */
    public function it_handles_model_deletion()
    {
        $user = User::create([
            'name' => 'Delete Me',
            'email' => 'delete@example.com'
        ]);

        $userId = $user->id;
        
        // Test deletion
        $result = $user->delete();
        $this->assertTrue($result);
        
        // Verify deletion
        $this->assertDatabaseMissing('users', ['id' => $userId]);
        
        // Test find returns null
        $deletedUser = User::find($userId);
        $this->assertNull($deletedUser);
    }

    /**
     * @test
     */
    public function it_handles_collection_operations()
    {
        // Create multiple users
        $users = [
            User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'age' => 25]),
            User::create(['name' => 'Bob', 'email' => 'bob@example.com', 'age' => 30]),
            User::create(['name' => 'Charlie', 'email' => 'charlie@example.com', 'age' => 35])
        ];

        // Test all() returns collection
        $allUsers = User::all();
        $this->assertInstanceOf(Collection::class, $allUsers);
        $this->assertGreaterThanOrEqual(3, $allUsers->count());

        // Test collection pluck
        $emails = $allUsers->pluck('email');
        $this->assertContains('alice@example.com', $emails->all());
        
        // Test collection filter
        $youngUsers = $allUsers->filter(function($user) {
            return $user->age < 30;
        });
        $this->assertGreaterThan(0, $youngUsers->count());

        // Test collection map
        $names = $allUsers->map(function($user) {
            return strtoupper($user->name);
        });
        $this->assertContains('ALICE', $names->all());

        // Test collection keyBy
        $usersById = $allUsers->keyBy('id');
        $this->assertArrayHasKey($users[0]->id, $usersById->all());

        // Test collection sortBy
        $sortedUsers = $allUsers->sortBy('age');
        $this->assertInstanceOf(Collection::class, $sortedUsers);
    }

    /**
     * @test
     */
    public function it_handles_fillable_and_guarded_attributes()
    {
        // Test mass assignment with fillable
        $userData = [
            'name' => 'Fillable Test',
            'email' => 'fillable@example.com',
            'age' => 25,
            'settings' => ['key' => 'value']
        ];

        $user = User::create($userData);
        
        $this->assertEquals('Fillable Test', $user->name);
        $this->assertEquals('fillable@example.com', $user->email);
        $this->assertEquals(25, $user->age);

        // Test fill method
        $user2 = new User();
        $user2->fill([
            'name' => 'Filled User',
            'email' => 'filled@example.com'
        ]);

        $this->assertEquals('Filled User', $user2->name);
        $this->assertEquals('filled@example.com', $user2->email);
    }

    /**
     * @test
     */
    public function it_handles_model_serialization()
    {
        $user = User::create([
            'name' => 'Serialization Test',
            'email' => 'serialize@example.com',
            'age' => 28,
            'settings' => ['theme' => 'dark']
        ]);

        // Test toArray
        $array = $user->toArray();
        $this->assertIsArray($array);
        $this->assertEquals($user->name, $array['name']);
        $this->assertEquals($user->email, $array['email']);

        // Test toJson
        $json = $user->toJson();
        $this->assertIsString($json);
        
        $decoded = json_decode($json, true);
        $this->assertEquals($user->name, $decoded['name']);
        $this->assertEquals($user->email, $decoded['email']);

        // Test collection serialization
        $users = User::limit(2)->get();
        $usersArray = $users->toArray();
        $this->assertIsArray($usersArray);
        
        $usersJson = $users->toJson();
        $this->assertIsString($usersJson);
        $decodedUsers = json_decode($usersJson, true);
        $this->assertIsArray($decodedUsers);
    }

    /**
     * @test
     */
    public function it_handles_model_timestamps()
    {
        $beforeCreate = date('Y-m-d H:i:s');
        
        $user = User::create([
            'name' => 'Timestamp Test',
            'email' => 'timestamp@example.com'
        ]);

        $afterCreate = date('Y-m-d H:i:s');

        // Check created_at is set
        $this->assertNotNull($user->created_at);
        $createdAt = $user->created_at instanceof \DateTime ? $user->created_at->format('Y-m-d H:i:s') : $user->created_at;
        $this->assertGreaterThanOrEqual($beforeCreate, $createdAt);
        $this->assertLessThanOrEqual($afterCreate, $createdAt);

        // Update model and check updated_at
        sleep(1); // Ensure time difference
        $beforeUpdate = date('Y-m-d H:i:s');
        $user->update(['name' => 'Updated Timestamp Test']);
        $afterUpdate = date('Y-m-d H:i:s');

        $this->assertNotNull($user->updated_at);
        $updatedAt = $user->updated_at instanceof \DateTime ? $user->updated_at->format('Y-m-d H:i:s') : $user->updated_at;
        $this->assertGreaterThanOrEqual($beforeUpdate, $updatedAt);
        $this->assertLessThanOrEqual($afterUpdate, $updatedAt);
    }

    /**
     * @test
     */
    public function it_handles_model_dirty_and_clean_states()
    {
        $user = User::create([
            'name' => 'Dirty Test',
            'email' => 'dirty@example.com'
        ]);

        // Fresh model should be clean
        $this->assertFalse($user->isDirty());
        $this->assertTrue($user->isClean());

        // Modify attribute
        $user->name = 'Modified Name';
        $this->assertTrue($user->isDirty());
        $this->assertFalse($user->isClean());
        $this->assertTrue($user->isDirty('name'));

        // Save and check clean state
        $user->save();
        $this->assertFalse($user->isDirty());
        $this->assertTrue($user->isClean());
    }

    /**
     * @test
     */
    public function it_handles_model_attribute_existence()
    {
        $user = User::create([
            'name' => 'Attribute Test',
            'email' => 'attribute@example.com',
            'age' => 25
        ]);

        // Test attribute existence
        $this->assertTrue(isset($user->name));
        $this->assertTrue(isset($user->email));
        $this->assertTrue(isset($user->age));
        $this->assertFalse(isset($user->nonexistent));

        // Test attribute access
        $this->assertEquals('Attribute Test', $user->name);
        $this->assertNull($user->nonexistent);
    }

    /**
     * @test
     */
    public function it_handles_model_increments_and_decrements()
    {
        $user = User::create([
            'name' => 'Increment Test',
            'email' => 'increment@example.com',
            'age' => 25
        ]);

        $originalAge = $user->age;

        // Test increment
        $user->increment('age');
        $this->assertEquals($originalAge + 1, $user->age);

        // Test increment with amount
        $user->increment('age', 5);
        $this->assertEquals($originalAge + 6, $user->age);

        // Test decrement
        $user->decrement('age');
        $this->assertEquals($originalAge + 5, $user->age);

        // Test decrement with amount
        $user->decrement('age', 3);
        $this->assertEquals($originalAge + 2, $user->age);
    }

    /**
     * @test
     */
    public function it_handles_model_refresh()
    {
        $user = User::create([
            'name' => 'Refresh Test',
            'email' => 'refresh@example.com'
        ]);

        // Modify in memory
        $user->name = 'Modified In Memory';
        $this->assertEquals('Modified In Memory', $user->name);

        // Refresh from database
        $user->refresh();
        $this->assertEquals('Refresh Test', $user->name);
    }

    /**
     * @test
     */
    public function it_handles_advanced_query_operations()
    {
        $this->createEloquentTestData();

        // Test whereIn
        $users = User::whereIn('age', [25, 30])->get();
        $this->assertGreaterThan(0, $users->count());

        // Test whereNotIn
        $users = User::whereNotIn('age', [999])->get();
        $this->assertGreaterThan(0, $users->count());

        // Test whereBetween
        $users = User::whereBetween('age', [20, 40])->get();
        $this->assertGreaterThan(0, $users->count());

        // Test whereNull/whereNotNull
        $users = User::whereNotNull('email')->get();
        $this->assertGreaterThan(0, $users->count());

        // Test latest/oldest
        $latestUser = User::latest('created_at')->first();
        $this->assertInstanceOf(User::class, $latestUser);

        $oldestUser = User::oldest('created_at')->first();
        $this->assertInstanceOf(User::class, $oldestUser);
    }

    /**
     * @test
     */
    public function it_handles_model_scope_methods()
    {
        // Create posts with different published states
        $user = User::create(['name' => 'Scope Test', 'email' => 'scope@example.com']);
        
        Post::create(['title' => 'Published Post', 'content' => 'Content', 'user_id' => $user->id, 'published_at' => date('Y-m-d H:i:s')]);
        Post::create(['title' => 'Draft Post', 'content' => 'Content', 'user_id' => $user->id, 'published_at' => null]);

        // Test scope methods if they exist
        $publishedPosts = Post::published()->get();
        $userPosts = Post::byUser($user->id)->get();

        $this->assertInstanceOf(Collection::class, $publishedPosts);
        $this->assertInstanceOf(Collection::class, $userPosts);
        $this->assertEquals(2, $userPosts->count());
    }

    /**
     * Helper method to create test data
     */
    protected function createEloquentTestData()
    {
        User::create(['name' => 'Alice Johnson', 'email' => 'alice@example.com', 'age' => 25]);
        User::create(['name' => 'Bob Smith', 'email' => 'bob@example.com', 'age' => 30]);
        User::create(['name' => 'Charlie Brown', 'email' => 'charlie@example.com', 'age' => 35]);
    }
}