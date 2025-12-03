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
        $user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        
        $profile = new Profile(['bio' => 'Software developer and tech enthusiast', 'website' => 'https://johndoe.com']);
        $profile->user_id = $user->id;
        $profile->save();
        
        $post1 = Post::create(['title' => 'My First Post', 'content' => 'Content of first post.', 'user_id' => $user->id]);
        $post2 = Post::create(['title' => 'Another Great Post', 'content' => 'More content here.', 'user_id' => $user->id]);

        $this->assertDatabaseHas('users', ['name' => 'John Doe']);
        $this->assertDatabaseHas('profiles', ['bio' => 'Software developer and tech enthusiast']);
        $this->assertDatabaseHas('posts', ['title' => 'My First Post']);
        $this->assertEquals($user->id, $profile->user_id);
        $this->assertEquals($user->id, $post1->user_id);
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
        
        $post->user()->dissociate();
        $this->assertNull($post->user_id);
        
        $user = User::find(1);
        $post->user()->associate($user);
        $this->assertEquals($user->id, $post->user_id);
        
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
        
        $user2 = User::create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);
        $user3 = User::create(['name' => 'Bob Johnson', 'email' => 'bob@example.com']);
        
        Post::create(['title' => 'Jane\'s Post', 'content' => 'Content by Jane', 'user_id' => $user2->id]);
        Post::create(['title' => 'Bob\'s Post', 'content' => 'Content by Bob', 'user_id' => $user3->id]);
        
        $this->assertEquals(3, User::all()->count());
        $this->assertGreaterThanOrEqual(4, Post::all()->count());
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
        
        $firstCount = $user->posts->count();
        $secondCount = $user->posts->count(); // Should use cache
        
        $this->assertEquals($firstCount, $secondCount);
        $this->assertTrue($user->relationLoaded('posts'));
        
        $user->unsetRelation('posts');
        $this->assertFalse($user->relationLoaded('posts'));
        
        $thirdCount = $user->posts->count(); // Should reload
        $this->assertEquals($firstCount, $thirdCount);
    }

    /**
     * @test
     */
    public function it_maintains_relationship_data_integrity()
    {
        $user = User::create(['name' => 'Integrity Test', 'email' => 'integrity@example.com']);
        
        $profile = new Profile(['bio' => 'Testing data integrity', 'website' => 'https://integrity.test']);
        $profile->user_id = $user->id;
        $profile->save();
        
        Post::create(['title' => 'Integrity Post', 'content' => 'Testing relationships', 'user_id' => $user->id]);
        
        // Test bidirectional consistency
        $this->assertEquals($user->id, $profile->user->id);
        $this->assertEquals($profile->id, $user->profile->id);
        $this->assertEquals($user->name, $profile->user->name);
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
        
        $this->assertCount(0, $emptyUser->posts);
        $this->assertNull($emptyUser->profile);
        
        $emptyUser->posts()->create(['title' => 'First Post', 'content' => 'Content']);
        $emptyUser->unsetRelation('posts');
        
        $this->assertCount(1, $emptyUser->posts);
        $this->assertEquals(0, $emptyUser->posts()->where('title', 'NonExistent')->count());
    }

    /**
     * Helper method to create basic test data (optimized)
     */
    protected function createBasicTestData()
    {
        $user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        
        $profile = new Profile(['bio' => 'Software developer and tech enthusiast', 'website' => 'https://johndoe.com']);
        $profile->user_id = $user->id;
        $profile->save();
        
        Post::create(['title' => 'My First Post', 'content' => 'Content of first post.', 'user_id' => $user->id]);
        Post::create(['title' => 'Another Great Post', 'content' => 'More content here.', 'user_id' => $user->id]);
    }

    /**
     * Helper method to create comment test data (optimized)
     */
    protected function createCommentTestData()
    {
        if ($post1 = Post::find(1)) {
            Comment::create(['content' => 'Great post!', 'post_id' => $post1->id]);
            Comment::create(['content' => 'Very informative.', 'post_id' => $post1->id]);
        }
        
        if ($post2 = Post::find(2)) {
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

    /**
     * @test
     */
    public function it_handles_has_one_creation_and_updates()
    {
        $user = User::create(['name' => 'Profile User', 'email' => 'profile@example.com']);
        
        // Create profile through relationship
        $profile = $user->profile()->create([
            'bio' => 'Initial bio',
            'website' => 'https://example.com'
        ]);
        
        $this->assertEquals($user->id, $profile->user_id);
        $this->assertEquals('Initial bio', $profile->bio);
        
        // Update profile through relationship
        $user->profile()->update(['bio' => 'Updated bio']);
        
        $profile->refresh();
        $this->assertEquals('Updated bio', $profile->bio);
    }

    /**
     * @test
     */
    public function it_handles_belongs_to_creation()
    {
        $user = User::create(['name' => 'Post Owner', 'email' => 'owner@example.com']);
        
        $post = new Post(['title' => 'New Post', 'content' => 'Content here']);
        $post->user()->associate($user);
        $post->save();
        
        $this->assertEquals($user->id, $post->user_id);
        $this->assertInstanceOf(User::class, $post->user);
    }

    /**
     * @test
     */
    public function it_handles_has_many_where_clauses()
    {
        $this->createBasicTestData();
        
        $user = User::find(1);
        
        // Test whereHas equivalent through manual filtering
        $postsWithFirst = $user->posts()->where('title', 'like', '%First%')->get();
        $this->assertCount(1, $postsWithFirst);
        
        // Test relationship count with conditions
        $countWithCondition = $user->posts()->where('content', '!=', '')->count();
        $this->assertGreaterThan(0, $countWithCondition);
    }

    /**
     * @test
     */
    public function it_handles_empty_relationship_collections()
    {
        $user = User::create(['name' => 'No Content User', 'email' => 'nocontent@example.com']);
        
        $posts = $user->posts;
        $this->assertCount(0, $posts);
        $this->assertInstanceOf('Arpon\Database\Eloquent\Collection', $posts);
        
        $profile = $user->profile;
        $this->assertNull($profile);
    }

    /**
     * @test
     */
    public function it_handles_relationship_deletes()
    {
        $this->createBasicTestData();
        
        $user = User::find(1);
        $postCount = $user->posts()->count();
        
        // Delete all user's posts
        $user->posts()->delete();
        
        $this->assertEquals(0, $user->posts()->count());
        $remainingPosts = Post::all()->count();
        $this->assertEquals(0, $remainingPosts - ($postCount - $postCount));
    }

    /**
     * @test
     */
    public function it_handles_relationship_updates()
    {
        $this->createBasicTestData();
        
        $user = User::find(1);
        
        // Update all user's posts
        $user->posts()->update(['content' => 'Updated content']);
        
        $posts = $user->posts()->get();
        foreach ($posts as $post) {
            $this->assertEquals('Updated content', $post->content);
        }
    }

    /**
     * @test
     */
    public function it_handles_relationship_first_or_create()
    {
        $user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);
        
        // First call creates the profile
        $profile1 = $user->profile()->firstOrCreate(
            [],
            ['bio' => 'First bio', 'website' => 'https://first.com']
        );
        
        $this->assertEquals('First bio', $profile1->bio);
        
        // Second call finds existing profile
        $profile2 = $user->profile()->firstOrCreate(
            [],
            ['bio' => 'Second bio', 'website' => 'https://second.com']
        );
        
        $this->assertEquals($profile1->id, $profile2->id);
        $this->assertEquals('First bio', $profile2->bio);
    }

    /**
     * @test
     */
    public function it_handles_relationship_update_or_create()
    {
        $user = User::create(['name' => 'Update Test', 'email' => 'update@example.com']);
        
        // First call creates
        $profile1 = $user->profile()->updateOrCreate(
            [],
            ['bio' => 'Original bio', 'website' => 'https://original.com']
        );
        
        $this->assertEquals('Original bio', $profile1->bio);
        
        // Second call updates
        $profile2 = $user->profile()->updateOrCreate(
            [],
            ['bio' => 'Updated bio', 'website' => 'https://updated.com']
        );
        
        $this->assertEquals($profile1->id, $profile2->id);
        $this->assertEquals('Updated bio', $profile2->bio);
    }

    /**
     * @test
     */
    public function it_handles_relationship_pluck()
    {
        $this->createBasicTestData();
        
        $user = User::find(1);
        $titles = $user->posts()->pluck('title');
        
        $this->assertInstanceOf('Arpon\Database\Support\Collection', $titles);
        $this->assertGreaterThan(0, $titles->count());
        $this->assertContains('My First Post', $titles->all());
    }

    /**
     * @test
     */
    public function it_handles_relationship_chunk_processing()
    {
        $this->createBasicTestData();
        
        $user = User::find(1);
        $processedCount = 0;
        
        $user->posts()->chunk(1, function ($posts) use (&$processedCount) {
            foreach ($posts as $post) {
                $processedCount++;
            }
        });
        
        $this->assertEquals($user->posts()->count(), $processedCount);
    }

    /**
     * @test
     */
    public function it_handles_relationship_exists_checks()
    {
        $this->createBasicTestData();
        
        $user = User::find(1);
        
        $this->assertTrue($user->posts()->exists());
        $this->assertTrue($user->posts()->where('title', 'My First Post')->exists());
        $this->assertFalse($user->posts()->where('title', 'Non Existent')->exists());
    }

    /**
     * @test
     */
    public function it_handles_relationship_ordering()
    {
        $user = User::create(['name' => 'Order Test', 'email' => 'order@example.com']);
        
        $user->posts()->create(['title' => 'Z Post', 'content' => 'Last']);
        $user->posts()->create(['title' => 'A Post', 'content' => 'First']);
        $user->posts()->create(['title' => 'M Post', 'content' => 'Middle']);
        
        $orderedPosts = $user->posts()->orderBy('title', 'asc')->get();
        
        $this->assertEquals('A Post', $orderedPosts->first()->title);
        $this->assertEquals('Z Post', $orderedPosts->last()->title);
    }

    /**
     * @test
     */
    public function it_handles_relationship_with_multiple_constraints()
    {
        $user = User::create(['name' => 'Multi Test', 'email' => 'multi@example.com']);
        
        $user->posts()->create(['title' => 'Published Long', 'content' => 'Long content here', 'published' => true]);
        $user->posts()->create(['title' => 'Draft Short', 'content' => 'Short', 'published' => false]);
        $user->posts()->create(['title' => 'Published Short', 'content' => 'Brief', 'published' => true]);
        
        $results = $user->posts()
            ->where('published', true)
            ->where('title', 'like', '%Short%')
            ->get();
        
        $this->assertCount(1, $results);
        $this->assertEquals('Published Short', $results->first()->title);
    }

    /**
     * @test
     */
    public function it_handles_relationship_aggregates()
    {
        $this->createBasicTestData();
        
        $user = User::find(1);
        
        $count = $user->posts()->count();
        $this->assertGreaterThan(0, $count);
        
        // Create posts with different content lengths for testing
        $user->posts()->update(['content' => 'test']);
        $maxLength = $user->posts()->max('title');
        $minLength = $user->posts()->min('title');
        
        $this->assertNotNull($maxLength);
        $this->assertNotNull($minLength);
    }

    /**
     * @test
     */
    public function it_handles_nested_relationship_creation()
    {
        $user = User::create(['name' => 'Nested User', 'email' => 'nested@example.com']);
        
        $post = $user->posts()->create(['title' => 'Post with Comments', 'content' => 'Content']);
        
        $comment1 = $post->comments()->create(['content' => 'First comment']);
        $comment2 = $post->comments()->create(['content' => 'Second comment']);
        
        $this->assertCount(2, $post->comments);
        $this->assertEquals($post->id, $comment1->post_id);
        $this->assertEquals($post->id, $comment2->post_id);
    }

    /**
     * @test
     */
    public function it_handles_relationship_with_default_values()
    {
        $user = User::create(['name' => 'Default Test', 'email' => 'default@example.com']);
        
        $post = $user->posts()->create([
            'title' => 'Default Post',
            'content' => 'Content'
            // published should default to 0/false
        ]);
        
        $this->assertFalse((bool)$post->published);
    }

    /**
     * @test
     */
    public function it_handles_relationship_save_method()
    {
        $user = User::create(['name' => 'Save Test', 'email' => 'save@example.com']);
        
        $post = new Post(['title' => 'Saved Post', 'content' => 'Saved content']);
        $user->posts()->save($post);
        
        $this->assertEquals($user->id, $post->user_id);
        $this->assertNotNull($post->id);
        $this->assertTrue($post->exists);
    }

    /**
     * @test
     */
    public function it_handles_relationship_save_many_method()
    {
        $user = User::create(['name' => 'SaveMany Test', 'email' => 'savemany@example.com']);
        
        $post1 = new Post(['title' => 'First', 'content' => 'Content 1']);
        $post2 = new Post(['title' => 'Second', 'content' => 'Content 2']);
        
        $user->posts()->saveMany([$post1, $post2]);
        
        $this->assertEquals($user->id, $post1->user_id);
        $this->assertEquals($user->id, $post2->user_id);
        $this->assertCount(2, $user->posts);
    }
}