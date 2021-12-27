<?php

namespace jdavidbakr\MailTracker\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use jdavidbakr\MailTracker\Events\ComplaintMessageEvent;

class MailgunRecordComplaintJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * See message structure
     * @docs https://documentation.mailgun.com/en/latest/api-events.html#event-structure
     */
    public array $eventData;

    public function __construct($eventData)
    {
        $this->eventData = $eventData;
    }

    public function retryUntil()
    {
        return now()->addDays(5);
    }

    public function handle()
    {
        $model = config('mail-tracker.sent_email_model');
        $messageId = Arr::get($this->eventData, 'message.headers.message-id');

        $sent_email = $model::where('message_id', $messageId)->first();
        if ($sent_email) {
            $meta = collect($sent_email->meta);
            $meta->put('complaint', true);
            $meta->put('success', false);
            $meta->put('complaint_time',  Arr::get($this->eventData, 'timestamp'));
            $meta->put('mailgun_message_complaint', $this->eventData); // append the full message received from Mailgun to the 'meta' field
            $sent_email->meta = $meta;
            $sent_email->save();

            Event::dispatch(new ComplaintMessageEvent(Arr::get($this->eventData, 'recipient'), $sent_email));
        }
    }
}
