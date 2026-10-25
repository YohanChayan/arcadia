<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An Arcadia membership's own device limit, when the owner sets one (null reads the site's), and
 * when the reader last swapped a device out themselves — allowed once every thirty days (option
 * D1), so two people sharing one login cannot keep trading places.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_memberships', function (Blueprint $table) {
            $table->unsignedTinyInteger('device_limit')->nullable()->after('role_id');
            $table->timestamp('devices_replaced_at')->nullable()->after('device_limit');
        });
    }

    public function down(): void
    {
        Schema::table('site_memberships', function (Blueprint $table) {
            $table->dropColumn(['device_limit', 'devices_replaced_at']);
        });
    }
};
