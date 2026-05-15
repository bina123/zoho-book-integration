<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Decouples the selected Zoho organization from the OAuth token row.
     *
     * Up:
     *   1. Create zoho_settings (singleton row, id = 1).
     *   2. Back-fill organization_id / organization_name from the latest
     *      zoho_tokens row so no manual re-picking is needed after deploy.
     *   3. Drop organization_id and organization_name from zoho_tokens.
     *
     * Down: reverse — re-add columns to zoho_tokens, copy back, drop settings.
     */
    public function up(): void
    {
        Schema::create('zoho_settings', function (Blueprint $table) {
            $table->id();
            $table->string('organization_id')->nullable();
            $table->string('organization_name')->nullable();
            $table->timestamps();
        });

        $latest = DB::table('zoho_tokens')
            ->orderByDesc('created_at')
            ->first(['organization_id', 'organization_name']);

        DB::table('zoho_settings')->insert([
            'id' => 1,
            'organization_id' => $latest->organization_id ?? null,
            'organization_name' => $latest->organization_name ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('zoho_tokens', function (Blueprint $table) {
            $table->dropColumn(['organization_id', 'organization_name']);
        });
    }

    public function down(): void
    {
        Schema::table('zoho_tokens', function (Blueprint $table) {
            $table->string('organization_id')->nullable();
            $table->string('organization_name')->nullable();
        });

        $setting = DB::table('zoho_settings')->find(1);
        if ($setting) {
            DB::table('zoho_tokens')
                ->orderByDesc('created_at')
                ->limit(1)
                ->update([
                    'organization_id' => $setting->organization_id,
                    'organization_name' => $setting->organization_name,
                ]);
        }

        Schema::dropIfExists('zoho_settings');
    }
};
