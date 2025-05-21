<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('chat_gpt_files', function (Blueprint $table) {
            $table->id('file_id');
            $table->foreignId('setting_id')
                ->constrained('chat_gpt_settings', 'setting_id')
                ->onDelete('cascade');
            $table->string('original_name');
            $table->string('stored_name');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('chat_gpt_files');
    }
};
