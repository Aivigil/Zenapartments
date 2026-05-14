<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique(); // e.g. ZR-C-00012, externally referenced
            $table->string('full_name');
            $table->string('father_or_husband_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->default('Pakistani');
            $table->string('country_of_residence', 2)->default('PK');
            // Sensitive — encrypted at the model layer
            $table->text('cnic_encrypted')->nullable();
            $table->text('passport_encrypted')->nullable();
            // Searchable hash of cnic for dedupe (HMAC, not plaintext)
            $table->string('cnic_hash', 64)->nullable()->unique();
            $table->string('primary_phone', 32)->index();
            $table->string('alt_phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->text('address_line1')->nullable();
            $table->text('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('kyc_status')->default('pending'); // pending, verified, rejected
            $table->timestamp('kyc_verified_at')->nullable();
            $table->foreignId('kyc_verified_by')->nullable()->references('id')->on('users');
            $table->json('preferences')->nullable(); // language, contact channel mute toggles
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['full_name']);
        });

        Schema::create('nominees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('relationship');
            $table->text('cnic_encrypted')->nullable();
            $table->string('cnic_hash', 64)->nullable();
            $table->string('phone', 32)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nominees');
        Schema::dropIfExists('clients');
    }
};
