<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use jdavidbakr\MailTracker\Model\SentEmailUrlClicked;

class AddHashIndexToSentEmailsUrlClickedTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
     Schema::connection((new SentEmailUrlClicked())->getConnectionName())->table('sent_emails_url_clicked', function (Blueprint $table) {
           $table->index('hash');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection((new SentEmailUrlClicked())->getConnectionName())->table('sent_emails_url_clicked', function (Blueprint $table) {
            $table->dropIndex('sent_emails_url_clicked_hash_index');
        });
    }
}
