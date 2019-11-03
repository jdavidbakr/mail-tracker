<?php

namespace jdavidbakr\MailTracker\Events;

use jdavidbakr\MailTracker\Model\SentEmail;
use Illuminate\Queue\SerializesModels;

class ComplaintMessageEvent
{
    use SerializesModels;

    public $email_address;
    public $sent_email;

    /**
     * Create a new event instance.
     *
     * @param  email_address  $email_address
     * @param  sent_email  $sent_email
     * @return void
     */
    public function __construct($email_address, SentEmail $sent_email = null)
    {
        $this->email_address = $email_address;
        $this->sent_email = $sent_email;
    }
}
