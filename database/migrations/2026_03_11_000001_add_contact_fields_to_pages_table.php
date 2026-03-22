<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('contact_email', 255)->nullable()->after('body');
            $table->string('contact_phone', 100)->nullable()->after('contact_email');
            $table->text('contact_address')->nullable()->after('contact_phone');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['contact_email', 'contact_phone', 'contact_address']);
        });
    }
};
