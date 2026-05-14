<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('block_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_category_id')->constrained();
            $table->string('code', 64);
            $table->string('name')->nullable();
            $table->decimal('size_value', 12, 3)->nullable();
            $table->string('size_unit', 16)->nullable(); // marla, kanal, sqft, sqm
            // Money stored in minor units (paisa). Currency PKR.
            $table->unsignedBigInteger('base_price_minor')->default(0);
            $table->char('currency', 3)->default('PKR');
            $table->string('status')->default('available'); // available, blocked, sold, possession_transferred, cancelled
            $table->json('attributes')->nullable(); // corner, facing, view_premium, floor_number, etc.
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['project_id', 'code']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
