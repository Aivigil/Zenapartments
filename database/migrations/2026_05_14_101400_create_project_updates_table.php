<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained();
            $table->string('title');
            $table->text('body');
            $table->string('category')->default('construction'); // construction, milestone, admin, payment_reminder
            $table->boolean('broadcast')->default(false);
            $table->boolean('broadcasted')->default(false);
            $table->timestamp('broadcasted_at')->nullable();
            $table->foreignId('created_by')->nullable()->references('id')->on('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_updates');
    }
};
