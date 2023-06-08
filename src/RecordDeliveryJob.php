<?php

namespace jdavidbakr\MailTracker;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Event;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use jdavidbakr\MailTracker\Events\EmailDeliveredEvent;

class RecordDeliveryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $message;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    public function __construct($message)
    {
        $this->message = $message;
    }

    public function retryUntil()
    {
        return now()->addDays(5);
    }

    public function handle()
    {
        $sent_email = MailTracker::sentEmailModel()->newQuery()->where('message_id', $this->message->mail->messageId)->first();
        if ($sent_email) {
            $meta = collect($sent_email->meta);
            $meta->put('smtpResponse', $this->message->delivery->smtpResponse);
            $meta->put('success', true);
            $meta->put('delivered_at', $this->message->delivery->timestamp);
            $meta->put('sns_message_delivery', $this->message); // append the full message received from SNS to the 'meta' field
            $sent_email->meta = $meta;
            $sent_email->save();

            foreach ($this->message->delivery->recipients as $recipient) {
                Event::dispatch(new EmailDeliveredEvent($recipient, $sent_email));
            }
        }
    }
}
