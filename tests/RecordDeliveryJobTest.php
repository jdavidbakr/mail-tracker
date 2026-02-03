<?php

namespace jdavidbakr\MailTracker\Tests;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Event;
use jdavidbakr\MailTracker\MailTracker;
use jdavidbakr\MailTracker\RecordBounceJob;
use jdavidbakr\MailTracker\RecordDeliveryJob;
use jdavidbakr\MailTracker\RecordComplaintJob;
use jdavidbakr\MailTracker\Events\EmailDeliveredEvent;
use jdavidbakr\MailTracker\Events\ComplaintMessageEvent;

class RecordDeliveryJobTest extends SetUpTest
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
            'delivery' => (object)[
                'timestamp' => 12345,
                'recipients' => (object)[
                    'recipient@example.com'
                ],
                'smtpResponse' => 'the smtp response',
            ]
        ];
        $job = new RecordDeliveryJob($message);

        $job->handle();

        $track = $track->fresh();
        $meta = $track->meta;
        $this->assertEquals('the smtp response', $meta->get('smtpResponse'));
        $this->assertTrue($meta->get('success'));
        $this->assertEquals(12345, $meta->get('delivered_at'));
        $this->assertEquals(json_decode(json_encode($message), true), $meta->get('sns_message_delivery'));
        Event::assertDispatched(EmailDeliveredEvent::class, function ($event) use ($track) {
            return $event->email_address == 'recipient@example.com' &&
                $event->sent_email->hash == $track->hash;
        });
    }

    /**
     * @test
     */
    public function it_handles_this_situation()
    {
        $message = (object)[
            'notificationType' => 'Delivery',
            'mail' => (object) [
                'timestamp' => "2020-04-07T15:07:53.347Z",
                'source' => "mymove@churchonthemove.com",
                'sourceArn' => "arn:aws:ses:us-east-1:819775105347:identity/churchonthemove.com",
                'sourceIp' => "54.236.242.10",
                'sendAccountId' => "819775105347",
                'messageId' => "01000171552ef683-42a82c88-b847-47ca-9bb8-f46655398d01-000000",
                'destination' => (object)[
                    "mymove@churchonthemove.com",
                    "kddavid05@yahoo.com"
                ],
                'headersTruncated' => false,
                'headers' => (object)[
                    (object)[
                        'name' => 'Message-ID',
                        'value' => "<58c15e034bd35bd98f750863e65545d6@swift.generated>"
                    ],
                    (object)[
                        'name' => 'Date',
                        'value' => 'Tue, 07 Apr 2020 10:07:53 -0500'
                    ],
                    (object)[
                        'name' => "Subject",
                        'value' => "Online Giving Notification"
                    ],
                    (object)[
                        'name' => 'From',
                        'value' => "Church on the Move <mymove@churchonthemove.com>"
                    ],
                    (object)[
                        'name' => 'To',
                        'value' => "kddavid05@yahoo.com"
                    ],
                    (object)[
                        'name' => 'Bcc',
                        'value' => "mymove@churchonthemove.com"
                    ],
                    (object)[
                        'name' => 'MIME-Version',
                        'value' => '1.0',
                    ],
                    (object)[
                        'name' => 'Content-Type',
                        'value' => "text/html; charset=utf-8"
                    ],
                    (object)[
                        'name' => "Content-Transfer-Encoding",
                        'value' => "quoted-printable"
                    ],
                    (object)[
                        'name' => "X-Mailer-Hash",
                        'value' => "iFxkLyGt3z91BOvlGnce3dmH7XeCk5XD"
                    ]
                ],
                'commenHeaders' => (object)[
                    'from' => (object)[
                        "Church on the Move <mymove@churchonthemove.com>"
                    ],
                    'date' => "Tue, 07 Apr 2020 10:07:53 -0500",
                    'to' => (object)[
                        "kddavid05@yahoo.com"
                    ],
                    'bcc' => (object)[
                        "mymove@churchonthemove.com"
                    ],
                    'messageId' => "<58c15e034bd35bd98f750863e65545d6@swift.generated>",
                    'subject' => "Online Giving Notification"
                ]
            ],
            'delivery' => (object)[
                'timestamp' => "2020-04-07T15:07:54.820Z",
                'processingTimeMillis' => 1473,
                'recipients' => (object)[
                    "kddavid05@yahoo.com"
                ],
                'smtpResponse' => "250 ok dirdel",
                'remoteMtaIp' => "98.136.96.91",
                'reportingMTA' => "a48-114.smtp-out.amazonses.com"
            ]
        ];
        Event::fake();
        $job = new RecordDeliveryJob($message);

        $job->handle();

        Event::assertNotDispatched(EmailDeliveredEvent::class);
    }
}
