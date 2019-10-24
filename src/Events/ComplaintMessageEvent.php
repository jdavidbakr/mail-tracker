<?php

namespace jdavidbakr\MailTracker\Events;

use jdavidbakr\MailTracker\Model\SentEmail;
use Illuminate\Queue\SerializesModels;

class ComplaintMessageEvent
{
    use SerializesModels;

    public $email_address;

    /**
     * Create a new event instance.
     *
     * @param  email_address  $email_address
     * @return void
     */
    public function __construct($email_address)
    {
        $this->email_address = $email_address;
    }
}
