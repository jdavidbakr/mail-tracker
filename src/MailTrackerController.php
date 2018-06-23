<?php

namespace jdavidbakr\MailTracker;

use Illuminate\Http\Request;
use Response;
use Event;

use App\Http\Requests;
use Illuminate\Routing\Controller;

use jdavidbakr\MailTracker\Events\ViewEmailEvent;
use jdavidbakr\MailTracker\Events\LinkClickedEvent;

class MailTrackerController extends Controller
{
    public function getT($hash)
    {
    	// Create a 1x1 ttransparent pixel and return it
    	$pixel = sprintf('%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%',71,73,70,56,57,97,1,0,1,0,128,255,0,192,192,192,0,0,0,33,249,4,1,0,0,0,0,44,0,0,0,0,1,0,1,0,0,2,2,68,1,0,59);
    	$response = Response::make($pixel, 200);
    	$response->header('Content-type','image/gif');
    	$response->header('Content-Length',42);
    	$response->header('Cache-Control','private, no-cache, no-cache=Set-Cookie, proxy-revalidate');
    	$response->header('Expires','Wed, 11 Jan 2000 12:59:00 GMT');
    	$response->header('Last-Modified','Wed, 11 Jan 2006 12:59:00 GMT');
    	$response->header('Pragma','no-cache');

		$tracker = Model\SentEmail::where('hash',$hash)
			->first();
		if($tracker) {
			$tracker->opens++;
			$tracker->save();
            Event::fire(new ViewEmailEvent($tracker));
		}

		return $response;
    }

    public function getL($url, $hash)
    {
    	$url = base64_decode(str_replace("$","/",$url));
    	$tracker = Model\SentEmail::where('hash',$hash)
    		->first();
    	if($tracker) {
    		$tracker->clicks++;
    		$tracker->save();
            $url_clicked = Model\SentEmailUrlClicked::where('url',$url)->where('hash', $hash)->first();
            if ($url_clicked) {
                $url_clicked->clicks++;
                $url_clicked->save();
            } else {
                $url_clicked = Model\SentEmailUrlClicked::create([
                    'sent_email_id' => $tracker->id,
                    'url' => $url,
                    'hash' => $tracker->hash,
                ]);
            }
            Event::fire(new LinkClickedEvent($tracker));
    	}

    	return redirect($url);
    }
}
