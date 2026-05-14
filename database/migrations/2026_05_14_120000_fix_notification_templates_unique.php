<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The original migration declared `code` as the unique column. But the
     * NotificationTemplatesSeeder pairs each `code` with multiple channels
     * (email + sms versions of the same template). The right uniqueness is
     * the composite (code, channel).
     */
    public function up(): void
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->dropUnique('notification_templates_code_unique');
            $table->unique(['code', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->dropUnique(['code', 'channel']);
            $table->unique('code');
        });
    }
};
