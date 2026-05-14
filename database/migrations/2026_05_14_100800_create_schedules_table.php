<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence_no');
            $table->date('due_date');
            $table->unsignedBigInteger('amount_minor');       // original scheduled amount
            $table->unsignedBigInteger('paid_minor')->default(0); // running paid total (denormalised, recomputed from allocations)
            $table->char('currency', 3)->default('PKR');
            $table->string('category'); // down_payment, installment, milestone:<code>, late_fee, charge, adjustment
            $table->string('label')->nullable();
            // due, partially_paid, paid, waived, written_off, cancelled
            $table->string('status')->default('due');
            $table->date('paid_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['booking_id', 'due_date']);
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
