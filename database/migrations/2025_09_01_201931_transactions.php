<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{/**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->enum('type', ['payment', 'usage', 'subscription', 'refund']);
            $table->decimal('usd_cost', 10, 2); // сумма в $
            $table->decimal('fx_used', 10, 4); // курс $→₽
            $table->decimal('margin_used', 5, 2)->default(1.00); // коэффициент маржи
            $table->string('message_id')->nullable(); // связка с сообщением
            $table->enum('status', ['completed', 'pending', 'failed'])->default('pending');
            $table->timestamps();

//            $table->foreign('client_id')->references('account_id')->on('account_oauth2')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
