<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSenderEmailAndNameToSentEmailsTable extends Migration
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
        $model = config('mail-tracker.sent_email_model');
        Schema::connection((new $model())->getConnectionName())->table('sent_emails', function (Blueprint $table) {
            $table->dropColumn('sender_name');
            $table->dropColumn('sender_email');
            $table->dropColumn('recipient_name');
            $table->dropColumn('recipient_email');
        });
    }
}
