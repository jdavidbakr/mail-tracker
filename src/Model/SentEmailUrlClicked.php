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
      return $this->belongsTo(SentEmail::class,'sent_email_id');
    }
}
