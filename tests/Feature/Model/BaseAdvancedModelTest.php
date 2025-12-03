<?php

namespace Arpon\Database\Tests\Feature\Model;

use Arpon\Database\Tests\Models\User;
use Arpon\Database\Tests\TestCase;
use Arpon\Database\Eloquent\Model;
use Arpon\Database\Eloquent\Concerns\SoftDeletes;
use Arpon\Database\Eloquent\Scopes\Scope;
use Arpon\Database\Eloquent\EloquentBuilder;

// --- Test Models ---

class Image extends Model {
    protected array $fillable = ['url', 'imageable_id', 'imageable_type'];
    public function imageable() {
        return $this->morphTo();
    }
}

class Role extends Model {
    protected array $fillable = ['name'];
    public function users() {
        return $this->belongsToMany(User::class, 'role_user');
    }
}

class SoftDeleteUser extends Model {
    use SoftDeletes;
    protected ?string $table = 'users';
    protected array $fillable = ['name', 'email', 'age'];
}

class ActiveScope implements Scope {
    public function apply(EloquentBuilder $builder, Model $model): void {
        $builder->where('is_active', 1);
    }
}

class ScopedUser extends Model {
    protected ?string $table = 'users';
    protected array $fillable = ['name', 'email', 'is_active'];
    protected static function boot(): void {
        parent::boot();
        static::addGlobalScope(new ActiveScope);
    }
}

class PostWithImage extends Model {
    protected ?string $table = 'posts';
    protected array $fillable = ['title', 'content'];
    public function images() {
        return $this->morphMany(Image::class, 'imageable');
    }
}

// --- Test Class ---

abstract class BaseAdvancedModelTest extends TestCase
{
    protected function migrateTestDatabase(): void
    {
        parent::migrateTestDatabase();

        $connection = $this->getConnection();
        
        // Create images table for polymorphic tests
        $connection->statement('DROP TABLE IF EXISTS images');
        $sql = $this->connection === 'mysql' 
            ? 'CREATE TABLE images (id INT AUTO_INCREMENT PRIMARY KEY, url VARCHAR(255), imageable_id INT, imageable_type VARCHAR(255), created_at DATETIME, updated_at DATETIME) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            : 'CREATE TABLE images (id INTEGER PRIMARY KEY AUTOINCREMENT, url VARCHAR(255), imageable_id INTEGER, imageable_type VARCHAR(255), created_at DATETIME, updated_at DATETIME)';
        $connection->statement($sql);

        // Create roles and pivot table for many-to-many tests
        $connection->statement('DROP TABLE IF EXISTS roles');
        $sql = $this->connection === 'mysql'
            ? 'CREATE TABLE roles (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), created_at DATETIME, updated_at DATETIME) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            : 'CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255), created_at DATETIME, updated_at DATETIME)';
        $connection->statement($sql);

        $connection->statement('DROP TABLE IF EXISTS role_user');
        $sql = $this->connection === 'mysql'
            ? 'CREATE TABLE role_user (user_id INT, role_id INT, PRIMARY KEY (user_id, role_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            : 'CREATE TABLE role_user (user_id INTEGER, role_id INTEGER, PRIMARY KEY (user_id, role_id))';
        $connection->statement($sql);
    }

    /** @test */
    public function it_handles_soft_deletes()
    {
        $this->addDeletedAtColumn();

        $user = SoftDeleteUser::create(['name' => 'Soft Delete Me', 'email' => 'soft@example.com']);
        $this->assertNull($user->deleted_at);
        
        $user->delete();
        $this->assertNotNull($user->deleted_at);
        $this->assertTrue($user->trashed());
        $this->assertNull(SoftDeleteUser::find($user->id));
        
        // withTrashed should find it
        $deletedUser = SoftDeleteUser::withTrashed()->find($user->id);
        $this->assertNotNull($deletedUser);
        $this->assertEquals($user->id, $deletedUser->id);
        
        // Restore
        $this->assertTrue($deletedUser->restore());
        $this->assertNull($deletedUser->deleted_at);
        $this->assertFalse($deletedUser->trashed());
        $this->assertNotNull(SoftDeleteUser::find($user->id));
    }
    
    /**
     * Helper to add deleted_at column (idempotent)
     */
    private function addDeletedAtColumn(): void
    {
        $sql = $this->connection === 'mysql' 
            ? 'ALTER TABLE users ADD COLUMN deleted_at TIMESTAMP NULL'
            : 'ALTER TABLE users ADD COLUMN deleted_at DATETIME';
        
        try {
            $this->getConnection()->statement($sql);
        } catch (\Exception $e) {
            // Column already exists, ignore
        }
    }

    /** @test */
    public function it_handles_global_scopes()
    {
        ScopedUser::create(['name' => 'Active User', 'email' => 'active@example.com', 'is_active' => 1]);
        ScopedUser::create(['name' => 'Inactive User', 'email' => 'inactive@example.com', 'is_active' => 0]);
        
        $users = ScopedUser::all();
        $this->assertCount(1, $users);
        $this->assertEquals('Active User', $users->first()->name);
        
        // Without global scope
        $allUsers = ScopedUser::withoutGlobalScope(ActiveScope::class)->get();
        $this->assertGreaterThanOrEqual(2, $allUsers->count());
    }

    /** @test */
    public function it_handles_polymorphic_relations()
    {
        $post = PostWithImage::create(['title' => 'Post with Image', 'content' => 'Content']);
        $image = $post->images()->create(['url' => 'http://example.com/image.jpg']);
        
        $this->assertEquals($post->id, $image->imageable_id);
        $this->assertEquals(PostWithImage::class, $image->imageable_type);
        $this->assertCount(1, $post->images);
        $this->assertEquals('http://example.com/image.jpg', $post->images->first()->url);
        
        // Test inverse
        $this->assertInstanceOf(PostWithImage::class, $image->imageable);
        $this->assertEquals($post->id, $image->imageable->id);
    }

    /** @test */
    public function it_handles_many_to_many_relations()
    {
        $user = UserWithRoles::create(['name' => 'Role User', 'email' => 'role@example.com']);
        $role1 = Role::create(['name' => 'Admin']);
        $role2 = Role::create(['name' => 'Editor']);
        
        // Test attach
        $user->roles()->attach([$role1->id, $role2->id]);
        $this->assertCount(2, $user->roles);
        $this->assertEquals(['Admin', 'Editor'], $user->roles->pluck('name')->sort()->values()->all());
        
        // Test detach
        $user->roles()->detach($role1->id);
        $this->loadRelationship($user, 'roles');
        $this->assertCount(1, $user->roles);
        $this->assertEquals('Editor', $user->roles->first()->name);
        
        // Test sync
        $user->roles()->sync([$role1->id]);
        $this->loadRelationship($user, 'roles');
        $this->assertCount(1, $user->roles);
        $this->assertEquals('Admin', $user->roles->first()->name);
    }
    
    protected function loadRelationship($model, $relation) {
        $model->unsetRelation($relation);
        return $model->$relation;
    }

    /** @test */
    public function it_can_update_and_refresh_a_model()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30
        ]);

        $user->name = 'Jane Doe';
        $this->assertEquals('Jane Doe', $user->name);

        $user->refresh();
        $this->assertEquals('John Doe', $user->name);
        
        $user->update(['name' => 'Johnny Doe']);
        $this->assertEquals('Johnny Doe', $user->name);

        $user->refresh();
        $this->assertEquals('Johnny Doe', $user->name);
    }
}

class UserWithRoles extends User {
    protected ?string $table = 'users';
    public function roles() {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }
}
