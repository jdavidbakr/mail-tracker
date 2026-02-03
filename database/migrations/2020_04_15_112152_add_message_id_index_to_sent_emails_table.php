<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use jdavidbakr\MailTracker\MailTracker;

class AddMessageIdIndexToSentEmailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection(MailTracker::sentEmailModel()->getConnectionName())->table('sent_emails', function (Blueprint $table) {
            $table->index('message_id');
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
            $table->dropIndex('sent_emails_message_id_index');
        });
    }
}
