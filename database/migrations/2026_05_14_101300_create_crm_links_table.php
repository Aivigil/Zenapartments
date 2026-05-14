<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_links', function (Blueprint $table) {
            $table->id();
            $table->string('crm_provider')->default('geniecentral'); // geniecentral; future: hubspot, ghl
            $table->morphs('local'); // local_type, local_id (Client, Booking, etc.)
            $table->string('remote_entity_type'); // contact, lead, opportunity
            $table->string('remote_entity_id');
            $table->timestamp('last_synced_at')->nullable();
            $table->json('last_payload_in')->nullable();
            $table->json('last_payload_out')->nullable();
            $table->string('sync_status')->default('ok'); // ok, drift, error
            $table->timestamps();
            $table->unique(['crm_provider', 'remote_entity_type', 'remote_entity_id'], 'crm_remote_unique');
        });

        Schema::create('crm_sync_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('direction'); // out, in
            $table->string('entity_type');
            $table->string('entity_id');
            $table->json('payload');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->string('status')->default('queued'); // queued, processing, succeeded, failed, dead
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_sync_jobs');
        Schema::dropIfExists('crm_links');
    }
};
