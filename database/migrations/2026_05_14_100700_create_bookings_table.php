<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique(); // ZR-B-00001
            $table->foreignId('client_id')->constrained();
            $table->foreignId('unit_id')->constrained();
            $table->foreignId('plan_template_id')->constrained();
            $table->date('booking_date');
            // Snapshot of agreed pricing at booking time, in minor units
            $table->unsignedBigInteger('total_price_minor');
            $table->char('currency', 3)->default('PKR');
            $table->unsignedBigInteger('down_payment_minor')->default(0);
            $table->string('status')->default('active'); // active, completed, cancelled
            $table->date('cancelled_on')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->references('id')->on('users');
            $table->foreignId('salesperson_id')->nullable()->references('id')->on('users');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
        });

        Schema::create('booking_co_buyers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained();
            $table->unsignedSmallInteger('share_bps')->default(0); // basis points
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['booking_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_co_buyers');
        Schema::dropIfExists('bookings');
    }
};
