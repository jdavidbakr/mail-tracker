<?php

namespace jdavidbakr\MailTracker\Tests;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use jdavidbakr\MailTracker\Events\ValidActionEvent;
use jdavidbakr\MailTracker\MailTracker;

class SkipListener
{
    public function handle(ValidActionEvent $event)
    {
        $event->skip = true;
    }
}

class ContinueListener
{
    public function handle(ValidActionEvent $event)
    {
        $event->skip = false;
    }
}

class MailTrackerControllerTest extends SetUpTest
{
    public function testReadTrackingIsSkipped()
    {
        Event::listen(
            ValidActionEvent::class,
            SkipListener::class,
        );

        // Create an old email to purge
        Config::set('mail-tracker.inject-pixel', 1);
        Config::set('mail-tracker.track-links', 1);

        $email = MailTracker::sentEmailModel()->newQuery()->create([
            'hash' => Str::random(32),
        ]);

        $this->get(route('mailTracker_t', [$email->hash]));

        $email->refresh();

        $this->assertNull($email->opened_at);
    }

    public function testReadTrackingIsNotSkipped()
    {
        Event::listen(
            ValidActionEvent::class,
            ContinueListener::class,
        );

        // Create an old email to purge
        Config::set('mail-tracker.inject-pixel', 1);
        Config::set('mail-tracker.track-links', 1);

        $email = MailTracker::sentEmailModel()->newQuery()->create([
            'hash' => Str::random(32),
        ]);

        $this->get(route('mailTracker_t', [$email->hash]));

        $email->refresh();

        $this->assertNotNull($email->opened_at);
    }

    public function testLinkTrackingIsSkipped()
    {
        Event::listen(
            ValidActionEvent::class,
            SkipListener::class,
        );

        // Create an old email to purge
        Config::set('mail-tracker.inject-pixel', 1);
        Config::set('mail-tracker.track-links', 1);

        $email = MailTracker::sentEmailModel()->newQuery()->create([
            'hash' => Str::random(32),
        ]);

        $redirect = 'http://' . Str::random(15) . '.com/' . Str::random(10) . '/' . Str::random(10) . '/' . rand(0, 100) . '/' . rand(0, 100) . '?page=' . rand(0, 100) . '&x=' . Str::random(32);

        $this->get(route('mailTracker_l', [
            MailTracker::hash_url($redirect), // Replace slash with dollar sign
            $email->hash,
        ]));

        $email->refresh();

        $this->assertNull($email->opened_at);
        $this->assertNull($email->clicked_at);
    }

    public function testLinkTrackingIsNotSkipped()
    {
        Event::listen(
            ValidActionEvent::class,
            ContinueListener::class,
        );

        // Create an old email to purge
        Config::set('mail-tracker.inject-pixel', 1);
        Config::set('mail-tracker.track-links', 1);

        $email = MailTracker::sentEmailModel()->newQuery()->create([
            'hash' => Str::random(32),
        ]);

        $redirect = 'http://' . Str::random(15) . '.com/' . Str::random(10) . '/' . Str::random(10) . '/' . rand(0, 100) . '/' . rand(0, 100) . '?page=' . rand(0, 100) . '&x=' . Str::random(32);

        $this->get(route('mailTracker_l', [
            MailTracker::hash_url($redirect), // Replace slash with dollar sign
            $email->hash,
        ]));

        $email->refresh();

        $this->assertNotNull($email->opened_at);
        $this->assertNotNull($email->clicked_at);
    }
}
