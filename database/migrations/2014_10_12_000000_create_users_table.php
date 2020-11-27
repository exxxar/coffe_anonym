<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('telegram_chat_id')->unique()->nullable();
            $table->string('fio_from_telegram')->default('');
            $table->string('phone')->nullable();
            $table->smallInteger('age')->nullable();
            $table->smallInteger('sex')->nullable();

            $table->boolean('need_meeting')->default(true)->comment("Нужна ли встеча на следующей неделе");
            $table->integer('meet_in_week')->default(1)->comment("Число встреч в неделю");
            $table->integer('prefer_meet_in_week')->default(0)->comment("С кем предпочтительно встречаться"); //0 - мужчины, 1 - женщины, 2 - без разницы

            $table->json('location')->nullable();
            $table->json('settings')->nullable();

            $table->boolean('is_admin')->default(false);

            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
