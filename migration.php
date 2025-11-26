<?php
require_once __DIR__ . '/bootstrap.php';

use Arpon\Database\Capsule\Manager as DB;
use Arpon\Database\Schema\Blueprint;

DB::schema()->dropIfExists('posts');
DB::schema()->dropIfExists('users');

DB::schema()->create("users", function (Blueprint $table) {
    $table->id();
    $table->string("name");
    $table->string("email")->unique();
    $table->string("password");
    $table->rememberToken();
    $table->timestamps();
});
Db::schema()->create("posts", function (Blueprint $table) {
    $table->id();
    $table->string("title");
    $table->text('content');
    $table->boolean('published');
    $table->foreignId("user_id")->constrained()->onDelete("cascade");
    $table->timestamps();
});