<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('zoho_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token_type')->default('Bearer');
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->integer('expires_in');
            $table->timestamp('expires_at');
            $table->string('scope')->nullable();
            $table->string('api_domain')->nullable();
            $table->string('organization_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zoho_tokens');
    }
};
