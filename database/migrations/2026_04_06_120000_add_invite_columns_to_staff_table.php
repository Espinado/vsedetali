<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('invite_token_hash', 64)->nullable();
            $table->timestamp('invite_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['invite_token_hash', 'invite_expires_at']);
        });
    }
};
