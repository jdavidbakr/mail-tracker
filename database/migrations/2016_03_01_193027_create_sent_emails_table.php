<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSentEmailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $model = config('mail-tracker.sent_email_model');
        Schema::connection((new $model)->getConnectionName())->create('sent_emails', function (Blueprint $table) {
            $table->increments('id');
            $table->char('hash', 32)->unique();
            $table->text('headers')->nullable();
            $table->string('subject')->nullable();
            $table->text('content')->nullable();
            $table->integer('opens')->nullable();
            $table->integer('clicks')->nullable();
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
        $model = config('mail-tracker.sent_email_model');
        Schema::connection((new $model())->getConnectionName())->drop('sent_emails');
    }
}
