<?php

namespace Arpon\Database\Tests\Feature\Schema;

use Arpon\Database\Tests\TestCase;

abstract class BaseSchemaTest extends TestCase
{
    /**
     * Cache schema instance to avoid repeated getConnection()->schema() calls
     */
    protected $schema;
    
    protected function migrateTestDatabase(): void
    {
        parent::migrateTestDatabase();
        
        // Initialize schema cache
        $this->schema = $this->getConnection()->schema();
    }
    
    /**
     * Helper to get schema (uses cached instance)
     */
    protected function schema()
    {
        return $this->schema ?? ($this->schema = $this->getConnection()->schema());
    }

    /** @test */
    public function it_can_create_table_and_check_columns()
    {
        $schema = $this->schema();
        $schema->dropIfExists('schema_examples');

        $schema->create('schema_examples', function ($table) {
            $table->id();
            $table->string('name');
            $table->integer('age');
            $table->timestamps();
        });

        $this->assertTrue($schema->hasTable('schema_examples'));
        $this->assertTrue($schema->hasColumn('schema_examples', 'name'));
        $this->assertTrue($schema->hasColumns('schema_examples', ['name', 'age']));

        $columns = $schema->getColumnListing('schema_examples');
        $this->assertContains('name', $columns);
        $this->assertContains('age', $columns);
    }

    /** @test */
    public function it_can_rename_a_table()
    {
        $schema = $this->schema();
        $schema->dropIfExists('new_schema'); // Drop target first to avoid conflicts
        $schema->dropIfExists('old_schema');

        $schema->create('old_schema', function ($table) {
            $table->id();
            $table->string('title');
        });

        $schema->rename('old_schema', 'new_schema');

        $this->assertFalse($schema->hasTable('old_schema'));
        $this->assertTrue($schema->hasTable('new_schema'));
    }

    /** @test */
    public function it_can_drop_tables()
    {
        $schema = $this->schema();
        $schema->dropIfExists('to_drop');

        $schema->create('to_drop', function ($table) {
            $table->id();
            $table->string('label');
        });

        $this->assertTrue($schema->hasTable('to_drop'));
        $schema->drop('to_drop');
        $this->assertFalse($schema->hasTable('to_drop'));

        // dropIfExists should not throw when table missing
        $schema->dropIfExists('non_existent_table');
        $this->assertFalse($schema->hasTable('non_existent_table'));
    }

    /** @test */
    public function it_can_toggle_foreign_key_constraints()
    {
        $schema = $this->schema();
        $this->assertIsBool($schema->disableForeignKeyConstraints());
        $this->assertIsBool($schema->enableForeignKeyConstraints());
    }

    /** @test */
    public function it_respects_unique_constraints()
    {
        $schema = $this->schema();
        $schema->dropIfExists('unique_examples');

        $schema->create('unique_examples', function ($table) {
            $table->id();
            $table->string('email')->unique();
        });

        $conn = $this->getConnection();
        $conn->table('unique_examples')->insert(['email' => 'unique@example.com']);

        $this->expectException(\Exception::class);
        $conn->table('unique_examples')->insert(['email' => 'unique@example.com']);
    }

    /** @test */
    public function it_applies_foreign_keys_and_cascades_on_delete()
    {
        $schema = $this->schema();
        $schema->enableForeignKeyConstraints();

        // Drop child first to avoid FK constraint blocking drops
        $schema->dropIfExists('children');
        $schema->dropIfExists('parents');

        $schema->create('parents', function ($table) {
            $table->id();
            $table->string('name');
        });

        $schema->create('children', function ($table) {
            $table->id();
            $table->foreignId('parent_id')->constrained()->cascadeOnDelete();
            $table->string('title');
        });

        $conn = $this->getConnection();
        $conn->table('parents')->insert(['name' => 'Parent']);
        $parentId = $conn->table('parents')->first()->id;
        $conn->table('children')->insert(['parent_id' => $parentId, 'title' => 'Child']);

        // Delete parent and expect child to be removed if cascade is active
        $conn->table('parents')->delete();
        $this->assertCount(0, $conn->table('children')->get());
    }

    /** @test */
    public function it_can_create_json_column_and_store_json()
    {
        $schema = $this->schema();
        $schema->dropIfExists('json_examples');

        $schema->create('json_examples', function ($table) {
            $table->id();
            $table->json('payload');
        });

        $data = ['key' => 'value', 'nested' => ['a' => 1]];
        $conn = $this->getConnection();
        $conn->table('json_examples')->insert(['payload' => json_encode($data)]);

        $row = $conn->table('json_examples')->first();
        $payload = is_string($row->payload) 
            ? json_decode($row->payload, true) 
            : json_decode(json_encode($row->payload), true);

        $this->assertEquals($data, $payload);
    }

    /** @test */
    public function it_supports_composite_primary_and_unique_indexes()
    {
        $schema = $this->schema();
        $schema->dropIfExists('composite_examples');

        $schema->create('composite_examples', function ($table) {
            $table->integer('a');
            $table->integer('b');
            $table->primary(['a', 'b']);
        });

        $conn = $this->getConnection();
        $conn->table('composite_examples')->insert(['a' => 1, 'b' => 2]);

        $this->expectException(\Exception::class);
        $conn->table('composite_examples')->insert(['a' => 1, 'b' => 2]);
    }

    /** @test */
    public function it_respects_on_delete_set_null_for_foreign_keys()
    {
        $schema = $this->schema();
        $schema->dropIfExists('child_nullable');
        $schema->dropIfExists('parent_nullable');
        $schema->enableForeignKeyConstraints();

        $schema->create('parent_nullable', function ($table) {
            $table->id();
            $table->string('name')->nullable();
        });

        $schema->create('child_nullable', function ($table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign(['parent_id'])->references(['id'])->on('parent_nullable')->nullOnDelete();
        });

        $conn = $this->getConnection();
        $conn->table('parent_nullable')->insert(['name' => 'p']);
        $parentId = $conn->table('parent_nullable')->first()->id;
        $conn->table('child_nullable')->insert(['parent_id' => $parentId]);

        $conn->table('parent_nullable')->delete();

        $child = $conn->table('child_nullable')->first();
        $this->assertNull($child->parent_id);
    }

    /** @test */
    public function it_throws_runtime_exception_when_renaming_column()
    {
        $schema = $this->schema();
        $schema->dropIfExists('rename_examples');

        $schema->create('rename_examples', function ($table) {
            $table->id();
            $table->string('old_name');
        });

        $this->expectException(\RuntimeException::class);
        $schema->table('rename_examples', function ($table) {
            $table->renameColumn('old_name', 'new_name');
        });
    }

    /** @test */
    public function it_outputs_sql_from_blueprint()
    {
        $connection = $this->getConnection();
        $grammar = $connection->getSchemaGrammar() ?? $connection->useDefaultSchemaGrammar();
        $grammar = $connection->getSchemaGrammar();

        $blueprint = new \Arpon\Database\Schema\Blueprint('sql_out');
        $blueprint->create();
        $blueprint->id();
        $blueprint->string('name');
        $blueprint->unique('name');

        $sql = $blueprint->toSql($connection, $grammar);

        $this->assertIsArray($sql);
        $this->assertNotEmpty($sql);
        $joined = strtolower(implode(' ', $sql));
        $this->assertStringContainsString('create', $joined);
        $this->assertStringContainsString('name', $joined);
    }
}
