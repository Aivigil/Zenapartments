<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only financial audit trail.
     *
     * Spatie activity_log covers general entity changes. This table is
     * specifically for high-impact financial events (post payment,
     * reverse payment, waive late fee, edit plan, etc.) where we need
     * extra guarantees: cannot be edited or deleted, includes before/after
     * snapshots, includes IP and user-agent.
     */
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('actor_id')->nullable()->references('id')->on('users');
            $table->string('actor_role')->nullable();
            $table->string('event'); // payment.posted, payment.reversed, schedule.waived, plan.amended, ...
            $table->morphs('subject');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->index(['event', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
