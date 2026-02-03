<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use jdavidbakr\MailTracker\MailTracker;

class AddSenderEmailAndNameToSentEmailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection(MailTracker::sentEmailModel()->getConnectionName())->table('sent_emails', function (Blueprint $table) {
            $table->string('recipient_email')->nullable()->after('headers');
            $table->string('recipient_name')->nullable()->after('headers');
            $table->string('sender_email')->nullable()->after('headers');
            $table->string('sender_name')->nullable()->after('headers');
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
            $table->dropColumn('sender_name');
            $table->dropColumn('sender_email');
            $table->dropColumn('recipient_name');
            $table->dropColumn('recipient_email');
        });
    }
}
