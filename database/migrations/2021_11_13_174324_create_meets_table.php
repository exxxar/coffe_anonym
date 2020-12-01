<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMeetsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::disableForeignKeyConstraints();
        Schema::create('meets', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('user1_id')->nullable();
            $table->uuid('user2_id')->nullable();
            $table->uuid('event_id')->nullable();

            $table->integer('rating')->default(0);
            $table->boolean('is_online')->default(true);
            $table->boolean('is_success')->default(true);
            $table->longText("short_comment");
            $table->date("meet_date");

            $table->timestamps();
        });
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('meets');
    }
}
