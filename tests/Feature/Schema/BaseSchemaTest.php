<?php

namespace Arpon\Database\Tests\Feature\Schema;

use Arpon\Database\Tests\TestCase;

abstract class BaseSchemaTest extends TestCase
{
    protected function migrateTestDatabase(): void
    {
        parent::migrateTestDatabase();

        $connection = $this->getConnection();

        // Ensure test schema tables are removed before each test suite
        $connection->statement('DROP TABLE IF EXISTS schema_examples');
        $connection->statement('DROP TABLE IF EXISTS old_schema');
        $connection->statement('DROP TABLE IF EXISTS new_schema');
        $connection->statement('DROP TABLE IF EXISTS to_drop');
        $connection->statement('DROP TABLE IF EXISTS to_drop_if_exists');
    }

    /** @test */
    public function it_can_create_table_and_check_columns()
    {
        $schema = $this->getConnection()->schema();

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
        $schema = $this->getConnection()->schema();

        $schema->dropIfExists('old_schema');
        $schema->dropIfExists('new_schema');

        $schema->create('old_schema', function ($table) {
            $table->id();
            $table->string('title');
        });

        $this->assertTrue($schema->hasTable('old_schema'));

        $schema->rename('old_schema', 'new_schema');

        $this->assertFalse($schema->hasTable('old_schema'));
        $this->assertTrue($schema->hasTable('new_schema'));
    }

    /** @test */
    public function it_can_drop_tables()
    {
        $schema = $this->getConnection()->schema();

        $schema->dropIfExists('to_drop');

        $schema->create('to_drop', function ($table) {
            $table->id();
            $table->string('label');
        });

        $this->assertTrue($schema->hasTable('to_drop'));

        $schema->drop('to_drop');

        $this->assertFalse($schema->hasTable('to_drop'));

        // dropIfExists should not throw when table missing
        $schema->dropIfExists('to_drop_if_exists');
        $this->assertFalse($schema->hasTable('to_drop_if_exists'));
    }

    /** @test */
    public function it_can_toggle_foreign_key_constraints()
    {
        $schema = $this->getConnection()->schema();

        // The methods should execute without throwing and return a boolean result
        $disabled = $schema->disableForeignKeyConstraints();
        $this->assertTrue(is_bool($disabled));

        $enabled = $schema->enableForeignKeyConstraints();
        $this->assertTrue(is_bool($enabled));
    }

    /** @test */
    public function it_respects_unique_constraints()
    {
        $schema = $this->getConnection()->schema();

        $schema->dropIfExists('unique_examples');

        $schema->create('unique_examples', function ($table) {
            $table->id();
            $table->string('email')->unique();
        });

        $conn = $this->getConnection();

        // First insert should succeed
        $conn->table('unique_examples')->insert([
            'email' => 'unique@example.com'
        ]);

        // Second insert with same email should fail due to unique constraint
        $threw = false;
        try {
            $conn->table('unique_examples')->insert([
                'email' => 'unique@example.com'
            ]);
        } catch (\Exception $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'Expected unique constraint to throw on duplicate insert');
    }

    /** @test */
    public function it_applies_foreign_keys_and_cascades_on_delete()
    {
        $schema = $this->getConnection()->schema();

        // Ensure foreign keys are enforced when testing cascade behavior
        $schema->enableForeignKeyConstraints();


    // Use conventional table names so constrained() can guess the parent table
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
        $parent = $conn->table('parents')->get();

        $conn->table('children')->insert(['parent_id' => $parent[0]->id, 'title' => 'Child']);

        // Delete parent and expect child to be removed if cascade is active
        $conn->table('parents')->delete();

        $children = $conn->table('children')->get();
        $this->assertCount(0, $children);
    }

