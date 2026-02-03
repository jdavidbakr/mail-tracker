<?php

namespace jdavidbakr\MailTracker\Events;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use jdavidbakr\MailTracker\Contracts\SentEmailModel;

class ViewEmailEvent implements ShouldQueue
{
    use SerializesModels;

    public $sent_email;
    public $ip_address;

    /**
     * Create a new event instance.
     *
     * @param Model|SentEmailModel $sent_email
     * @param $ip_address
     */
    public function __construct(Model|SentEmailModel $sent_email, $ip_address)
    {
        $this->sent_email = $sent_email;
        $this->ip_address = $ip_address;
    }
}
