<?php

namespace jdavidbakr\MailTracker;

use Event;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use jdavidbakr\MailTracker\Events\ValidActionEvent;
use jdavidbakr\MailTracker\Exceptions\BadUrlLink;
use Response;

class MailTrackerController extends Controller
{
    public function getT($hash)
    {
        // Create a 1x1 transparent pixel and return it
        $pixel    = sprintf('%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c', 71, 73, 70, 56, 57, 97, 1, 0, 1, 0, 128, 255, 0, 192, 192, 192, 0, 0, 0, 33, 249, 4, 1, 0, 0, 0, 0, 44, 0, 0, 0, 0, 1, 0, 1, 0, 0, 2, 2, 68, 1, 0, 59);
        $response = Response::make($pixel, 200);
        $response->header('Content-type', 'image/gif');
        $response->header('Content-Length', strlen($pixel));
        $response->header('Cache-Control', 'private, no-cache, no-cache=Set-Cookie, proxy-revalidate');
        $response->header('Expires', 'Wed, 11 Jan 2000 12:59:00 GMT');
        $response->header('Last-Modified', 'Wed, 11 Jan 2006 12:59:00 GMT');
        $response->header('Pragma', 'no-cache');

        $tracker = MailTracker::sentEmailModel()->newQuery()->where('hash', $hash)
            ->first();
        if ($tracker) {
            $event = new ValidActionEvent($tracker);

            \Illuminate\Support\Facades\Event::dispatch($event);

            if (!$event->skip) {
                RecordTrackingJob::dispatch($tracker, request()->ip())
                    ->onQueue(config('mail-tracker.tracker-queue'));
                if (!$tracker->opened_at) {
                    $tracker->opened_at = now();
                    $tracker->save();
                }
            }
        }

        return $response;
    }

    public function getN(Request $request)
    {
        $url  = $request->l;
        $hash = $request->h;

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new BadUrlLink('Mail hash: ' . $hash . ', URL: ' . $url);
        }

        return $this->linkClicked($url, $hash);
    }

    protected function linkClicked($url, $hash)
    {
        if (!$url) {
            $url = config('mail-tracker.redirect-missing-links-to') ?: '/';
        }

        $url_host = parse_url($url, PHP_URL_HOST);
        $tracker  = MailTracker::sentEmailModel()->newQuery()->where('hash', $hash)
            ->first();

        if ($tracker) {
            $event = new ValidActionEvent($tracker);

            \Illuminate\Support\Facades\Event::dispatch($event);

            if (!$event->skip) {
                RecordLinkClickJob::dispatch($tracker, $url, request()->ip())
                    ->onQueue(config('mail-tracker.tracker-queue'));

                // If no opened at but has a clicked event then we can assume that it was in fact opened, the tracking pixel may have been blocked
                if (config('mail-tracker.inject-pixel') && !$tracker->opened_at) {
                    $tracker->opened_at = now();
                    $tracker->save();
                }

                if (!$tracker->clicked_at) {
                    $tracker->clicked_at = now();
                    $tracker->save();
                }
            }
        }

        // Only check the domains in context if we are storing the content.. If we aren't then we will have to rely on the ::signedRoute to protect us from unwanted links
        // TODO: This can be removed eventually when we enforce the use of signed routes for all links with no grace period
        if (config('mail-tracker.log-content', true) && (!$tracker || empty($tracker->domains_in_context) || !in_array($url_host, $tracker->domains_in_context))) {
            return redirect(config('mail-tracker.redirect-missing-links-to') ?: '/');
        }

        return redirect($url);
    }
}
