<?php

namespace jdavidbakr\MailTracker\Traits;

trait HasSentEmails
{
    public function sentEmails()
    {
        $model = config('mail-tracker.sent_email_model');
        return $this->morphMany($model, 'mailable');
    }
}
