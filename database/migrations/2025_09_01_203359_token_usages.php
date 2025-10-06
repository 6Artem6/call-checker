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
        Schema::create('token_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id'); // FK на account_oauth2
            $table->string('model'); // GPT-4, GPT-3.5, etc
            $table->unsignedBigInteger('prompt_tokens')->default(0);
            $table->unsignedBigInteger('completion_tokens')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);

            $table->decimal('usd_cost', 10, 6);   // стоимость в долларах по прайсу
            $table->decimal('fx_used', 10, 6);    // курс usd_rub
            $table->decimal('margin_used', 5, 2); // коэффициент маржи, напр. 1.3
            $table->decimal('rub_cost', 12, 2);   // сколько реально списано в рублях

            $table->string('message_id')->nullable(); // id сообщения, где списали
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('token_usages');
    }
};
