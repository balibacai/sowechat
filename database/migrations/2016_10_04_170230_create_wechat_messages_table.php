<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWechatMessagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wechat_messages', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type');
            $table->text('content');
            $table->string('from_user_name')->nullable();
            $table->string('from_user_nick')->nullable();
            $table->string('to_user_name')->nullable();
            $table->string('to_user_nick')->nullable();
            $table->longText('info');
            $table->nullableTimestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wechat_messages');
    }
}
