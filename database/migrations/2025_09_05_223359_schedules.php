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
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('setting_id');
            $table->unsignedTinyInteger('weekday'); // 0-6 или 1-7
            $table->time('time_from');
            $table->time('time_to');
            $table->timestamps();

            $table->foreign('setting_id')->references('setting_id')->on('chat_gpt_settings')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
