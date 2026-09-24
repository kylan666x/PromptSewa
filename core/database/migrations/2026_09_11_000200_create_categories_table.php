<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taxonomy for the marketplace (PRD §5: Admin manages categories).
 *
 * One level of nesting is enough for a prompt library; deeper trees are
 * a YAGNI trap on shared hosting where every join costs an index seek.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories') // self-referencing FK: categories → categories
                ->nullOnDelete();
            $table->string('name', 100);
            $table->string('slug', 120)->unique(); // URLs: /categories/writing-prompts
            $table->string('icon', 50)->nullable(); // Blade/Alpine icon identifier
            $table->unsignedSmallInteger('position')->default(0); // manual curation order
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            // Composite index for the ordered category sidebar query.
            $table->index(['is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
