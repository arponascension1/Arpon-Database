# 🚀 Arpon Database

[![Version](https://img.shields.io/badge/version-1.1.0-blue.svg)](https://github.com/arponascension1/Arpon-Database/releases/tag/v1.1.0)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-8.0%2B-blue.svg)](https://php.net)
[![Build](https://img.shields.io/badge/build-passing-brightgreen.svg)](https://github.com/arponascension1/Arpon-Database)
[![Laravel Compatible](https://img.shields.io/badge/Laravel-Compatible-red.svg)](https://laravel.com)

> **A powerful, Laravel-compatible PHP database layer with advanced ORM, Query Builder, and Schema Builder for MySQL & SQLite.**

## ✨ Why Arpon Database?

🎯 **Laravel Compatible** - Drop-in replacement for Laravel's database layer  
🔧 **No Framework Required** - Works standalone or with any PHP project  
🏗️ **Advanced Schema Builder** - 25+ column types with `foreignId()->constrained()` support  
🔗 **Complete ORM** - Full Eloquent relationships including polymorphic  
⚡ **High Performance** - Optimized queries with proper caching  
🛡️ **Production Ready** - Thoroughly tested and battle-hardened  

## 📦 Quick Install

```bash
composer require arpon/database
```

## 🚀 Quick Start

### 1. Setup (30 seconds)

```php
<?php
require_once 'vendor/autoload.php';

use Arpon\Database\Capsule\Manager as DB;

$capsule = new DB();
$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => 'localhost',
    'database'  => 'your_db',
    'username'  => 'your_user',
    'password'  => 'your_pass',
    'charset'   => 'utf8mb4',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

// 🎉 Ready to use!
```

### 2. Create Your First Model

```php
use Arpon\Database\Eloquent\Model;

class User extends Model {
    protected array $fillable = ['name', 'email', 'password'];
    
    public function posts() {
        return $this->hasMany(Post::class);
    }
}

// ✅ Works perfectly in v1.0.0!
$user = User::create([
    'name' => 'John Doe', 
    'email' => 'john@example.com'
]);
```

### 3. Query Like a Pro

```php
// Laravel-style static methods
$users = DB::table('users')->where('active', true)->get();
$schema = DB::schema();
$connection = DB::connection();

// Eloquent relationships
$user = User::with('posts')->find(1);
$posts = $user->posts()->where('published', true)->get();
```

## 🏗️ Features

<table>
<tr>
<td width="50%">

### 🔧 Database Operations
- **Query Builder** - Fluent, expressive queries
- **Schema Builder** - Database migrations & structure
- **Raw Queries** - When you need full SQL control
- **Transactions** - ACID compliance with rollbacks
- **Multiple Connections** - MySQL, SQLite support

</td>
<td width="50%">

### 🔗 Advanced ORM
- **Eloquent Models** - Laravel-compatible ORM
- **Relationships** - All 11 relationship types
- **Polymorphic Relations** - Flexible model associations  
- **Through Relations** - Access distant relationships
- **Soft Deletes** - Non-destructive data removal

</td>
</tr>
</table>

## 🎯 Laravel Compatibility

Perfect drop-in replacement for Laravel's database layer:

```php
// All these work exactly like Laravel
DB::table('users')->get();
DB::select('SELECT * FROM users WHERE active = ?', [1]);
DB::transaction(function() { /* your code */ });

User::create($data);
User::with('posts')->get();
User::where('email', $email)->first();
```

## 📚 Documentation

### Schema Building

```php
use Arpon\Database\Capsule\Manager as DB;

DB::schema()->create('users', function($table) {
    $table->increments('id');
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->rememberToken();
    $table->timestamps();
});

DB::schema()->create('posts', function($table) {
    $table->id();  // Laravel-style big integer primary key
    $table->string('title');
    $table->text('content');
    $table->foreignId('user_id')->constrained()->onDelete('cascade');  // ✨ NEW in v1.1.0!
    $table->timestamps();
});
```

### Relationships

```php
class User extends Model {
    public function posts() {
        return $this->hasMany(Post::class);
    }
    
    public function profile() {
        return $this->hasOne(Profile::class);
    }
    
    public function roles() {
        return $this->belongsToMany(Role::class);
    }
}

class Post extends Model {
    public function user() {
        return $this->belongsTo(User::class);
    }
    
    public function comments() {
        return $this->hasMany(Comment::class);
    }
    
    public function tags() {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}

// Usage
$user = User::with(['posts.comments', 'profile'])->find(1);
$userPosts = $user->posts;
$firstPost = $user->posts()->first();
```

### Query Builder

```php
// Simple queries
$users = DB::table('users')->get();
$user = DB::table('users')->where('email', 'john@example.com')->first();

// Advanced queries  
$results = DB::table('users')
    ->join('posts', 'users.id', '=', 'posts.user_id')
    ->where('users.active', true)
    ->where('posts.published', true)
    ->select('users.*', 'posts.title')
    ->orderBy('posts.created_at', 'desc')
    ->paginate(10);

// Aggregations
$count = DB::table('users')->count();
$average = DB::table('posts')->avg('views');
```

## 🧪 Testing

Run the test suite to verify everything works:

```bash
# Basic functionality test
php test.php

# User creation verification  
php test_final_verification.php

# Advanced relationship tests
php test_relationships.php
```

### ✅ All Tests Pass
- ✅ User::create() and save() work perfectly
- ✅ Query Builder returns proper objects  
- ✅ All relationships function correctly
- ✅ Schema operations work on MySQL & SQLite
- ✅ Laravel compatibility verified

## 📋 Requirements

- **PHP** 8.0+ (7.4+ supported)
- **MySQL** 5.7+ or **SQLite** 3.0+
- **PDO** extension

## 🤝 Contributing

We welcome contributions! Please feel free to submit a Pull Request.

## 📄 License

This project is open-sourced software licensed under the [MIT license](LICENSE).

## 🌟 Support

- ⭐ **Star this repo** if you find it helpful!  
- 🐛 **Report issues** on GitHub
- 💡 **Request features** via GitHub Issues

---

<p align="center">
<strong>Made with ❤️ for the PHP community</strong><br>
<em>Arpon Database v1.0.0 - Production Ready</em>
</p>

### 🏗️ Advanced Schema Builder
- **Enhanced Blueprint**: 25+ column types including JSON, UUID, enum, set, binary, longText
- **Foreign Key Constraints**: Full CASCADE DELETE/UPDATE support on both MySQL and SQLite
- **Index Management**: Create, drop, and manage indexes with composite and unique constraints
- **Table Modifications**: Add/drop columns, rename tables, and modify existing structures
- **Cross-Database Compatibility**: Consistent API across MySQL and SQLite with intelligent fallbacks

### 🔍 Powerful Query Builder
- **Fluent Interface**: Chainable methods for building complex queries
- **Advanced Joins**: Inner, left, right, and cross joins with sub-queries
- **Aggregations**: Count, sum, average, min, max with grouping
- **Raw Queries**: Execute custom SQL with parameter binding
- **Transactions**: Full ACID transaction support with rollback capabilities

### 🔗 Advanced Relationship System
- **Complete Relationship Coverage**: 11 relationship types including through and polymorphic relationships
- **Through Relationships**: hasOneThrough(), hasManyThrough() for distant model access
- **Polymorphic Relationships**: morphOne(), morphMany(), morphTo() for flexible associations
- **Many-to-Many Polymorphic**: morphToMany(), morphedByMany() for complex relationships
- **Optimized SQL Generation**: Efficient joins and queries with proper column qualification
- **Laravel Eloquent Compatible**: Seamless migration from Laravel applications

### 🎯 Laravel-Compatible Capsule Manager
- **Container Architecture**: IoC container with dependency injection support
- **Static Method Access**: `Manager::connection()`, `Manager::table()`, `Manager::schema()`
- **Global Instance Management**: CapsuleManagerTrait for Laravel-style usage
- **Familiar Syntax**: Drop-in replacement for Laravel's database components
- **Migration-Style**: Use the same Blueprint patterns you're already familiar with
- **Enhanced ORM**: Full Eloquent-style relationships with advanced features

### 🐛 Stability & Bug Fixes (v1.0.0)
- **Fixed User Creation**: `User::create()` and `$user->save()` now work properly
- **Fixed Fetch Mode**: Query Builder returns objects instead of arrays
- **Enhanced Compatibility**: Improved Laravel Illuminate compatibility
- **Production Ready**: Thoroughly tested and stable for production use

## � Changelog

### v1.0.0 (2025-10-11) - Stable Release 🎉
- **🚀 NEW**: Laravel-compatible Capsule Manager with container architecture
- **🐛 FIXED**: User::create() and save() database insertion issues
- **🐛 FIXED**: DatabaseManager fetch mode configuration
- **🐛 FIXED**: Eloquent Model hydration for object compatibility
- **✨ ENHANCED**: Complete Laravel Illuminate compatibility
- **📚 UPDATED**: Comprehensive documentation and examples
- **🧪 TESTED**: All features thoroughly tested and verified

### Previous Versions
All previous versions (v2.x.x) have been deprecated in favor of this stable v1.0.0 release with improved architecture and bug fixes.

## �🚀 Quick Start

### Installation

```bash
composer require arpon/database
```

### Quick Setup

1. **Copy configuration files from examples:**
   ```bash
   cp examples/config.example.php config.php
   cp examples/bootstrap.example.php bootstrap.php
   ```

2. **Update your database settings** in `config.php`

3. **Initialize the database:**
   ```php
   <?php
   require_once 'bootstrap.php';
   
   // Database is now ready to use!
   $users = table('users')->get();
   
   // ✅ v1.0.0: User creation now works properly!
   $user = User::create(['name' => 'John', 'email' => 'john@example.com']);
   ```

### Basic Usage

```php
<?php
require_once 'vendor/autoload.php';

use Arpon\Database\Capsule\Manager as DB;
use Arpon\Database\Eloquent\Model;

// Create Laravel-compatible database manager
$capsule = new DB();

// Add MySQL connection
$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => 'localhost',
    'database'  => 'your_database',
    'username'  => 'your_username',
    'password'  => 'your_password',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
]);

// Boot the manager (Laravel-style)
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Now use Laravel-compatible static methods
$users = DB::table('users')->get();
$connection = DB::connection();
$schema = DB::schema();

// Or use Eloquent models
class User extends Model {
    protected array $fillable = ['name', 'email'];
}

// Create users (now works properly in v1.0.0!)
$user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
$user = new User();
$user->name = 'Jane Doe';
$user->save(); // ✅ Fixed in v1.0.0
```

### Examples & Documentation

Complete examples are available in the `examples/` directory:
- **Configuration**: `examples/config.example.php`
- **Initialization**: `examples/bootstrap.example.php`  
- **Basic Usage**: `examples/basic_usage.php`
- **Advanced Examples**: See `examples/README.md`

## 📖 Documentation

### Schema Building

#### Creating Tables with Advanced Features

```php
$schema = $capsule->schema();

$schema->create('users', function ($table) {
    $table->increments('id');
    $table->uuid('uuid')->unique();
    $table->string('name', 100);
    $table->string('email')->unique();
    $table->json('preferences')->nullable();
    $table->enum('status', ['active', 'inactive', 'pending'])->default('pending');
    $table->softDeletes();  // Laravel-style soft deletes
    $table->timestamps();
    
    // MySQL-specific optimizations
    if ($table instanceof MySqlBlueprint) {
        $table->engine = 'InnoDB';
        $table->charset = 'utf8mb4';
    }
});

$schema->create('posts', function ($table) {
    $table->increments('id');
    $table->unsignedInteger('user_id');
    $table->string('title');
    $table->longText('content');
    $table->unsignedInteger('views')->default(0);
    $table->decimal('rating', 3, 2)->default(0.00);
    $table->timestamps();
    
    // Foreign key with CASCADE DELETE
    $table->foreign('user_id')
          ->references('id')
          ->on('users')
          ->onDelete('cascade')
          ->onUpdate('cascade');
          
    // Composite index
    $table->index(['user_id', 'created_at'], 'user_posts_index');
});
```

#### Advanced Column Types

```php
$schema->create('advanced_table', function ($table) {
    // Standard types
    $table->increments('id');
    $table->bigInteger('big_number');
    $table->decimal('price', 10, 2);
    $table->boolean('is_active');
    
    // Advanced types
    $table->json('metadata');                    // JSON column
    $table->uuid('identifier');                  // UUID column
    $table->enum('type', ['A', 'B', 'C']);     // Enum column
    $table->set('permissions', ['read', 'write', 'delete']); // Set column
    $table->binary('file_data');                // Binary data
    $table->longText('description');            // Long text
    $table->mediumText('summary');              // Medium text
    $table->tinyInteger('priority');            // Tiny integer
    
    // Laravel-style helpers
    $table->morphs('taggable');                 // Polymorphic relation columns
    $table->nullableMorphs('commentable');      // Nullable polymorphic relation
    $table->rememberToken();                    // Remember token for authentication
});
```

### 🔗 Eloquent Relationships

#### Defining Models with Relationships

```php
use Arpon\Database\Eloquent\Model;

class User extends Model
{
    protected array $fillable = ['name', 'email'];
    
    // One-to-many relationship
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
    
    // One-to-one relationship  
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }
    
    // Through relationship - user's comments through posts
    public function comments()
    {
        return $this->hasManyThrough(Comment::class, Post::class);
    }
    
    // Polymorphic relationship
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }
}

class Post extends Model
{
    protected array $fillable = ['title', 'content', 'user_id'];
    
    // Inverse relationship
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    // Polymorphic many-to-many
    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}
```

#### Using Relationships

```php
// Eager loading relationships
$users = User::with('posts', 'profile')->get();

// Access through relationships
$user = User::find(1);
$userPosts = $user->posts; // Collection of posts
$userProfile = $user->profile; // Single profile

// Through relationships
$userComments = $user->comments(); // All comments through posts

// Polymorphic relationships  
$post = Post::find(1);
$postTags = $post->tags; // Tags associated with this post

// Dynamic queries on relationships
$activeUserPosts = $user->posts()->where('status', 'published')->get();
```

### Query Building

#### Basic Queries

```php
// Select queries
$users = $capsule->table('users')
    ->where('status', 'active')
    ->where('created_at', '>', '2023-01-01')
    ->orderBy('name')
    ->get();

// Insert
$userId = $capsule->table('users')->insertGetId([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'status' => 'active'
]);

// Update
$capsule->table('users')
    ->where('id', $userId)
    ->update(['status' => 'verified']);

// Delete
$capsule->table('users')
    ->where('status', 'inactive')
    ->delete();
```

#### Advanced Queries

```php
// Joins with aggregations
$results = $capsule->table('users')
    ->join('posts', 'users.id', '=', 'posts.user_id')
    ->select('users.name', 'users.email')
    ->selectRaw('COUNT(posts.id) as post_count')
    ->selectRaw('AVG(posts.rating) as avg_rating')
    ->where('users.status', 'active')
    ->groupBy('users.id')
    ->having('post_count', '>', 5)
    ->orderBy('avg_rating', 'desc')
    ->get();

// Subqueries
$popularPosts = $capsule->table('posts')
    ->whereIn('user_id', function($query) {
        $query->select('id')
              ->from('users')
              ->where('status', 'verified');
    })
    ->where('views', '>', 1000)
    ->get();
```

#### Transactions

```php
$capsule->transaction(function () use ($capsule) {
    $userId = $capsule->table('users')->insertGetId([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com'
    ]);
    
    $capsule->table('posts')->insert([
        'user_id' => $userId,
        'title' => 'My First Post',
        'content' => 'Hello World!'
    ]);
    
    // If any query fails, entire transaction rolls back
});
```

### Foreign Key CASCADE Operations

#### CASCADE DELETE Example

```php
// When a user is deleted, all their posts and comments are automatically deleted
$schema->create('users', function ($table) {
    $table->increments('id');
    $table->string('name');
    $table->timestamps();
});

$schema->create('posts', function ($table) {
    $table->increments('id');
    $table->unsignedInteger('user_id');
    $table->string('title');
    $table->timestamps();
    
    $table->foreign('user_id')
          ->references('id')
          ->on('users')
          ->onDelete('cascade');  // 🔥 Auto-delete posts when user deleted
});

$schema->create('comments', function ($table) {
    $table->increments('id');
    $table->unsignedInteger('post_id');
    $table->text('content');
    $table->timestamps();
    
    $table->foreign('post_id')
          ->references('id')
          ->on('posts')
          ->onDelete('cascade');  // 🔥 Auto-delete comments when post deleted
});

// Deleting a user automatically cascades to posts and comments
$capsule->table('users')->where('id', 1)->delete();
// ✅ User deleted, all their posts deleted, all related comments deleted
```

#### SET NULL Example

```php
$schema->create('user_profiles', function ($table) {
    $table->increments('id');
    $table->unsignedInteger('user_id')->nullable();
    $table->string('bio');
    $table->timestamps();
    
    $table->foreign('user_id')
          ->references('id')
          ->on('users')
          ->onDelete('set null');  // 🔄 Set to NULL when user deleted
});
```

## 🔧 Advanced Features

### Cross-Database Compatibility

The library automatically handles differences between MySQL and SQLite:

```php
// This works identically on both MySQL and SQLite
$schema->create('products', function ($table) {
    $table->increments('id');
    $table->json('attributes');     // JSON in MySQL, TEXT in SQLite
    $table->uuid('product_code');   // CHAR(36) in both databases
    $table->enum('status', ['new', 'used']); // Native ENUM in MySQL, TEXT with CHECK in SQLite
});
```

### Performance Optimizations

```php
// Batch insertions
$capsule->table('logs')->insert([
    ['message' => 'Log 1', 'level' => 'info'],
    ['message' => 'Log 2', 'level' => 'error'],
    ['message' => 'Log 3', 'level' => 'warning'],
]);

// Increment/Decrement
$capsule->table('posts')->increment('views');
$capsule->table('users')->decrement('credits', 5);
```

## 🧪 Testing

Run the comprehensive test suite:

```bash
# Test basic functionality (v1.0.0 verified)
php test.php                    # Basic CRUD operations test
php test_final_verification.php # User::create() and save() test

# Run comprehensive tests
php mysql_index.php             # MySQL functionality test
php sqlite_cascade_test.php     # SQLite CASCADE test
php cascade_test.php            # Cross-database CASCADE test

# Run PHPUnit tests (if available)
./vendor/bin/phpunit
```

### ✅ v1.0.0 Verification
All tests pass successfully in v1.0.0, including:
- User::create() method works properly
- $user->save() method inserts data correctly  
- Query Builder returns objects (not arrays)
- Laravel Capsule Manager static methods functional

## 📋 Requirements

- **PHP**: 7.4+ or 8.0+
- **MySQL**: 5.7+ or 8.0+ (optional)
- **SQLite**: 3.0+ (optional)
- **Extensions**: PDO, pdo_mysql (for MySQL), pdo_sqlite (for SQLite)

## 🔄 Version History

### Version 2.2.0 (Current)
- ✅ **Enhanced Collection Methods** - Added `values()`, `unique()`, and `sort()` methods with advanced sorting capabilities
- ✅ **Comprehensive Soft Delete System** - Full Laravel-compatible soft delete functionality with SoftDeletes trait, SoftDeleteScope, and query extensions
- ✅ **Advanced Scopes System** - Enhanced global and local scopes with dynamic scope management
- ✅ **Model Event System** - Complete event-driven architecture with model lifecycle events
- ✅ **Trait Boot System** - Automatic trait discovery and initialization
- ✅ **Query Builder Macros** - Dynamic method injection for scope extensions

### Version 1.0.0 - Stable Release 🎉
- ✅ **Laravel Capsule Manager**: Full Laravel Illuminate compatibility with container architecture
- ✅ **Bug Fixes**: Fixed User::create() and save() methods for proper database insertion
- ✅ **Query Builder**: Fixed fetch mode to return objects instead of arrays
- ✅ **Complete Relationship System**: 11 relationship types (hasOne, hasMany, belongsTo, hasOneThrough, hasManyThrough, morphOne, morphMany, morphTo, morphToMany, morphedByMany, belongsToMany)
- ✅ **Through Relationships**: Access distant models through intermediate relationships
- ✅ **Polymorphic Relationships**: One model can belong to multiple other model types
- ✅ **Advanced ORM**: Full Laravel Eloquent compatibility with optimized SQL generation
- ✅ **Enhanced Model Features**: qualifyColumn(), getMorphClass(), morphMap support
- ✅ **Production Ready**: Comprehensive test coverage, bug fixes, and performance optimizations
- ✅ **Static Method Access**: Manager::connection(), Manager::table(), Manager::schema()
- ✅ **Stable Architecture**: Clean codebase with proper error handling and compatibility

### Version 2.0.0
- ✅ **Enhanced Schema Builder**: 25+ advanced column types
- ✅ **Foreign Key CASCADE**: Full ON DELETE/UPDATE support
- ✅ **Cross-Database Compatibility**: MySQL and SQLite feature parity
- ✅ **Laravel-Compatible API**: Drop-in replacement for Laravel DB components
- ✅ **Advanced Constraints**: Index management, unique constraints, composite keys
- ✅ **Performance Optimizations**: Batch operations, connection pooling

### Version 1.0.0
- Basic query builder
- Simple schema operations
- MySQL and SQLite support

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Inspired by Laravel's Eloquent ORM and Schema Builder
- Built with modern PHP practices and PSR standards
- Designed for developer productivity and database portability

---

**Made with ❤️ by [Arpon](https://github.com/arponascension1)**