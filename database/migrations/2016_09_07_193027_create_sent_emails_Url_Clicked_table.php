<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use jdavidbakr\MailTracker\MailTracker;

class CreateSentEmailsUrlClickedTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection(MailTracker::sentEmailUrlClickedModel()->getConnectionName())->create('sent_emails_url_clicked', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('sent_email_id')->unsigned();
            $table->foreign('sent_email_id')->references('id')->on('sent_emails')->onDelete('cascade');
            $table->text('url')->nullable();
            $table->char('hash', 32);
            $table->integer('clicks')->default('1');
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
        Schema::connection(MailTracker::sentEmailUrlClickedModel()->getConnectionName())->drop('sent_emails_url_clicked');
    }
}
