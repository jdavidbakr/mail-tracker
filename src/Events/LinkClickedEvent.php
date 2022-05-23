<?php

namespace jdavidbakr\MailTracker\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use jdavidbakr\MailTracker\Model\SentEmail;

class LinkClickedEvent implements ShouldQueue
{
    use SerializesModels;

    public $sent_email;
    public $ip_address;
    public $link_url;

    /**
     * Create a new event instance.
     *
     * @param SentEmail $sent_email
     * @param string $ip_address
     * @param string $link_url
     */
    public function __construct(SentEmail $sent_email, $ip_address, $link_url)
    {
        $this->sent_email = $sent_email;
        $this->ip_address = $ip_address;
        $this->link_url = $link_url;
    }
}
