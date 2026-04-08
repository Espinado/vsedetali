<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Пустой model в vehicles давал в селекте «Модель» дублирующие option value="".
 * Совпадает с config('remains_stock_import.default_model_when_missing').
 */
return new class extends Migration
{
    public function up(): void
    {
        $placeholder = 'Общее';

        DB::table('vehicles')
            ->where(function ($q) {
                $q->whereNull('model')->orWhereRaw('TRIM(model) = ?', ['']);
            })
            ->update(['model' => $placeholder, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Не откатываем: нельзя надёжно отличить подставленное значение от реального «Общее».
    }
};
