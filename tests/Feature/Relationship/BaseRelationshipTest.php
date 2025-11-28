<?php

namespace Arpon\Database\Tests\Feature\Relationship;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Arpon\Database\Tests\TestCase;
use Arpon\Database\Tests\Models\User;
use Arpon\Database\Tests\Models\Post;
use Arpon\Database\Tests\Models\Profile;
use Arpon\Database\Tests\Models\Comment;
use Arpon\Database\Eloquent\Model;
use Exception;

/**
 * Comprehensive Eloquent Relationship Tests
 * 
 * Converted from test-relationships.php to professional PHPUnit format
 */
abstract class BaseRelationshipTest extends TestCase
{


    public function setUp(): void
    {
        parent::setUp();

    }

    /**
     * @test
     */
    public function it_creates_test_data_with_relationships()
    {
        // Create a user
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
        
        // Create user profile
        $profile = new Profile([
            'bio' => 'Software developer and tech enthusiast',
            'website' => 'https://johndoe.com'
        ]);
        $profile->user_id = $user->id;
        $profile->save();
        
        // Create posts for the user
        $post1 = Post::create([
            'title' => 'My First Post',
            'content' => 'This is the content of my first post.',
            'user_id' => $user->id
        ]);
        
        $post2 = Post::create([
            'title' => 'Another Great Post',
            'content' => 'More interesting content here.',
            'user_id' => $user->id
        ]);

        // Verify data creation
        $this->assertDatabaseHas('users', ['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->assertDatabaseHas('profiles', ['bio' => 'Software developer and tech enthusiast']);
        $this->assertDatabaseHas('posts', ['title' => 'My First Post']);
        $this->assertDatabaseHas('posts', ['title' => 'Another Great Post']);
        
        // Verify relationships
        $this->assertEquals($user->id, $profile->user_id);
        $this->assertEquals($user->id, $post1->user_id);
        $this->assertEquals($user->id, $post2->user_id);
    }

    /**
     * @test
     */
    public function it_handles_has_one_relationships()
    {
        $this->createBasicTestData();
        
        $user = User::find(1);
        $profile = $user->profile;
        
        $this->assertNotNull($profile);
        $this->assertInstanceOf(Profile::class, $profile);
        $this->assertEquals('Software developer and tech enthusiast', $profile->bio);
        $this->assertEquals($user->id, $profile->user_id);
    }

    /**
     * @test
     */
    public function it_handles_belongs_to_relationships()
    {
        $this->createBasicTestData();
        
        $profile = Profile::first();
        $user = $profile->user;
        
        $this->assertNotNull($user);
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals($profile->user_id, $user->id);
    }

    /**
     * @test
     */
    public function it_handles_has_many_relationships()
    {
        $this->createBasicTestData();
        
        $user = User::find(1);
        $posts = $user->posts;
        
        $this->assertGreaterThan(0, $posts->count());
        $this->assertContains('My First Post', $posts->pluck('title')->all());
        $this->assertContains('Another Great Post', $posts->pluck('title')->all());
        
        foreach ($posts as $post) {
            $this->assertEquals($user->id, $post->user_id);
        }
    }

    /**
     * @test
     */
    public function it_counts_relationships()
    {
        $this->createBasicTestData();
        $this->createCommentTestData();
        
        $post = Post::find(1);
        $commentCount = $post->comments()->count();
        
        $this->assertGreaterThan(0, $commentCount);
        $this->assertEquals($post->comments->count(), $commentCount);
    }

    /**
     * @test
     */
    public function it_creates_models_through_relationships()
    {
        $this->createBasicTestData();
        
        $user = User::find(1);
        $initialPostCount = $user->posts()->count();
        
        // Create a new post through the relationship
        $newPost = $user->posts()->create([
            'title' => 'Created via Relationship',
            'content' => 'This post was created through the hasMany relationship.'
        ]);
        
        $this->assertNotNull($newPost);
        $this->assertEquals($user->id, $newPost->user_id);
        $this->assertEquals($initialPostCount + 1, $user->posts()->count());
        $this->assertDatabaseHas('posts', [
            'title' => 'Created via Relationship',
            'user_id' => $user->id
        ]);
    }

    /**
     * @test
     */
    public function it_handles_association_and_dissociation()
    {
        $this->createBasicTestData();
        
        $post = Post::find(1);
        $originalUserId = $post->user_id;
        
        // Dissociate
        $post->user()->dissociate();
        $this->assertNull($post->user_id);
        
        // Re-associate
        $user = User::find(1);
        $post->user()->associate($user);
        $this->assertEquals($user->id, $post->user_id);
        
        // Save and verify
        $post->save();
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'user_id' => $user->id]);
    }

