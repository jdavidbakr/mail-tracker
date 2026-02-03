<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use jdavidbakr\MailTracker\MailTracker;

class AddFirstOpenAndFirstClickColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection(MailTracker::sentEmailModel()->getConnectionName())->table('sent_emails', function (Blueprint $table) {
            $table->datetime('clicked_at')->nullable()->after('updated_at');
            $table->datetime('opened_at')->nullable()->after('updated_at');
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
            $table->dropColumn('opened_at');
            $table->dropColumn('clicked_at');
        });
    }
}
