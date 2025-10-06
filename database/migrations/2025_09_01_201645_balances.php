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
        Schema::create('balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->decimal('amount_rub', 15, 2)->default(0); // баланс в рублях
            $table->decimal('margin_coefficient', 5, 2)->default(1.00); // коэффициент маржи
            $table->decimal('min_charge_rub', 10, 2)->default(1.00); // минимальное списание за сообщение
            $table->decimal('low_balance_threshold', 10, 2)->default(100.00); // порог уведомления
            $table->timestamps();

//            $table->foreign('client_id')->references('account_id')->on('account_oauth2')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balances');
    }
};
