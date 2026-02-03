<?php

namespace jdavidbakr\MailTracker\Tests;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Event;
use jdavidbakr\MailTracker\MailTracker;
use jdavidbakr\MailTracker\RecordBounceJob;
use jdavidbakr\MailTracker\RecordComplaintJob;
use jdavidbakr\MailTracker\Events\ComplaintMessageEvent;

class RecordComplaintJobTest extends SetUpTest
{
    /**
     * @test
     */
    public function it_marks_the_email_as_unsuccessful()
    {
        Event::fake();
        $track = MailTracker::sentEmailModel()->newQuery()->create([
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
        $job = new RecordComplaintJob($message);

        $job->handle();

        $track = $track->fresh();
        $meta = $track->meta;
        $this->assertTrue($meta->get('complaint'));
        $this->assertFalse($meta->get('success'));
        $this->assertEquals(12345, $meta->get('complaint_time'));
        $this->assertEquals(json_decode(json_encode($message), true), $meta->get('sns_message_complaint'));
        Event::assertDispatched(ComplaintMessageEvent::class, function ($event) use ($track) {
            return $event->email_address == 'recipient@example.com' &&
                $event->sent_email->hash == $track->hash;
        });
    }
}
