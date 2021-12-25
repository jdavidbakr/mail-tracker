<?php

namespace jdavidbakr\MailTracker;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use jdavidbakr\MailTracker\Events\EmailSentEvent;
use jdavidbakr\MailTracker\Model\SentEmailUrlClicked;

class MailTracker implements \Swift_Events_SendListener
{
    protected $hash;

    /**
     * Inject the tracking code into the message
     */
    public function beforeSendPerformed(\Swift_Events_SendEvent $event)
    {
        $message = $event->getMessage();

        // Create the trackers
        $this->createTrackers($message);

        // Purge old records
        $this->purgeOldRecords();
    }

    public function sendPerformed(\Swift_Events_SendEvent $event)
    {
        // If this was sent through SES, retrieve the data
        if ((config('mail.default') ?? config('mail.driver')) == 'ses') {
            $message = $event->getMessage();
            $this->updateSesMessageId($message);
        }
    }

    protected function updateSesMessageId($message)
    {
        $model = config('mail-tracker.sent_email_model');
        // Get the SentEmail object
        $headers = $message->getHeaders();
        $hash = optional($headers->get('X-Mailer-Hash'))->getFieldBody();
        $sent_email = $model::where('hash', $hash)->first();

        // Get info about the message-id from SES
        if ($sent_email) {
            $sent_email->message_id = $headers->get('X-SES-Message-ID')->getFieldBody();
            $sent_email->save();
        }
    }

    protected function addTrackers($html, $hash)
    {
        if (config('mail-tracker.inject-pixel')) {
            $html = $this->injectTrackingPixel($html, $hash);
        }
        if (config('mail-tracker.track-links')) {
            $html = $this->injectLinkTracker($html, $hash);
        }

        return $html;
    }

    protected function injectTrackingPixel($html, $hash)
    {
        // Append the tracking url
        $tracking_pixel = '<img border=0 width=1 alt="" height=1 src="'.route('mailTracker_t', [$hash]).'" />';

        $linebreak = app(Str::class)->random(32);
        $html = str_replace("\n", $linebreak, $html);

        if (preg_match("/^(.*<body[^>]*>)(.*)$/", $html, $matches)) {
            $html = $matches[1].$matches[2].$tracking_pixel;
        } else {
            $html = $html . $tracking_pixel;
        }
        $html = str_replace($linebreak, "\n", $html);

        return $html;
    }

    protected function injectLinkTracker($html, $hash)
    {
        $this->hash = $hash;

        $html = preg_replace_callback(
            "/(<a[^>]*href=[\"])([^\"]*)/",
            [$this, 'inject_link_callback'],
            $html
        );

        return $html;
    }

    protected function inject_link_callback($matches)
    {
        if (empty($matches[2])) {
            $url = app()->make('url')->to('/');
        } else {
            $url = str_replace('&amp;', '&', $matches[2]);
        }

        return $matches[1].route(
            'mailTracker_n',
            [
                'l' => $url,
                'h' => $this->hash
            ]
        );
    }

    /**
     * Legacy function
     *
     * @param [type] $url
     * @return boolean
     */
    public static function hash_url($url)
    {
        // Replace "/" with "$"
        return str_replace("/", "$", base64_encode($url));
    }

