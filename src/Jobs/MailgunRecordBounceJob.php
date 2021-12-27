<?php

namespace jdavidbakr\MailTracker\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use jdavidbakr\MailTracker\Events\PermanentBouncedMessageEvent;
use jdavidbakr\MailTracker\Events\TransientBouncedMessageEvent;

class MailgunRecordBounceJob implements ShouldQueue
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
            $current_codes = [];
            if ($meta->has('failures')) {
                $current_codes = $meta->get('failures');
            }
            $current_codes[] = ['emailAddress' => Arr::get($this->eventData, 'recipient')];
            $meta->put('failures', $current_codes);
            $meta->put('success', false);
            $meta->put('mailgun_message_bounce', $this->eventData); // append the full message received from Mailgun to the 'meta' field
            $sent_email->meta = $meta;
            $sent_email->save();

            if (Arr::has($this->eventData, 'reject')) {
                // handle rejection
                $this->permanentBounce($sent_email);
            } elseif (Arr::get($this->eventData, 'severity') === 'permanent') {
                $this->permanentBounce($sent_email);
            } else {
                $this->transientBounce($sent_email);
            }
        }
    }

    protected function permanentBounce($sent_email)
    {
        Event::dispatch(new PermanentBouncedMessageEvent(Arr::get($this->eventData, 'recipient'), $sent_email));
    }

    protected function transientBounce($sent_email)
    {
        Event::dispatch(new TransientBouncedMessageEvent(
            Arr::get($this->eventData, 'recipient'),
            Arr::get($this->eventData, 'severity'),
            Arr::get($this->eventData, 'delivery-status.message') . ' ' . Arr::get($this->eventData, 'delivery-status.description'),
            $sent_email
        ));
    }
}
