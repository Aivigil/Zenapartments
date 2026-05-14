<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique(); // ZR-P-000001 (receipt number)
            $table->foreignId('client_id')->constrained();
            $table->foreignId('booking_id')->nullable()->constrained();
            // bank_transfer, cash, cheque, online_gateway, foreign_wire
            $table->string('channel');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('PKR');
            // For non-PKR — rate at posting; pkr_amount_minor = round(amount_minor * fx_rate)
            $table->decimal('fx_rate', 18, 8)->nullable();
            $table->unsignedBigInteger('pkr_amount_minor')->nullable();
            $table->date('received_at');
            $table->string('bank_account')->nullable();
            $table->string('bank_reference')->nullable()->index();
            $table->foreignId('bank_statement_line_id')->nullable()->references('id')->on('bank_statement_lines')->nullOnDelete();
            $table->string('status')->default('posted'); // posted, reversed
            $table->date('reversed_on')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->foreignId('reversed_by')->nullable()->references('id')->on('users');
            $table->foreignId('posted_by')->nullable()->references('id')->on('users');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'received_at']);
        });

        Schema::create('allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('PKR');
            $table->timestamps();
            $table->index(['payment_id', 'schedule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocations');
        Schema::dropIfExists('payments');
    }
};
