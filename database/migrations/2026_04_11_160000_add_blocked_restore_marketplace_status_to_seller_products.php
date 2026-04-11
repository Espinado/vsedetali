<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_products', function (Blueprint $table): void {
            $table->string('blocked_restore_marketplace_status', 20)
                ->nullable()
                ->after('status')
                ->comment('Статус листинга до блокировки продавца (восстановление при разблокировке)');
        });
    }

    public function down(): void
    {
        Schema::table('seller_products', function (Blueprint $table): void {
            $table->dropColumn('blocked_restore_marketplace_status');
        });
    }
};
