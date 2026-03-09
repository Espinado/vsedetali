<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('inn', 50)->nullable()->index();
            $table->text('legal_address')->nullable();
            $table->string('vat_number', 50)->nullable();
            $table->text('bank_details')->nullable();
            $table->decimal('credit_limit', 12, 2)->nullable();
            $table->string('contract_number', 100)->nullable()->index();
            $table->date('contract_date')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
