<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('owner');
            $table->string('kind'); // cnic, sales_agreement, addendum, receipt, statement, payment_proof, project_update, other
            $table->string('filename');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->string('storage_disk')->default('local');
            $table->string('storage_key');
            $table->string('content_sha256', 64)->index();
            $table->boolean('is_generated')->default(false);
            $table->json('metadata')->nullable();
            $table->foreignId('uploaded_by')->nullable()->references('id')->on('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
