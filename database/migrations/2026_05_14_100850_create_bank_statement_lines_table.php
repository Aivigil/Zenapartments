<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_imports', function (Blueprint $table) {
            $table->id();
            $table->string('bank_account');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('source_filename')->nullable();
            $table->string('source_hash', 64)->nullable()->index();
            $table->unsignedInteger('total_lines')->default(0);
            $table->unsignedInteger('matched_lines')->default(0);
            $table->foreignId('imported_by')->nullable()->references('id')->on('users');
            $table->timestamps();
        });

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_import_id')->constrained()->cascadeOnDelete();
            $table->string('bank_account');
            $table->date('txn_date');
            $table->string('direction'); // credit (incoming), debit (outgoing — usually ignored)
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('PKR');
            $table->text('description')->nullable();
            $table->string('counterparty')->nullable();
            $table->string('reference')->nullable()->index();
            $table->json('raw')->nullable();
            // pending, matched (auto), confirmed (human), ignored, dispute
            $table->string('status')->default('pending');
            $table->json('suggested_matches')->nullable(); // array of {client_id, score, reason}
            $table->foreignId('matched_client_id')->nullable()->references('id')->on('clients');
            $table->foreignId('matched_payment_id')->nullable()->references('id')->on('payments');
            $table->foreignId('reviewed_by')->nullable()->references('id')->on('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'txn_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statement_imports');
    }
};
