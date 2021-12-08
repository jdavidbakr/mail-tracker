<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMessageIdToSentEmailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $model = config('mail-tracker.sent_email_model');
        Schema::connection((new $model())->getConnectionName())->table('sent_emails', function (Blueprint $table) {
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
        $model = config('mail-tracker.sent_email_model');
        Schema::connection((new $model())->getConnectionName())->table('sent_emails', function (Blueprint $table) {
            $table->dropColumn('message_id');
        });
        Schema::connection((new $model())->getConnectionName())->table('sent_emails', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
}
