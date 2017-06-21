<?php

namespace jdavidbakr\MailTracker\Events;

use jdavidbakr\MailTracker\Model\SentEmail;
use jdavidbakr\MailTracker\Model\SentEmailUrlClicked;
use Illuminate\Queue\SerializesModels;

class LinkClickedEvent
{
    use SerializesModels;

    public $sent_email;

    /**
     * Create a new event instance.
     *
     * @param  sent_email  $sent_email
     * @param  url_clicked  $url_clicked
     * @return void
     */
    public function __construct(SentEmail $sent_email, SentEmailUrlClicked $url_clicked)
    {
        $this->sent_email = $sent_email;
        $this->url_clicked = $url_clicked;
    }
}