    /** @test */
    public function it_can_create_json_column_and_store_json()
    {
        $schema = $this->getConnection()->schema();

        $schema->dropIfExists('json_examples');

        $schema->create('json_examples', function ($table) {
            $table->id();
            $table->json('payload');
        });

        $conn = $this->getConnection();

        $data = ['key' => 'value', 'nested' => ['a' => 1]];

        $conn->table('json_examples')->insert([
            'payload' => json_encode($data)
        ]);

        $row = $conn->table('json_examples')->get()[0];

        $payload = is_string($row->payload) ? json_decode($row->payload, true) : (is_string(json_encode($row->payload)) ? json_decode(json_encode($row->payload), true) : (array) $row->payload);

        $this->assertEquals($data, $payload);
    }

    /** @test */
    public function it_supports_composite_primary_and_unique_indexes()
    {
        $schema = $this->getConnection()->schema();

        $schema->dropIfExists('composite_examples');

        $schema->create('composite_examples', function ($table) {
            $table->integer('a');
            $table->integer('b');
            $table->primary(['a', 'b']);
            $table->unique(['a', 'b'], 'composite_idx');
        });

        $conn = $this->getConnection();

        // Insert first row
        $conn->table('composite_examples')->insert(['a' => 1, 'b' => 2]);

        // Duplicate composite should fail due to unique index
        $threw = false;
        try {
            $conn->table('composite_examples')->insert(['a' => 1, 'b' => 2]);
        } catch (\Exception $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'Expected duplicate composite insert to throw due to unique index');
    }

    /** @test */
    public function it_respects_on_delete_set_null_for_foreign_keys()
    {
        $schema = $this->getConnection()->schema();

        // Drop in correct order
        $schema->dropIfExists('child_nullable');
        $schema->dropIfExists('parent_nullable');

        // Ensure foreign key enforcement is enabled for this connection
        $schema->enableForeignKeyConstraints();

        $schema->create('parent_nullable', function ($table) {
            $table->id();
            $table->string('name')->nullable();
        });

        $schema->create('child_nullable', function ($table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            // Create explicit foreign key with ON DELETE SET NULL
            $table->foreign(['parent_id'])->references(['id'])->on('parent_nullable')->nullOnDelete();
        });

        $conn = $this->getConnection();

    $conn->table('parent_nullable')->insert(['name' => 'p']);
    $parent = $conn->table('parent_nullable')->get()[0];

        $conn->table('child_nullable')->insert(['parent_id' => $parent->id]);

        // Delete parent, expect child.parent_id to become null
        $conn->table('parent_nullable')->delete();

        $children = $conn->table('child_nullable')->get();
        $this->assertCount(1, $children);
        $this->assertNull($children[0]->parent_id);
    }

    /** @test */
    public function it_handles_rename_column_on_mysql_and_throws_on_sqlite()
    {
        $schema = $this->getConnection()->schema();

        $schema->dropIfExists('rename_examples');

        $schema->create('rename_examples', function ($table) {
            $table->id();
            $table->string('old_name');
        });

        // Renaming columns requires DB introspection which isn't implemented here.
        $this->expectException(\RuntimeException::class);
        $schema->table('rename_examples', function ($table) {
            $table->renameColumn('old_name', 'new_name');
        });
    }

    /** @test */
    public function it_outputs_sql_from_blueprint()
    {
        $connection = $this->getConnection();
        $grammar = $connection->getSchemaGrammar();
        if (is_null($grammar)) {
            $connection->useDefaultSchemaGrammar();
            $grammar = $connection->getSchemaGrammar();
        }

        $blueprint = new \Arpon\Database\Schema\Blueprint('sql_out');
        $blueprint->create();
        $blueprint->id();
        $blueprint->string('name');
        $blueprint->unique('name');

        $sql = $blueprint->toSql($connection, $grammar);

        $this->assertIsArray($sql);
        $joined = implode(' ', $sql);
        $this->assertStringContainsString('create', strtolower($joined));
        $this->assertStringContainsString('name', strtolower($joined));
    }
}
