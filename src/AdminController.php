<?php

namespace jdavidbakr\MailTracker;

use Illuminate\Http\Request;
use Response;

use App\Http\Requests;
use Illuminate\Routing\Controller;

use jdavidbakr\MailTracker\Model\SentEmail;
use jdavidbakr\MailTracker\Model\SentEmailUrlClicked;

use Mail;

class AdminController extends Controller
{
    /**
     * Sent email search
     */
    public function postSearch(Request $request)
    {
        session(['mail-tracker-index-search' => $request->search]);
        return redirect(route('mailTracker_Index'));
    }

    /**
     * Clear search
     */
    public function clearSearch()
    {
        session(['mail-tracker-index-search' => null]);
        return redirect(route('mailTracker_Index'));
    }

    /**
     * Index.
     *
     * @return \Illuminate\Http\Response
     */
    public function getIndex()
    {
        session(['mail-tracker-index-page' => request()->page]);
        $search = session('mail-tracker-index-search');

        $query = SentEmail::query();

        if ($search) {
            $terms = explode(" ", $search);
            foreach ($terms as $term) {
                $query->where(function ($q) use ($term) {
                    $q->where('sender', 'like', '%'.$term.'%')
                        ->orWhere('recipient', 'like', '%'.$term.'%')
                        ->orWhere('subject', 'like', '%'.$term.'%');
                });
            }
        }
        $query->orderBy('created_at', 'desc');

        $emails = $query->paginate(config('mail-tracker.emails-per-page'));

        return \View('emailTrakingViews::index')->with('emails', $emails);
    }

    /**
     * Show Email.
     *
     * @return \Illuminate\Http\Response
     */
    public function getShowEmail($id)
    {
        $email = SentEmail::where('id', $id)->first();
        return \View('emailTrakingViews::show')->with('email', $email);
    }

    /**
     * Url Detail.
     *
     * @return \Illuminate\Http\Response
     */
    public function getUrlDetail($id)
    {
        $detalle = SentEmailUrlClicked::where('sent_email_id', $id)->get();
        if (!$detalle) {
            return back();
        }
        return \View('emailTrakingViews::url_detail')->with('details', $detalle);
    }

    /**
     * SMTP Detail.
     *
     * @return \Illuminate\Http\Response
     */
    public function getSMTPDetail($id)
    {
        $detalle = SentEmail::find($id);
        if (!$detalle) {
            return back();
        }
        return \View('emailTrakingViews::smtp_detail')->with('details', $detalle);
    }
}
