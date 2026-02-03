<?php

namespace jdavidbakr\MailTracker\Tests;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Event;
use jdavidbakr\MailTracker\MailTracker;
use jdavidbakr\MailTracker\RecordBounceJob;
use jdavidbakr\MailTracker\RecordDeliveryJob;
use jdavidbakr\MailTracker\RecordTrackingJob;
use jdavidbakr\MailTracker\RecordComplaintJob;
use jdavidbakr\MailTracker\RecordLinkClickJob;
use jdavidbakr\MailTracker\Events\ViewEmailEvent;
use jdavidbakr\MailTracker\Events\LinkClickedEvent;

class RecordTrackingJobTest extends SetUpTest
{
    /**
     * @test
     */
    public function it_records_views()
    {
        Event::fake();
        $track = MailTracker::sentEmailModel()->newQuery()->create([
                'hash' => Str::random(32),
            ]);
        $job = new RecordTrackingJob($track, '127.0.0.1');

        $job->handle();

        Event::assertDispatched(ViewEmailEvent::class, function ($e) use ($track) {
            return $track->id == $e->sent_email->id &&
                $e->ip_address == '127.0.0.1';
        });
        $this->assertDatabaseHas('sent_emails', [
                'id' => $track->id,
                'opens' => 1,
            ]);
    }
}
