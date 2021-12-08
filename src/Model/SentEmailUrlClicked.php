<?php

namespace jdavidbakr\MailTracker\Model;

use Illuminate\Database\Eloquent\Model;

// use Model\SentEmail;

class SentEmailUrlClicked extends Model
{
    protected $table = 'sent_emails_url_clicked';

    protected $fillable = [
        'sent_email_id',
        'url',
        'hash',
        'clicks',
    ];

    public function getConnectionName()
    {
        $connName = config('mail-tracker.connection');
        return $connName ?: config('database.default');
    }

    public function email()
    {
        $model = config('mail-tracker.sent_email_model');
        return $this->belongsTo($model, 'sent_email_id');
    }
}
