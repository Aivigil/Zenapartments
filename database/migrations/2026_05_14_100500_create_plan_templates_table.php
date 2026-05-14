<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('down_payment_bps')->default(0); // basis points (0-10000)
            $table->unsignedSmallInteger('installment_count')->default(0);
            $table->string('installment_frequency')->default('monthly'); // monthly, quarterly
            // Milestone charges: array of { code, label, type:percent|fixed, value, trigger:date|event, sort_order }
            $table->json('milestone_charges')->nullable();
            // Late fee policy: { kind:none|flat|percent, grace_days, value, cap_minor }
            $table->json('late_fee_policy')->nullable();
            // Early payment discount: { kind:none|percent, value }
            $table->json('early_payment_discount')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_templates');
    }
};
