<?php

namespace jdavidbakr\MailTracker;

use Event;
use Response;
use App\Http\Requests;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use jdavidbakr\MailTracker\RecordTrackingJob;
use jdavidbakr\MailTracker\RecordLinkClickJob;
use jdavidbakr\MailTracker\Events\ViewEmailEvent;
use jdavidbakr\MailTracker\Exceptions\BadUrlLink;
use jdavidbakr\MailTracker\Events\LinkClickedEvent;

class MailTrackerController extends Controller
{
    public function getT($hash)
    {
        // Create a 1x1 ttransparent pixel and return it
        $pixel = sprintf('%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c', 71, 73, 70, 56, 57, 97, 1, 0, 1, 0, 128, 255, 0, 192, 192, 192, 0, 0, 0, 33, 249, 4, 1, 0, 0, 0, 0, 44, 0, 0, 0, 0, 1, 0, 1, 0, 0, 2, 2, 68, 1, 0, 59);
        $response = Response::make($pixel, 200);
        $response->header('Content-type', 'image/gif');
        $response->header('Content-Length', 42);
        $response->header('Cache-Control', 'private, no-cache, no-cache=Set-Cookie, proxy-revalidate');
        $response->header('Expires', 'Wed, 11 Jan 2000 12:59:00 GMT');
        $response->header('Last-Modified', 'Wed, 11 Jan 2006 12:59:00 GMT');
        $response->header('Pragma', 'no-cache');

        $tracker = Model\SentEmail::where('hash', $hash)
            ->first();
        if ($tracker) {
            RecordTrackingJob::dispatch($tracker)
                ->onQueue(config('mail-tracker.tracker-queue'));
        }

        return $response;
    }

    public function getL($url, $hash)
    {
        $url = base64_decode(str_replace("$", "/", $url));
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new BadUrlLink('Mail hash: '.$hash);
        }
        return $this->linkClicked($url, $hash);
    }

    public function getN(Request $request)
    {
        $url = $request->l;
        $hash = $request->h;
        return $this->linkClicked($url, $hash);
    }

    protected function linkClicked($url, $hash)
    {
        $tracker = Model\SentEmail::where('hash', $hash)
            ->first();
        if ($tracker) {
            RecordLinkClickJob::dispatch($tracker, $url)
                ->onQueue(config('mail-tracker.tracker-queue'));
            return redirect($url);
        }

        throw new BadUrlLink('Mail hash: '.$hash);
    }
}
