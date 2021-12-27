<?php

namespace jdavidbakr\MailTracker;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use jdavidbakr\MailTracker\Jobs\MailgunRecordBounceJob;
use jdavidbakr\MailTracker\Jobs\MailgunRecordComplaintJob;
use jdavidbakr\MailTracker\Jobs\MailgunRecordDeliveryJob;

/**
 * Handle Mailgun webhooks.
 * @docs https://documentation.mailgun.com/en/latest/user_manual.html#webhooks-1
 */
class MailgunController extends Controller
{
    public function callback(Request $request)
    {
        $signatureData = $request->input('signature', []);

        // validate messages in production
        if (config('app.env') === 'production') {
            if (! $this->verifyWebhookSignature(
                Arr::get($signatureData, 'timestamp'),
                Arr::get($signatureData, 'token'),
                Arr::get($signatureData, 'signature')
            )) {
                abort(419);
            }
        }

        $eventData = $request->input('event-data', []);
        return $this->process_notification($eventData);
    }

    /**
     * This function verifies the webhook signature with your API key to to see if it is authentic.
     *
     * If this function returns FALSE, you must not process the request.
     * You should reject the request with status code 403 Forbidden.
     */
    public function verifyWebhookSignature(int $timestamp, string $token, string $signature): bool
    {
        if (empty($timestamp) || empty($token) || empty($signature)) {
            return false;
        }

        if (! config('services.mailgun.signing_key')) {
            Log::warning('Mailgun signing key is missing. Please add it to \'services.mailgun.signing_key\'');
            return false;
        }

        $hmac = hash_hmac('sha256', $timestamp.$token, config('services.mailgun.signing_key'));

        // hash_equals is constant time, but will not be introduced until PHP 5.6
        return hash_equals($hmac, $signature);
    }

    /**
     * @param array $eventData
     * @return string
     */
    protected function process_notification(array $eventData)
    {
        switch (Arr::get($eventData, 'event')) {
            case 'delivered':
                $this->process_delivery($eventData);
                break;
            case 'failed':
            case 'rejected':
                $this->process_bounce($eventData);
                break;
            case 'complained':
                $this->process_complaint($eventData);
                break;
        }
        return 'notification processed';
    }

    protected function process_delivery($eventData)
    {
        MailgunRecordDeliveryJob::dispatch($eventData)
            ->onQueue(config('mail-tracker.tracker-queue'));
    }

    public function process_bounce($eventData)
    {
        MailgunRecordBounceJob::dispatch($eventData)
            ->onQueue(config('mail-tracker.tracker-queue'));
    }

    public function process_complaint($eventData)
    {
        MailgunRecordComplaintJob::dispatch($eventData)
            ->onQueue(config('mail-tracker.tracker-queue'));
    }
}
