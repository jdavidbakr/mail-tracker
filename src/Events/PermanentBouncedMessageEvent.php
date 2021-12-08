<?php

namespace jdavidbakr\MailTracker\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use jdavidbakr\MailTracker\Model\SentEmail;

class PermanentBouncedMessageEvent implements ShouldQueue
{
    use SerializesModels;

    public $email_address;
    public $sent_email;

    /**
     * Create a new event instance.
     *
     * @param string $email_address
     * @param SentEmail|null $sent_email
     */
    public function __construct($email_address, SentEmail $sent_email = null)
    {
        $this->email_address = $email_address;
        $this->sent_email = $sent_email;
    }
}
