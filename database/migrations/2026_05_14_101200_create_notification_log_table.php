<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('channel'); // email, sms, whatsapp, in_app
            $table->string('subject')->nullable();
            $table->text('body');
            $table->json('variables')->nullable(); // array of allowed merge fields
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('notification_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained();
            $table->foreignId('booking_id')->nullable()->constrained();
            $table->foreignId('schedule_id')->nullable()->constrained();
            $table->foreignId('notification_template_id')->nullable()->constrained();
            $table->string('template_code')->nullable();
            $table->string('channel');
            $table->string('recipient'); // phone / email
            $table->string('subject')->nullable();
            $table->text('body');
            // queued, sent, delivered, failed, suppressed (quiet hours / muted)
            $table->string('status')->default('queued');
            $table->string('provider')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamp('queued_at')->useCurrent();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'queued_at']);
            $table->index(['status', 'queued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_log');
        Schema::dropIfExists('notification_templates');
    }
};
