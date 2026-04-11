<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $row = DB::table('settings')->where('key', 'store_name')->first();
        if ($row === null) {
            return;
        }

        $trimmed = trim((string) $row->value);
        if ($trimmed === '' || $trimmed === 'vsedetalki.ru') {
            return;
        }

        $normalized = Str::lower($trimmed);
        $legacy = [
            'vsedetalki',
            'vsedetali',
            'vsedetali.ru',
            'vse detali',
            'vsē detaļi',
        ];

        foreach ($legacy as $name) {
            if ($normalized === Str::lower($name)) {
                DB::table('settings')->where('key', 'store_name')->update(['value' => 'vsedetalki.ru']);
                Cache::forget('setting.store_name');

                return;
            }
        }
    }

    public function down(): void
    {
        // Не восстанавливаем прежнее значение названия.
    }
};
