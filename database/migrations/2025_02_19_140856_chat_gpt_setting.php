<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('chat_gpt_settings', function (Blueprint $table) {
            $table->id('setting_id');
            $table->integer('account_id');
            $table->text('prompt')->nullable();
            $table->decimal('temperature', 3, 2)->default(0.5);
            $table->string('model', 255);
            $table->string('assistant_id', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('chat_gpt_settings');
    }
};