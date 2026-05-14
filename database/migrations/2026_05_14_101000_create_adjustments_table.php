<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->foreignId('booking_id')->constrained();
            $table->foreignId('schedule_id')->nullable()->constrained();
            $table->string('kind'); // waiver, discount, write_off, goodwill, fx_adjustment, manual_debit, manual_credit
            $table->string('direction'); // credit (reduces outstanding), debit (increases outstanding)
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('PKR');
            $table->date('effective_on');
            $table->text('reason');
            $table->foreignId('requested_by')->nullable()->references('id')->on('users');
            $table->foreignId('approved_by')->nullable()->references('id')->on('users');
            $table->timestamp('approved_at')->nullable();
            $table->string('status')->default('approved'); // pending, approved, rejected, reversed
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adjustments');
    }
};
