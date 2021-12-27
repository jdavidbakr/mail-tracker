<?php

namespace jdavidbakr\MailTracker\Tests;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Event;
use jdavidbakr\MailTracker\Jobs\MailgunRecordComplaintJob;
use jdavidbakr\MailTracker\Model\SentEmail;
use jdavidbakr\MailTracker\Jobs\SnsRecordComplaintJob;
use jdavidbakr\MailTracker\Events\ComplaintMessageEvent;

class RecordComplaintJobTest extends SetUpTest
{
    /**
     * @test
     */
    public function sns_it_marks_the_email_as_unsuccessful()
    {
        Event::fake();
        $track = SentEmail::create([
                'hash' => Str::random(32),
            ]);
        $message_id = Str::uuid();
        $track->message_id = $message_id;
        $track->save();
        $message = (object)[
            'mail' => (object)[
                'messageId' => $message_id,
            ],
            'complaint' => (object)[
                'timestamp' => 12345,
                'complainedRecipients' => (object)[
                    (object)[
                       'emailAddress' => 'recipient@example.com'
                    ]
                ],
            ]
        ];
        $job = new SnsRecordComplaintJob($message);

        $job->handle();

        $track = $track->fresh();
        $meta = $track->meta;
        $this->assertTrue($meta->get('complaint'));
        $this->assertFalse($meta->get('success'));
        $this->assertEquals(12345, $meta->get('complaint_time'));
        $this->assertEquals(json_decode(json_encode($message), true), $meta->get('sns_message_complaint'));
        Event::assertDispatched(ComplaintMessageEvent::class, function ($event) use ($track) {
            return $event->email_address === 'recipient@example.com' &&
                $event->sent_email->hash === $track->hash;
        });
    }


    /**
     * @test
     */
    public function mailgun_it_marks_the_email_as_unsuccessful()
    {
        Event::fake();
        $track = SentEmail::create([
            'hash' => Str::random(32),
        ]);
        $message_id = Str::uuid();
        $track->message_id = $message_id;
        $track->save();
        $eventData = [
            'event' => 'complained',
            'id' => 'ncV2XwymRUKbPek_MIM-Gw',
            'timestamp' => 12345,
            'log-level' => 'warn',
            'recipient' => 'recipient@example.com',
            'tags' => [],
            'campaigns' => [],
            'user-variables' => [],
            'flags' => ['is-test-mode' => false,],
            'message' =>
                [
                    'headers' =>
                        [
                            'to' => 'recipient@example.com',
                            'message-id' => $message_id,
                            'from' => 'John Doe <sender@example.com>',
                            'subject' => 'This is the subject.',
                        ],
                    'attachments' =>
                        [
                        ],
                    'size' => 18937,
                ],
        ];
        $job = new MailgunRecordComplaintJob($eventData);

        $job->handle();

        $track = $track->fresh();
        $meta = $track->meta;
        $this->assertTrue($meta->get('complaint'));
        $this->assertFalse($meta->get('success'));
        $this->assertEquals(12345, $meta->get('complaint_time'));
        $this->assertEquals(json_decode(json_encode($eventData), true), $meta->get('mailgun_message_complaint'));
        Event::assertDispatched(ComplaintMessageEvent::class, function ($event) use ($track) {
            return $event->email_address === 'recipient@example.com' &&
                $event->sent_email->hash === $track->hash;
        });
    }
}
