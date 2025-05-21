<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Invoices extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yoomoney_account_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('RUB');
            $table->uuid('invoice_id')->unique();
            $table->string('payment_id')->nullable();
            $table->string('payment_link', 512)->nullable();
            $table->enum('status', ['waiting_for_capture', 'succeeded', 'canceled'])->default('waiting_for_capture');
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

//            $table->foreign('account_id')
//                ->references('account_id')
//                ->on('account_oauth2')
//                ->onDelete('cascade')
//                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
}

