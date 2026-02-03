<?php

namespace jdavidbakr\MailTracker\Events;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use jdavidbakr\MailTracker\Contracts\SentEmailModel;

class EmailDeliveredEvent implements ShouldQueue
{
    use SerializesModels;

    public $email_address;
    public $sent_email;

    /**
     * Create a new event instance.
     *
     * @param string $email_address
     * @param Model|SentEmailModel|null $sent_email
     */
    public function __construct($email_address, Model|SentEmailModel|null $sent_email = null)
    {
        $this->email_address = $email_address;
        $this->sent_email = $sent_email;
    }
}
