<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });

        Schema::table('sellers', function (Blueprint $table): void {
            $table->dropUnique(['user_id']);
        });

        Schema::table('sellers', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
        });

        Schema::table('sellers', function (Blueprint $table): void {
            $table->unique('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->date('contract_date')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });

        Schema::table('sellers', function (Blueprint $table): void {
            $table->dropUnique(['user_id']);
            $table->dropColumn('contract_date');
        });

        Schema::table('sellers', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable(false)->change();
        });

        Schema::table('sellers', function (Blueprint $table): void {
            $table->unique('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
