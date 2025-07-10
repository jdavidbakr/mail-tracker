<?php

namespace jdavidbakr\MailTracker\Listener;

use jdavidbakr\MailTracker\Events\ValidLinkEvent;

class DomainExistsInContentListener
{
    public function handle(ValidLinkEvent $event): void
    {
        $url_host = parse_url($event->url, PHP_URL_HOST);
        // If logging of content is on then
        if (config('mail-tracker.log-content', true)) {
            if ($event->sent_email && !empty($event->sent_email->domains_in_context) && in_array($url_host, $event->sent_email->domains_in_context)) {
                $event->valid = true;
            }
        }
    }
}
