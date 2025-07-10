<?php

namespace jdavidbakr\MailTracker\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ValidateSignature as Middleware;
use Illuminate\Support\Facades\Event;
use jdavidbakr\MailTracker\Events\ValidLinkEvent;
use jdavidbakr\MailTracker\Exceptions\BadUrlLink;
use jdavidbakr\MailTracker\MailTracker;
use jdavidbakr\MailTracker\Model\SentEmail;

class ValidateSignature extends Middleware
{
    public function handle($request, Closure $next, $relative = null)
    {
        $ignore = property_exists($this, 'except') ? $this->except : $this->ignore;

        $hash = $request->get('h');
        $url  = $request->get('l');

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new BadUrlLink('Mail hash: ' . $hash . ', URL: ' . $url);
        }

        // If the signature is valid then we know that it has not been tampered with so continue
        if ($request->hasValidSignatureWhileIgnoring($ignore, $relative !== 'relative')) {
            return $next($request);
        }

        // If the signature is not valid then we need to check if the link is valid
        /** @var SentEmail $tracker */
        if ($tracker = MailTracker::sentEmailModel()->newQuery()->where('hash', $hash)->first()) {
            // If the link is not from a valid signed route then determine if the link is valid
            $event = new ValidLinkEvent($tracker, $url);

            Event::dispatch($event);

            if ($event->valid) {
                return $next($request);
            }
        }

        return redirect(config('mail-tracker.redirect-missing-links-to') ?: '/');
    }
}
