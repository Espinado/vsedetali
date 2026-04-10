<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('warehouses')
            ->whereNotNull('seller_id')
            ->update(['is_default' => false]);
    }

    public function down(): void
    {
        // Не восстанавливаем прежние значения.
    }
};
