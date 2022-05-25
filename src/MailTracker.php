<?php

namespace jdavidbakr\MailTracker;

use Exception;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use jdavidbakr\MailTracker\Events\EmailSentEvent;
use jdavidbakr\MailTracker\Model\SentEmail;
use jdavidbakr\MailTracker\Model\SentEmailUrlClicked;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\Multipart\AlternativePart;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Part\Multipart\RelatedPart;
use Symfony\Component\Mime\Part\TextPart;

class MailTracker
{
    // Set this to "false" to skip this library migrations
    public static $runsMigrations = true;

    protected $hash;

    /**
     * Configure this library to not register its migrations.
     *
     * @return static
     */
    public static function ignoreMigrations()
    {
        static::$runsMigrations = false;

        return new static;
    }

    /**
     * Inject the tracking code into the message
     */
    public function messageSending(MessageSending $event)
    {
        $message = $event->message;

        // Create the trackers
        $this->createTrackers($message);

        // Purge old records
        $this->purgeOldRecords();
    }

    public function messageSent(MessageSent $event)
    {
        if ((config('mail.default') ?? config('mail.driver')) == 'ses') {
            $this->updateSesMessageId($event->sent);
        } else {
            $this->updateMessageId($event->sent);
        }
    }

    protected function updateMessageId(SentMessage $message)
    {
        // Get the SentEmail object
        $headers = $message->getOriginalMessage()->getHeaders();
        $hash = optional($headers->get('X-Mailer-Hash'))->getBody();
        $sent_email = SentEmail::where('hash', $hash)->first();

        // Get info about the message-id from SES
        if ($sent_email) {
            $sent_email->message_id = $message->getMessageId();
            $sent_email->save();
        }
    }

    protected function updateSesMessageId(SentMessage $message)
    {
        // Get the SentEmail object
        $headers = $message->getOriginalMessage()->getHeaders();
        $hash = optional($headers->get('X-Mailer-Hash'))->getBody();
        $sent_email = SentEmail::where('hash', $hash)->first();

        // Get info about the message-id from SES
        if ($sent_email) {
            $sent_email->message_id = $headers->get('X-SES-Message-ID')->getBody();
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
     * @param  Swift_Mime_Message $message
     * @return void
     */
    protected function createTrackers($message)
    {
        foreach ($message->getTo() as $toAddress) {
            $to_email = $toAddress->getAddress();
            $to_name = $toAddress->getName();
            foreach ($message->getFrom() as $fromAddress) {
                $from_email = $fromAddress->getAddress();
                $from_name = $fromAddress->getName();
                $headers = $message->getHeaders();
                if ($headers->get('X-No-Track')) {
                    // Don't send with this header
                    $headers->remove('X-No-Track');
                    // Don't track this email
                    continue;
                }
                do {
                    $hash = app(Str::class)->random(32);
                    $used = SentEmail::where('hash', $hash)->count();
                } while ($used > 0);
                $headers->addTextHeader('X-Mailer-Hash', $hash);
                $subject = $message->getSubject();

                $original_content = $message->getBody();
                $original_html = '';
                if(
                    ($original_content instanceof(AlternativePart::class)) ||
                    ($original_content instanceof(MixedPart::class)) ||
                    ($original_content instanceof(RelatedPart::class))
                ) {
                    $messageBody = $message->getBody() ?: [];
                    $newParts = [];
                    foreach($messageBody->getParts() as $part) {
                        if (method_exists($part, 'getParts')) {
                            foreach ($part->getParts() as $p) {
                                if($p->getMediaSubtype() == 'html') {
                                    $original_html = $p->getBody();
                                    $newParts[] = new TextPart(
                                        $this->addTrackers($original_html, $hash),
                                        $message->getHtmlCharset(),
                                        $p->getMediaSubtype(),
                                        null
                                    );

                                    break;
                                }
                            }
                        }

                        if($part->getMediaSubtype() == 'html') {
                            $original_html = $part->getBody();
                            $newParts[] = new TextPart(
                                $this->addTrackers($original_html, $hash),
                                $message->getHtmlCharset(),
                                $part->getMediaSubtype(),
                                null
                            );
                        } else {
                            $newParts[] = $part;
                        }
                    }
                    $message->setBody(new AlternativePart(...$newParts));
                } else {
                    $original_html = $original_content->getBody();
                    if($original_content->getMediaSubtype() == 'html') {
                        $message->setBody(new TextPart(
                                $this->addTrackers($original_html, $hash),
                                $message->getHtmlCharset(),
                                $original_content->getMediaSubtype(),
                                null
                            )
                        );
                    }
                }

                $tracker = SentEmail::create([
                    'hash' => $hash,
                    'headers' => $headers->toString(),
                    'sender_name' => $from_name,
                    'sender_email' => $from_email,
                    'recipient_name' => $to_name,
                    'recipient_email' => $to_email,
                    'subject' => $subject,
                    'content' => config('mail-tracker.log-content', true) ?
                        (Str::length($original_html) > config('mail-tracker.content-max-size', 65535) ?
                            Str::substr($original_html, 0, config('mail-tracker.content-max-size', 65535)) . '...' :
                            $original_html)
                        : null,
                    'opens' => 0,
                    'clicks' => 0,
                    'message_id' => Str::uuid(),
                    'meta' => [],
                ]);

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
        if (config('mail-tracker.expire-days') > 0) {
            $emails = SentEmail::where('created_at', '<', \Carbon\Carbon::now()
                ->subDays(config('mail-tracker.expire-days')))
                ->select('id')
                ->get();
            SentEmailUrlClicked::whereIn('sent_email_id', $emails->pluck('id'))->delete();
            SentEmail::whereIn('id', $emails->pluck('id'))->delete();
        }
    }
}
