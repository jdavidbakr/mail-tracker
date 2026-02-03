<?php

namespace jdavidbakr\MailTracker\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use jdavidbakr\MailTracker\Contracts\SentEmailModel;

class ValidLinkEvent
{
    use Dispatchable;

    public $valid = false;
    public $sent_email;
    public $url;

    public function __construct(Model|SentEmailModel $sent_email, $url)
    {
        $this->sent_email = $sent_email;
        $this->url        = $url;
    }
}