    /**
     * @test
     */
    public function it_handles_deep_nested_relationships()
    {
        $this->createBasicTestData();
        $this->createCommentTestData();
        
        // Create additional users and content
        $user2 = User::create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);
        $user3 = User::create(['name' => 'Bob Johnson', 'email' => 'bob@example.com']);
        
        $janePost = Post::create(['title' => 'Jane\'s Post', 'content' => 'Content by Jane', 'user_id' => $user2->id]);
        $bobPost = Post::create(['title' => 'Bob\'s Post', 'content' => 'Content by Bob', 'user_id' => $user3->id]);
        
        $totalUsers = User::all()->count();
        $totalPosts = Post::all()->count();
        
        $this->assertEquals(3, $totalUsers);
        $this->assertGreaterThanOrEqual(4, $totalPosts); // At least 2 from basic data + 2 new
    }

    /**
     * @test
     */
    public function it_queries_relationships_with_conditions()
    {
        $this->createBasicTestData();
        
        $user = User::find(1);
        
        // Test relationship with where conditions
        $postsWithSpecificTitle = $user->posts()->where('title', 'like', '%First%')->get();
        $this->assertEquals(1, $postsWithSpecificTitle->count());
        
        // Test relationship with multiple conditions
        $recentPosts = $user->posts()
            ->where('title', '!=', '')
            ->orderBy('created_at', 'desc')
            ->get();
        
        $this->assertGreaterThan(0, $recentPosts->count());
    }

    /**
     * @test
     */
    public function it_handles_relationship_collection_operations()
    {
        $this->createBasicTestData();
        
        $user = User::find(1);
        $posts = $user->posts;
        
        // Test collection filtering
        $filteredPosts = $posts->filter(function($post) {
            return strlen($post->title) > 10;
        });
        
        // Test collection mapping
        $titleLengths = $posts->map(function($post) {
            return strlen($post->title);
        });
        
        // Test collection sorting
        $sortedPosts = $posts->sortBy('title');
        
        $this->assertInstanceOf('Arpon\Database\Eloquent\Collection', $filteredPosts);
        $this->assertInstanceOf('Arpon\Database\Support\Collection', $titleLengths);
        $this->assertEquals($posts->count(), $sortedPosts->count());
    }

    /**
     * @test
     */
    public function it_tests_make_vs_create_methods()
    {
        $this->createBasicTestData();
        
        $user = User::find(1);
        
        // Test make (doesn't save to database)
        $newPost = $user->posts()->make([
            'title' => 'Made Post (Not Saved)',
            'content' => 'This post is made but not saved'
        ]);
        
        $this->assertFalse($newPost->exists);
        $this->assertNull($newPost->id);
        $this->assertEquals($user->id, $newPost->user_id);
        
        // Now save it
        $newPost->save();
        
        $this->assertTrue($newPost->exists);
        $this->assertNotNull($newPost->id);
        
        // Test makeMany
        $multiplePosts = $user->posts()->makeMany([
            ['title' => 'Bulk Made 1', 'content' => 'Content 1'],
            ['title' => 'Bulk Made 2', 'content' => 'Content 2']
        ]);
        
        $this->assertEquals(2, $multiplePosts->count());
        
        foreach ($multiplePosts as $post) {
            $this->assertFalse($post->exists);
            $this->assertEquals($user->id, $post->user_id);
        }
    }

    /**
     * @test
     */
    public function it_handles_relationship_existence_queries()
    {
        $this->createBasicTestData();
        
        $usersWithPosts = User::all()->filter(function($user) {
            return $user->posts->count() > 0;
        });
        
        $usersWithProfiles = User::all()->filter(function($user) {
            return $user->profile !== null;
        });
        
        $this->assertGreaterThan(0, $usersWithPosts->count());
        $this->assertGreaterThan(0, $usersWithProfiles->count());
    }

    /**
     * @test
     */
    public function it_handles_counting_edge_cases()
    {
        $user4 = User::create(['name' => 'Empty User', 'email' => 'empty@example.com']);
        $emptyPostCount = $user4->posts()->count();
        
        $this->assertEquals(0, $emptyPostCount);
        
        // Test counting after creating and deleting
        $postToDelete = Post::create(['title' => 'Delete Me', 'content' => 'Will be deleted', 'user_id' => $user4->id]);
        $beforeCount = $user4->posts()->count();
        
        $postToDelete->delete();
        $user4->unsetRelation('posts'); // Clear cache
        $afterCount = $user4->posts()->count();
        
        $this->assertEquals(1, $beforeCount);
        $this->assertEquals(0, $afterCount);
    }

    /**
     * @test
     */
    public function it_handles_complex_relationship_chains()
    {
        $this->createBasicTestData();
        $this->createCommentTestData();
        
        $user = User::find(1);
        
        // Test chaining: User -> Posts -> Comments
        $totalCommentsOnUserPosts = 0;
        foreach ($user->posts as $post) {
            $totalCommentsOnUserPosts += $post->comments->count();
        }
        
        // Alternative approach: direct counting
        $directCommentCount = 0;
        $userPosts = $user->posts;
        foreach ($userPosts as $post) {
            $directCommentCount += $post->comments()->count();
        }
        
        $this->assertEquals($totalCommentsOnUserPosts, $directCommentCount);
    }

    /**
     * @test
     */
    public function it_handles_relationship_memory_efficiency()
    {
        $this->createBasicTestData();
        
        $user = User::find(1);
        
        // First access - should load from database
        $posts1 = $user->posts;
        $firstCount = $posts1->count();
        
        // Second access - should use cached relationship
        $posts2 = $user->posts;
        $secondCount = $posts2->count();
        
        $this->assertEquals($firstCount, $secondCount);
        $this->assertTrue($user->relationLoaded('posts'));
        
        // Test unloading relationship
        $user->unsetRelation('posts');
        $this->assertFalse($user->relationLoaded('posts'));
        
        // Re-access should reload
        $posts3 = $user->posts;
        $thirdCount = $posts3->count();
        
        $this->assertEquals($firstCount, $thirdCount);
    }

    /**
     * @test
     */
    public function it_maintains_relationship_data_integrity()
    {
        $user = User::create(['name' => 'Integrity Test', 'email' => 'integrity@example.com']);
        
        $profile = new Profile([
            'bio' => 'Testing data integrity',
            'website' => 'https://integrity.test'
        ]);
        $profile->user_id = $user->id;
        $profile->save();
        
        $post = Post::create([
            'title' => 'Integrity Post',
            'content' => 'Testing relationships',
            'user_id' => $user->id
        ]);
        
        // Test bidirectional relationship consistency
        $userFromProfile = $profile->user;
        $profileFromUser = $user->profile;
        
        $this->assertEquals($user->id, $userFromProfile->id);
        $this->assertEquals($profile->id, $profileFromUser->id);
        $this->assertEquals($user->name, $userFromProfile->name);
        $this->assertEquals($profile->bio, $profileFromUser->bio);
    }

    /**
     * @test
     */
    public function it_handles_mass_relationship_operations()
    {
        $user = User::create(['name' => 'Mass Test User', 'email' => 'mass@example.com']);
        
        // Create multiple posts in one go
        $posts = $user->posts()->createMany([
            ['title' => 'Mass Post 1', 'content' => 'Content 1'],
            ['title' => 'Mass Post 2', 'content' => 'Content 2'],
            ['title' => 'Mass Post 3', 'content' => 'Content 3'],
        ]);
        
        $this->assertEquals(3, $posts->count());
        
        // Verify each post has correct user_id
        foreach ($posts as $post) {
            $this->assertEquals($user->id, $post->user_id);
        }
    }

    /**
     * @test
     */
    public function it_handles_relationship_attribute_access()
    {
        $this->createBasicTestData();
        
        $user = User::find(1);
        $firstPost = $user->posts->first();
        $postUser = $firstPost->user;
        
        $this->assertEquals($user->id, $postUser->id);
        
        // Test nested attribute access
        $profile = $user->profile;
        if ($profile) {
            $profileUser = $profile->user;
            $this->assertEquals($user->name, $profileUser->name);
        }
        
        // Test relationship data modification
        $originalTitle = $firstPost->title;
        $firstPost->title = "Modified Title";
        
        // Relationship should still work after modification
        $userAgain = $firstPost->user;
        $this->assertEquals($user->id, $userAgain->id);
        
        // Restore original title
        $firstPost->title = $originalTitle;
    }

    /**
     * @test
     */
    public function it_handles_edge_case_scenarios()
    {
        $emptyUser = User::create(['name' => 'Empty Relations', 'email' => 'empty@relations.com']);
        
        // Test accessing non-existent relationships
        $nonExistentPosts = $emptyUser->posts;
        $this->assertEquals(0, $nonExistentPosts->count());
        
        $nonExistentProfile = $emptyUser->profile;
        $this->assertNull($nonExistentProfile);
        
        // Test relationship after creating data
        $newPost = $emptyUser->posts()->create(['title' => 'First Post', 'content' => 'Content']);
        
        // Clear the relationship cache to get fresh data
        $emptyUser->unsetRelation('posts');
        $updatedPosts = $emptyUser->posts;
        
        $this->assertEquals(1, $updatedPosts->count());
        
        // Test relationship counting edge cases
        $zeroCount = $emptyUser->posts()->where('title', 'NonExistent')->count();
        $this->assertEquals(0, $zeroCount);
    }

    /**
     * Helper method to create basic test data
     */
    protected function createBasicTestData()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
        
        $profile = new Profile([
            'bio' => 'Software developer and tech enthusiast',
            'website' => 'https://johndoe.com'
        ]);
        $profile->user_id = $user->id;
        $profile->save();
        
        Post::create([
            'title' => 'My First Post',
            'content' => 'This is the content of my first post.',
            'user_id' => $user->id
        ]);
        
        Post::create([
            'title' => 'Another Great Post',
            'content' => 'More interesting content here.',
            'user_id' => $user->id
        ]);
    }

    /**
     * Helper method to create comment test data
     */
    protected function createCommentTestData()
    {
        $post1 = Post::find(1);
        $post2 = Post::find(2);
        
        if ($post1) {
            Comment::create(['content' => 'Great post!', 'post_id' => $post1->id]);
            Comment::create(['content' => 'Very informative.', 'post_id' => $post1->id]);
        }
        
        if ($post2) {
            Comment::create(['content' => 'Looking forward to more posts.', 'post_id' => $post2->id]);
        }
    }

    /**
     * @test
     */
    public function it_eager_loads_with_constraints()
    {
        // Create a user
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        // Create published and unpublished posts for the user
        $user->posts()->create([
            'title' => 'Published Post',
            'content' => 'This post is published.',
            'published' => true,
        ]);

        $user->posts()->create([
            'title' => 'Unpublished Post',
            'content' => 'This post is not published.',
            'published' => false,
        ]);

        // Eager load the user with only their unpublished posts
        $userWithPosts = User::with(['posts' => function ($query) {
            $query->where('published', false);
        }])->find($user->id);

        $this->assertCount(1, $userWithPosts->posts);
        $this->assertEquals('Unpublished Post', $userWithPosts->posts->first()->title);
    }
}