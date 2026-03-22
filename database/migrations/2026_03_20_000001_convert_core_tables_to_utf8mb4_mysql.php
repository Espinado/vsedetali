<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Исправляет ошибку MySQL 1366 при вставке кириллицы, если таблицы созданы в latin1.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $tables = [
            'products',
            'brands',
            'categories',
            'vehicles',
            'warehouses',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::statement(
                "ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
        }
    }

    public function down(): void
    {
        // Откат кодировки таблиц не выполняем — данные уже могут содержать utf8mb4.
    }
};
