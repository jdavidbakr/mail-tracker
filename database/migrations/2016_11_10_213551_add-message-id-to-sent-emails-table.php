<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use jdavidbakr\MailTracker\MailTracker;

class AddMessageIdToSentEmailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection(MailTracker::sentEmailModel()->getConnectionName())->table('sent_emails', function (Blueprint $table) {
            $table->string('message_id')->nullable();
            $table->text('meta')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection(MailTracker::sentEmailModel()->getConnectionName())->table('sent_emails', function (Blueprint $table) {
            $table->dropColumn('message_id');
        });
        Schema::connection(MailTracker::sentEmailModel()->getConnectionName())->table('sent_emails', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
}
