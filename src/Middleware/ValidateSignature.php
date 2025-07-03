<?php

namespace jdavidbakr\MailTracker\Middleware;

use Closure;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Routing\Middleware\ValidateSignature as Middleware;
use Illuminate\Support\Carbon;
use jdavidbakr\MailTracker\MailTracker;
use jdavidbakr\MailTracker\Model\SentEmail;

class ValidateSignature extends Middleware
{
    public function handle($request, Closure $next, $relative = null)
    {
        $ignore = property_exists($this, 'except') ? $this->except : $this->ignore;

        if ($request->hasValidSignatureWhileIgnoring($ignore, $relative !== 'relative')) {
            return $next($request);
        }

        // Temporary measure to allow for legacy URLs that do not have a signature
        if (Carbon::now()->format('Y-m-d') < config('mail-tracker.signed_route_enforcement.start_date')) {
            // Is the tracked email within the grace period?
            /** @var SentEmail $tracker */
            if ($tracker = MailTracker::sentEmailModel()->newQuery()->where('hash', $request->get('h'))->first()) {
                if ($tracker->created_at->greaterThanOrEqualTo(Carbon::now()->subDays(config('mail-tracker.signed_route_enforcement.grace_period_days'))->startOfDay())) {
                    // If the email is within the grace period, we allow the redirect
                    return $next($request);
                }
            }
        }

        throw new InvalidSignatureException;
    }
}
