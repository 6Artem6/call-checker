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
        Schema::create('openai_model_pricing', function (Blueprint $table) {
            $table->id();
            $table->string('model')->unique();
            $table->decimal('input', 10, 4)->nullable();
            $table->decimal('cached', 10, 4)->nullable();
            $table->decimal('output', 10, 4)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('openai_model_pricing');
    }
};
