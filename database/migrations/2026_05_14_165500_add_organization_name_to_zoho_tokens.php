<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zoho_tokens', function (Blueprint $table) {
            $table->string('organization_name')->nullable()->after('organization_id');
        });
    }

    public function down(): void
    {
        Schema::table('zoho_tokens', function (Blueprint $table) {
            $table->dropColumn('organization_name');
        });
    }
};