<?php

namespace jdavidbakr\MailTracker\Events;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use jdavidbakr\MailTracker\Contracts\SentEmailModel;

class LinkClickedEvent implements ShouldQueue
{
    use SerializesModels;

    public $sent_email;
    public $ip_address;
    public $link_url;

    /**
     * Create a new event instance.
     *
     * @param Model|SentEmailModel $sent_email
     * @param string $ip_address
     * @param string $link_url
     */
    public function __construct(Model|SentEmailModel $sent_email, $ip_address, $link_url)
    {
        $this->sent_email = $sent_email;
        $this->ip_address = $ip_address;
        $this->link_url = $link_url;
    }
}