    /**
     * Create the trackers
     *
     * @param  \Swift_Mime_SimpleMessage $message
     * @return void
     */
    protected function createTrackers($message)
    {
        $model = config('mail-tracker.sent_email_model');
        foreach ($message->getTo() as $to_email => $to_name) {
            foreach ($message->getFrom() as $from_email => $from_name) {
                $headers = $message->getHeaders();
                if ($headers->get('X-No-Track')) {
                    // Don't send with this header
                    $headers->remove('X-No-Track');
                    // Don't track this email
                    continue;
                }
                do {
                    $hash = app(Str::class)->random(32);
                    $used = $model::where('hash', $hash)->count();
                } while ($used > 0);
                $headers->addTextHeader('X-Mailer-Hash', $hash);
                $subject = $message->getSubject();

                $original_content = $message->getBody();

                if ($message->getContentType() === 'text/html' ||
                    ($message->getContentType() === 'multipart/alternative' && $message->getBody()) ||
                    ($message->getContentType() === 'multipart/mixed' && $message->getBody())
                ) {
                    $message->setBody($this->addTrackers($message->getBody(), $hash));
                }

                foreach ($message->getChildren() as $part) {
                    if (strpos($part->getContentType(), 'text/html') === 0) {
                        $part->setBody($this->addTrackers($message->getBody(), $hash));
                    }
                }

                $logContent = config('mail-tracker.log-content', true);
                $logContentStrategy = config('mail-tracker.log-content-strategy', 'database');
                $dbLoggedContent = null;
                if ($logContent && $logContentStrategy === 'filesystem') {
                    // store body in html file
                    $basePath = config('mail-tracker.tracker-filesystem-folder', 'mail-tracker');
                    $fileSystem = config('mail-tracker.tracker-filesystem');
                    $contentFilePath = "{$basePath}/{$hash}.html";
                    try {
                        Storage::disk($fileSystem)->put($contentFilePath, $original_content);
                    } catch (\Exception $e) {
                        Log::warning($e->getMessage());
                        // fail silently
                    }
                } else if ($logContent && $logContentStrategy === 'database') {
                    $dbLoggedContent = strlen($original_content) > config('mail-tracker.content-max-size', 65535) ? substr($original_content, 0, config('mail-tracker.content-max-size', 65535)) . '...' : $original_content;
                }

                $tracker = new $model([
                    'hash' => $hash,
                    'headers' => $headers->toString(),
                    'sender_name' => $from_name,
                    'sender_email' => $from_email,
                    'recipient_name' => $to_name,
                    'recipient_email' => $to_email,
                    'subject' => $subject,
                    'content' => $dbLoggedContent,
                    'opens' => 0,
                    'clicks' => 0,
                    'message_id' => $message->getId(),
                    'meta' => $this->buildInitialMeta($contentFilePath ?? null),
                ]);

                // extract mailable linking info
                if ($headers->get('X-Mailable-Id') && $headers->get('X-Mailable-Type')) {
                    $tracker->mailable_type = $tracker->getHeader('X-Mailable-Type');
                    $tracker->mailable_id = $tracker->getHeader('X-Mailable-Id');
                    $headers->remove('X-Mailable-Type');
                    $headers->remove('X-Mailable-Id');
                    $tracker->headers = $headers->toString();
                }
                $tracker->save();

                Event::dispatch(new EmailSentEvent($tracker));
            }
        }
    }

    /**
     * Purge old records in the database
     *
     * @return void
     */
    protected function purgeOldRecords()
    {
        $model = config('mail-tracker.sent_email_model');
        if (config('mail-tracker.expire-days') > 0) {
            $emails = $model::where('created_at', '<', \Carbon\Carbon::now()
                ->subDays(config('mail-tracker.expire-days')))
                ->select('id', 'meta')
                ->get();
            // remove files
            $emails->each(function ($email) {
                if ($email->meta && ($filePath = $email->meta->get('content_file_path'))) {
                    Storage::disk(config('mail-tracker.tracker-filesystem'))->delete($filePath);
                }
            });
            SentEmailUrlClicked::whereIn('sent_email_id', $emails->pluck('id'))->delete();
            $model::whereIn('id', $emails->pluck('id'))->delete();
        }
    }

    /**
     * @param $contentFilePath
     * @return array
     */
    protected function buildInitialMeta($contentFilePath = null){
        $meta = [];
        if($contentFilePath !== null){
            $meta['content_file_path'] =  $contentFilePath;
        }

        if(config('mail-tracker.log-mail-driver')){
            $meta['mail_driver'] = config('mail.driver');
        }
        return $meta;
    }
}
