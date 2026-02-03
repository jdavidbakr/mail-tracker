<?php

namespace jdavidbakr\MailTracker\Model;

use Illuminate\Database\Eloquent\Model;
use jdavidbakr\MailTracker\Concerns\IsSentEmailUrlClickedModel;
use jdavidbakr\MailTracker\Contracts\SentEmailUrlClickedModel;


class SentEmailUrlClicked extends Model implements SentEmailUrlClickedModel
{
    use IsSentEmailUrlClickedModel;

    protected $table = 'sent_emails_url_clicked';

    protected $fillable = [
        'sent_email_id',
        'url',
        'hash',
        'clicks',
    ];
}
