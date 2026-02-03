<?php

namespace jdavidbakr\MailTracker\Tests;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use jdavidbakr\MailTracker\Events\PermanentBouncedMessageEvent;
use jdavidbakr\MailTracker\Events\TransientBouncedMessageEvent;
use jdavidbakr\MailTracker\MailTracker;
use jdavidbakr\MailTracker\RecordBounceJob;

class RecordBounceJobTest extends SetUpTest
{
    /**
     * @test
     */
    public function it_handles_permanent_bounce()
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
            'bounce' => (object)[
                'bouncedRecipients' => (object)[
                    (object)[
                       'emailAddress' => 'recipient@example.com'
                    ]
                ],
                'bounceType' => 'Permanent'
            ]
        ];
        $job = new RecordBounceJob($message);

        $job->handle();

        $track = $track->fresh();
        $meta = $track->meta;
        $this->assertEquals([
            [
                'emailAddress' => 'recipient@example.com'
            ]
        ], $meta->get('failures'));
        $this->assertFalse($meta->get('success'));
        $this->assertEquals(json_decode(json_encode($message), true), $meta->get('sns_message_bounce'));
        Event::assertDispatched(PermanentBouncedMessageEvent::class, function ($event) use ($track) {
            return $event->email_address == 'recipient@example.com' &&
                $event->sent_email->hash == $track->hash;
        });
    }

    /**
     * @test
     */
    public function it_handles_transient_bounce()
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
            'bounce' => (object)[
                'bouncedRecipients' => (object)[
                    (object)[
                       'emailAddress' => 'recipient@example.com',
                       'diagnosticCode' => 'The Diagnostic Code',
                    ]
                ],
                'bounceType' => 'Transient',
                'bounceSubType' => 'General',
            ]
        ];
        $job = new RecordBounceJob($message);

        $job->handle();

        $track = $track->fresh();
        $meta = $track->meta;
        $this->assertEquals([
            [
                'emailAddress' => 'recipient@example.com',
                'diagnosticCode' => 'The Diagnostic Code',
            ]
        ], $meta->get('failures'));
        $this->assertFalse($meta->get('success'));
        $this->assertEquals(json_decode(json_encode($message), true), $meta->get('sns_message_bounce'));
        Event::assertDispatched(TransientBouncedMessageEvent::class, function ($event) use ($track) {
            return $event->email_address == 'recipient@example.com' &&
                $event->bounce_sub_type == 'General' &&
                $event->diagnostic_code == 'The Diagnostic Code' &&
                $event->sent_email->hash == $track->hash;
        });
    }

    /**
     * @test
     */
    public function it_handles_transient_bounce_without_diagnostic_code()
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
            'bounce' => (object)[
                'bouncedRecipients' => (object)[
                    (object)[
                       'emailAddress' => 'recipient@example.com',
                    ]
                ],
                'bounceType' => 'Transient',
                'bounceSubType' => 'General',
            ]
        ];
        $job = new RecordBounceJob($message);

        $job->handle();

        $track = $track->fresh();
        $meta = $track->meta;
        $this->assertEquals([
            [
                'emailAddress' => 'recipient@example.com',
            ]
        ], $meta->get('failures'));
        $this->assertFalse($meta->get('success'));
        $this->assertEquals(json_decode(json_encode($message), true), $meta->get('sns_message_bounce'));
        Event::assertDispatched(TransientBouncedMessageEvent::class, function ($event) use ($track) {
            return $event->email_address == 'recipient@example.com' &&
                $event->bounce_sub_type == 'General' &&
                $event->diagnostic_code == '' &&
                $event->sent_email->hash == $track->hash;
        });
    }
}
