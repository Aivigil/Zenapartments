<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_portal_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            // Optional scope to a single booking — null = all bookings for this client
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            // 40-char URL-safe token (kept opaque, not in URL signature) for /c/{token}
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('last_used_at')->nullable();
            $table->ipAddress('last_used_ip')->nullable();
            $table->unsignedInteger('use_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('label')->nullable();   // 'WhatsApp Apr 14', etc.
            $table->timestamps();

            $table->index(['client_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_portal_tokens');
    }
};
