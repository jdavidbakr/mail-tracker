<?php

namespace jdavidbakr\MailTracker\Tests;

use Exception;
use Faker\Factory;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\MailServiceProvider;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use jdavidbakr\MailTracker\Events\EmailSentEvent;
use jdavidbakr\MailTracker\Events\LinkClickedEvent;
use jdavidbakr\MailTracker\Exceptions\BadUrlLink;
use jdavidbakr\MailTracker\MailTracker;
use jdavidbakr\MailTracker\Model\SentEmail;
use jdavidbakr\MailTracker\Model\SentEmailUrlClicked;
use jdavidbakr\MailTracker\RecordBounceJob;
use jdavidbakr\MailTracker\RecordComplaintJob;
use jdavidbakr\MailTracker\RecordDeliveryJob;
use jdavidbakr\MailTracker\RecordLinkClickJob;
use jdavidbakr\MailTracker\RecordTrackingJob;
use Mockery;
use Orchestra\Testbench\Exceptions\Handler;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Part\AbstractPart;
use Throwable;

class IgnoreExceptions extends Handler
{
    public function __construct()
    {
    }
    public function report(Throwable $e)
    {
    }
    public function render($request, Throwable $e)
    {
        throw $e;
    }
}

class TestMailable extends Mailable
{
    public function build()
    {
        return $this->markdown('email.markdown');
    }
}

class MailTrackerTest extends SetUpTest
{
    protected function disableExceptionHandling()
    {
        $this->app->instance(ExceptionHandler::class, new IgnoreExceptions);
    }

    public function testSendMessage()
    {
        // Create an old email to purge
        Config::set('mail-tracker.expire-days', 1);
        Config::set('mail-tracker.inject-pixel', 1);
        Config::set('mail-tracker.track-links', 1);

        $old_email = MailTracker::sentEmailModel()->newQuery()->create([
                'hash' => Str::random(32),
            ]);
        $old_url = MailTracker::sentEmailUrlClickedModel()->newQuery()->create([
                'sent_email_id' => $old_email->id,
                'hash' => Str::random(32),
            ]);
        // Go into the future to make sure that the old email gets removed
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::now()->addWeek());
        $str = Mockery::mock(Str::class);
        app()->instance(Str::class, $str);
        $str->shouldReceive('random')
            ->times(2)
            ->andReturn('random-hash');

        Event::fake([
            EmailSentEvent::class
        ]);

        $faker = Factory::create();
        $email = $faker->email;
        $subject = $faker->sentence;
        $name = $faker->firstName . ' ' .$faker->lastName;
        View::addLocation(__DIR__);
        try {
            Mail::send('email.test', [], function ($message) use ($email, $subject, $name) {
                $message->from('from@johndoe.com', 'From Name');
                $message->sender('sender@johndoe.com', 'Sender Name');

                $message->to($email, $name);

                $message->cc('cc@johndoe.com', 'CC Name');
                $message->bcc('bcc@johndoe.com', 'BCC Name');

                $message->replyTo('reply-to@johndoe.com', 'Reply-To Name');

                $message->subject($subject);

                $message->priority(3);
            });
        } catch (TransportException $e) {
        }

        Event::assertDispatched(EmailSentEvent::class);

        $this->assertDatabaseHas('sent_emails', [
                'hash' => 'random-hash',
                'recipient_name' => $name,
                'recipient_email' => $email,
                'sender_name' => 'From Name',
                'sender_email' => 'from@johndoe.com',
                'subject' => $subject,
                'opened_at' => null,
                'clicked_at' => null,
            ]);
        $sent_email = MailTracker::sentEmailModel()->newQuery()->where([
            'hash' => 'random-hash',
        ])->first();
        $this->assertEquals($name.' <'.$email.'>', $sent_email->recipient);
        $this->assertEquals('From Name <from@johndoe.com>', $sent_email->sender);
        $this->assertNull($old_email->fresh());
        $this->assertNull($old_url->fresh());
    }

    public function testSendMessageWithMailRaw()
    {
        $faker = Factory::create();
        $email = $faker->email;
        $name = $faker->firstName . ' ' .$faker->lastName;
        $content = 'Text to e-mail';
        View::addLocation(__DIR__);
        $str = Mockery::mock(Str::class);
        app()->instance(Str::class, $str);
        $str->shouldReceive('random')
            ->once()
            ->andReturn('random-hash');

        try {
            Mail::raw($content, function ($message) use ($email, $name) {
                $message->from('from@johndoe.com', 'From Name');

                $message->to($email, $name);
            });
        } catch (Exception $e) {
        }

        $this->assertDatabaseHas('sent_emails', [
            'hash' => 'random-hash',
            'sender_name' => 'From Name',
            'sender_email' => 'from@johndoe.com',
            'recipient_name' => $name,
            'recipient_email' => $email,
            'content' => $content
        ]);
    }

    public function testSendMessageWithMultiPart()
    {
        $faker = Factory::create();
        $email = $faker->email;
        $name = $faker->firstName . ' ' .$faker->lastName;
        View::addLocation(__DIR__);
        $str = Mockery::mock(Str::class);
        app()->instance(Str::class, $str);
        $str->shouldReceive('random')
            ->once()
            ->andReturn('random-hash');
        $mailable = new TestMailable();
        $mailable->subject('this is the message subject.');

        try {
            Mail::to($email)->send($mailable);
        } catch (Exception $e) {
            // dd($e);
        }

        $this->assertDatabaseHas('sent_emails', [
            'hash' => 'random-hash',
            'recipient_email' => $email,
        ]);
    }

    public function testSendMessageWithMixedPart()
    {
        $faker = Factory::create();
        $email = $faker->email;
        $name = $faker->firstName . ' ' .$faker->lastName;
        View::addLocation(__DIR__);
        $str = Mockery::mock(Str::class);
        app()->instance(Str::class, $str);
        $str->shouldReceive('random')
            ->once()
            ->andReturn('random-hash');
        $mailable = new TestMailable();
        $mailable->subject('this is  the message subject.');
        $mailable->attach(__DIR__.'/email/test.blade.php');

        try {
            Mail::to($email)->send($mailable);
        } catch (Exception $e) {
            // dd($e);
        }

        $this->assertDatabaseHas('sent_emails', [
            'hash' => 'random-hash',
            'recipient_email' => $email,
        ]);
    }

    public function testSendMessageWithRelatedPart()
    {
        $faker = Factory::create();
        $email = $faker->email;
        $name = $faker->firstName . ' ' .$faker->lastName;
        View::addLocation(__DIR__);
        $str = Mockery::mock(Str::class);
        app()->instance(Str::class, $str);
        $str->shouldReceive('random')
            ->once()
            ->andReturn('random-hash');

        try {
            Mail::send('email.embed-test', ['imagePath' => __DIR__ . '/email/example.png'], function ($message) use ($email, $name) {
                $message->from('from@johndoe.com', 'From Name');
                $message->sender('sender@johndoe.com', 'Sender Name');

                $message->to($email, $name);

                $message->cc('cc@johndoe.com', 'CC Name');
                $message->bcc('bcc@johndoe.com', 'BCC Name');

                $message->replyTo('reply-to@johndoe.com', 'Reply-To Name');

                $message->subject('This is the test subject');

                $message->priority(3);
            });
        } catch (TransportException $e) {

        }

        $this->assertDatabaseHas('sent_emails', [
            'hash' => 'random-hash',
            'recipient_email' => $email,
        ]);
    }

    public function testSendMessageWithAttachment()
    {
        $faker = Factory::create();
        $email = $faker->email;
        View::addLocation(__DIR__);
        $str = Mockery::mock(Str::class);
        app()->instance(Str::class, $str);
        $str->shouldReceive('random')
            ->once()
            ->andReturn('random-hash');

        $mailable = new TestMailable();
        $mailable->subject('this is  the message subject.');
        $mailable->attach(__DIR__.'/email/example.pdf', [
            'as' => 'invoice.pdf',
            'mime' => 'application/pdf',
        ]);

        try {
            Mail::to($email)->send($mailable);
        } catch (Exception $e) {
            // dd($e);
        }

        $this->assertDatabaseHas('sent_emails', [
            'hash' => 'random-hash',
            'recipient_email' => $email,
        ]);
    }

    /**
     * @test
     */
    public function it_doesnt_track_if_told_not_to()
    {
        $faker = Factory::create();
        $email = $faker->email;
        $anotherEmail = $faker->email;
        $subject = $faker->sentence;
        $name = $faker->firstName . ' ' .$faker->lastName;
        View::addLocation(__DIR__);
        try {
            Mail::send('email.test', [], function ($message) use ($email, $anotherEmail, $subject, $name) {
                $message->from('from@johndoe.com', 'From Name');
                $message->sender('sender@johndoe.com', 'Sender Name');

                $message->to($email, $name);
                $message->to($anotherEmail, $name);

                $message->cc('cc@johndoe.com', 'CC Name');
                $message->bcc('bcc@johndoe.com', 'BCC Name');

                $message->replyTo('reply-to@johndoe.com', 'Reply-To Name');

                $message->subject($subject);

                $message->priority(3);

                $message->getHeaders()->addTextHeader('X-No-Track', Str::random(10));
            });
        } catch (TransportException $e) {
        }

        $this->assertDatabaseMissing('sent_emails', [
            'subject' => $subject,
            'sender_name' => 'From Name',
            'sender_email' => 'from@johndoe.com',
            'recipient_name' => $name,
            'recipient_email' => $email,
        ]);

        $this->assertDatabaseMissing('sent_emails', [
            'subject' => $subject,
            'sender_name' => 'From Name',
            'sender_email' => 'from@johndoe.com',
            'recipient_name' => $name,
            'recipient_email' => $anotherEmail,
        ]);
    }

    /**
     * @test
     */
    public function testPing()
    {
        Carbon::setTestNow(now());
        Config::set('mail-tracker.tracker-queue', 'alt-queue');
        Bus::fake();
        $track = MailTracker::sentEmailModel()->newQuery()->create([
                'hash' => Str::random(32),
            ]);
        $pings = $track->opens;
        $pings++;
        $url = route('mailTracker_t', [$track->hash]);

        $response = $this->get($url);

        $response->assertSuccessful();
        Bus::assertDispatched(RecordTrackingJob::class, function ($e) use ($track) {
            return $e->sentEmail->id == $track->id &&
                $e->ipAddress == '127.0.0.1' &&
                $e->queue == 'alt-queue';
        });
        $this->assertDatabaseHas('sent_emails', [
            'id' => $track->id,
            'opened_at' => now()->format("Y-m-d H:i:s"),
        ]);
    }

    /**
     * @test
     */
    public function it_leaves_existing_opened_at_value()
    {
        Carbon::setTestNow(now());
        Config::set('mail-tracker.tracker-queue', 'alt-queue');
        Bus::fake();
        $track = MailTracker::sentEmailModel()->newQuery()->create([
                'hash' => Str::random(32),
                'opened_at' => now()->subDays(10),
            ]);
        $pings = $track->opens;
        $pings++;
        $url = route('mailTracker_t', [$track->hash]);

        $response = $this->get($url);

        $response->assertSuccessful();
        Bus::assertDispatched(RecordTrackingJob::class, function ($e) use ($track) {
            return $e->sentEmail->id == $track->id &&
                $e->ipAddress == '127.0.0.1' &&
                $e->queue == 'alt-queue';
        });
        $this->assertDatabaseHas('sent_emails', [
            'id' => $track->id,
            'opened_at' => $track->opened_at,
        ]);
    }

    public function testLegacyLink()
    {
        Carbon::setTestNow(now());
        Config::set('mail-tracker.tracker-queue', 'alt-queue');
        Bus::fake();
        $track = MailTracker::sentEmailModel()->newQuery()->create([
                'hash' => Str::random(32),
            ]);
        $clicks = $track->clicks;
        $clicks++;
        $redirect = 'http://'.Str::random(15).'.com/'.Str::random(10).'/'.Str::random(10).'/'.rand(0, 100).'/'.rand(0, 100).'?page='.rand(0, 100).'&x='.Str::random(32);
        $url = route('mailTracker_l', [
                MailTracker::hash_url($redirect), // Replace slash with dollar sign
                $track->hash
            ]);
        $response = $this->get($url);

        $response->assertRedirect($redirect);
        Bus::assertDispatched(RecordLinkClickJob::class, function ($job) use ($track, $redirect) {
            return $job->sentEmail->id == $track->id &&
                $job->url == $redirect &&
                $job->ipAddress == '127.0.0.1' &&
                $job->queue == 'alt-queue';
        });
        $this->assertDatabaseHas('sent_emails', [
            'id' => $track->id,
            'clicked_at' => now()->format("Y-m-d H:i:s"),
        ]);
    }

    public function testLink()
    {
        Carbon::setTestNow(now());
        Config::set('mail-tracker.inject-pixel', true);
        Config::set('mail-tracker.tracker-queue', 'alt-queue');
        Bus::fake();
        $track = MailTracker::sentEmailModel()->newQuery()->create([
                'hash' => Str::random(32),
            ]);
        $redirect = 'http://'.Str::random(15).'.com/'.Str::random(10).'/'.Str::random(10).'/'.rand(0, 100).'/'.rand(0, 100).'?page='.rand(0, 100).'&x='.Str::random(32);
        $url = route('mailTracker_n', [
                'l' => $redirect,
                'h' => $track->hash
            ]);

        $response = $this->get($url);

        $response->assertRedirect($redirect);
        Bus::assertDispatched(RecordLinkClickJob::class, function ($job) use ($track, $redirect) {
            return $job->sentEmail->id == $track->id &&
                $job->url == $redirect &&
                $job->ipAddress == '127.0.0.1' &&
                $job->queue == 'alt-queue';
        });
        $this->assertDatabaseHas('sent_emails', [
            'id' => $track->id,
            'clicked_at' => now()->format("Y-m-d H:i:s"),
            'opened_at' => now()->format("Y-m-d H:i:s"),
        ]);
    }

    /**
     * @test
     */
    public function it_redirects_even_if_no_sent_email_exists()
    {
        $track = MailTracker::sentEmailModel()->newQuery()->create([
                'hash' => Str::random(32),
            ]);

        $clicks = $track->clicks;
        $clicks++;

        $redirect = 'http://'.Str::random(15).'.com/'.Str::random(10).'/'.Str::random(10).'/'.rand(0, 100).'/'.rand(0, 100).'?page='.rand(0, 100).'&x='.Str::random(32);

        // Do it with an invalid hash
        $url = route('mailTracker_n', [
                'l' => $redirect,
                'h' => 'bad-hash'
            ]);
        $response = $this->get($url);

        $response->assertRedirect($redirect);
    }

    /**
     * @test
     */
    public function it_redirects_to_config_page_if_no_url_in_request()
    {
        Config::set('mail-tracker.redirect-missing-links-to', '/home');

        $url = route('mailTracker_n');
        $response = $this->get($url);

        $response->assertRedirect('/home');
    }

    /**
     * @test
     */
    public function it_redirects_to_home_page_if_no_url_in_request()
    {
        $url = route('mailTracker_n');
        $response = $this->get($url);

        $response->assertRedirect('/');
    }

    /**
     * @test
     */
    public function random_string_in_link_does_not_crash(Type $var = null)
    {
        $this->disableExceptionHandling();
        $this->expectException(BadUrlLink::class);
        $url = route('mailTracker_l', [
            Str::random(32),
            'the-mail-hash',
        ]);

        $this->get($url);
    }

    /**
     * @test
     */
    public function it_retrieves_the_mesage_id_from_laravel_mailer()
    {
        $sent = MailTracker::sentEmailModel()->newQuery()->create([
            'hash'=>'the-hash',
            'message_id'=>'to-be-replaced',
        ]);
        $headers = new Headers;
        $headers->addHeader('X-Mailer-Hash', $sent->hash);
        $sendingEvent = Mockery::mock(MessageSending::class);
        $sendingEvent->message = Mockery::mock(Email::class, [
                'getTo' => [
                    Mockery::mock([
                        'getAddress'=>'destination@example.com',
                        'getName'=>'Destination Person'
                    ])
                ],
                'getFrom' => [
                    Mockery::mock([
                        'getAddress'=>'from@example.com',
                        'getName'=>'From Name'
                    ])
                ],
                'getHeaders' => $headers,
                'getSubject' => 'The message subject',
                'getBody' => Mockery::mock(AbstractPart::class, [
                    'getBody'=>'The body',
                    'getMediaType'=>'text',
                    'getMediaSubtype'=>'html',
                ]),
                'setBody' => Mockery::Mock(Email::class),
                'getChildren' => [],
                'getId' => 'message-id',
                'getHtmlCharset' => 'utf-8',
            ]);
        $sentEvent = Mockery::mock(MessageSent::class);
        $sentEvent->sent = Mockery::mock(SentMessage::class, [
            'getOriginalMessage'=>Mockery::mock([
                'getHeaders'=>$headers
            ]),
            'getMessageId'=>'native-id',
        ]);
        $tracker = new MailTracker();

        $tracker->messageSending($sendingEvent);
        $tracker->messageSent($sentEvent);

        $this->assertDatabaseHas('sent_emails', [
            'id'=>$sent->id,
            'message_id'=>'native-id'
        ]);
    }

    /**
     * @test
     */
    public function it_retrieves_the_mesage_id_from_ses_mail_default()
    {
        Config::set('mail.default', 'ses');
        Config::set('mail.driver', null);
        $sent = MailTracker::sentEmailModel()->newQuery()->create([
            'hash'=>'the-hash',
            'message_id'=>'to-be-replaced',
        ]);
        $headers = new Headers;
        $headers->addHeader('X-Mailer-Hash', $sent->hash);
        $headers->addHeader('X-SES-Message-ID', 'aws-mailer-hash');
        $sendingEvent = Mockery::mock(MessageSending::class);
        $sendingEvent->message = Mockery::mock(Email::class, [
                'getTo' => [
                    Mockery::mock([
                        'getAddress'=>'destination@example.com',
                        'getName'=>'Destination Person'
                    ])
                ],
                'getFrom' => [
                    Mockery::mock([
                        'getAddress'=>'from@example.com',
                        'getName'=>'From Name'
                    ])
                ],
                'getHeaders' => $headers,
                'getSubject' => 'The message subject',
                'getBody' => Mockery::mock(AbstractPart::class, 'content', [
                    'getBody'=>'The body',
                    'getMediaType'=>'text',
                    'getMediaSubtype'=>'html',
                ]),
                'setBody' => Mockery::mock(Email::class),
                'getChildren' => [],
                'getId' => 'message-id',
                'getHtmlCharset' => 'utf-8',
            ]);
        $sentEvent = Mockery::mock(MessageSent::class);
        $sentEvent->sent = Mockery::mock(SentMessage::class, [
            'getOriginalMessage'=>Mockery::mock([
                'getHeaders'=>$headers
            ]),
        ]);
        $tracker = new MailTracker();

        $tracker->messageSending($sendingEvent);
        $tracker->messageSent($sentEvent);

        $this->assertDatabaseHas('sent_emails', [
            'id'=>$sent->id,
            'message_id'=>'aws-mailer-hash'
        ]);
    }

    /**
     * @test
     */
    public function it_retrieves_the_mesage_id_from_ses_mail_driver()
    {
        $str = Mockery::mock(Str::class);
        app()->instance(Str::class, $str);
        $str->shouldReceive('random')
            ->with(32)
            ->andReturn('random-hash');
        Config::set('mail.driver', 'ses');
        Config::set('mail.default', null);
        $sent = MailTracker::sentEmailModel()->newQuery()->create([
            'hash'=>'the-hash',
            'message_id'=>'to-be-replaced',
        ]);
        $headers = new Headers;
        $headers->addHeader('X-Mailer-Hash', $sent->hash);
        $headers->addHeader('X-SES-Message-ID', 'aws-mailer-hash');
        $sendingEvent = Mockery::mock(MessageSending::class);
        $sendingEvent->message = Mockery::mock(Email::class, [
                'getTo' => [
                    Mockery::mock([
                        'getAddress'=>'destination@example.com',
                        'getName'=>'Destination Person'
                    ])
                ],
                'getFrom' => [
                    Mockery::mock([
                        'getAddress'=>'from@example.com',
                        'getName'=>'From Name'
                    ])
                ],
                'getHeaders' => $headers,
                'getSubject' => 'The message subject',
                'getBody' => Mockery::mock(AbstractPart::class, 'content', [
                    'getBody'=>'The body',
                    'getMediaType'=>'text',
                    'getMediaSubtype'=>'html',
                ]),
                'setBody' => Mockery::mock(Email::class),
                'getChildren' => [],
                'getId' => 'message-id',
                'getHtmlCharset' => 'utf-8',
            ]);
        $sentEvent = Mockery::mock(MessageSent::class);
        $sentEvent->sent = Mockery::mock(SentMessage::class, [
            'getOriginalMessage'=>Mockery::mock([
                'getHeaders'=>$headers
            ]),
        ]);
        $tracker = new MailTracker();

        $tracker->messageSending($sendingEvent);
        $tracker->messageSent($sentEvent);

        $this->assertDatabaseHas('sent_emails', [
            'id'=>$sent->id,
            'message_id'=>'aws-mailer-hash'
        ]);
    }

    /**
     * SNS Tests
     */

    /**
     * @test
     */
    public function it_confirms_a_subscription()
    {
        $url = action('\jdavidbakr\MailTracker\SNSController@callback');
        $response = $this->post($url, [
                'message' => json_encode([
                        // Required
                        'Message' => 'test subscription message',
                        'MessageId' => Str::random(10),
                        'Timestamp' => \Carbon\Carbon::now()->timestamp,
                        'TopicArn' => Str::random(10),
                        'Type' => 'SubscriptionConfirmation',
                        'Signature' => Str::random(32),
                        'SigningCertURL' => Str::random(32),
                        'SignatureVersion' => 1,
                        // Request-specific
                        'SubscribeURL' => 'http://google.com',
                        'Token' => Str::random(10),
                    ])
            ]);
        $response->assertSee('subscription confirmed');
    }

    /**
     * @test
     */
    public function it_processes_with_registered_topic()
    {
        $topic = Str::random(32);
        Config::set('mail-tracker.sns-topic', $topic);
        $url = action('\jdavidbakr\MailTracker\SNSController@callback');
        $response = $this->post($url, [
                'message' => json_encode([
                        // Required
                        'Message' => 'test subscription message',
                        'MessageId' => Str::random(10),
                        'Timestamp' => \Carbon\Carbon::now()->timestamp,
                        'TopicArn' => $topic,
                        'Type' => 'SubscriptionConfirmation',
                        'Signature' => Str::random(32),
                        'SigningCertURL' => Str::random(32),
                        'SignatureVersion' => 1,
                        // Request-specific
                        'SubscribeURL' => 'http://google.com',
                        'Token' => Str::random(10),
                    ])
            ]);
        $response->assertSee('subscription confirmed');
    }

    /**
     * @test
     */
    public function it_ignores_invalid_topic()
    {
        $topic = Str::random(32);
        Config::set('mail-tracker.sns-topic', $topic);
        $url = action('\jdavidbakr\MailTracker\SNSController@callback');
        $response = $this->post($url, [
                'message' => json_encode([
                        // Required
                        'Message' => 'test subscription message',
                        'MessageId' => Str::random(10),
                        'Timestamp' => \Carbon\Carbon::now()->timestamp,
                        'TopicArn' => Str::random(32),
                        'Type' => 'SubscriptionConfirmation',
                        'Signature' => Str::random(32),
                        'SigningCertURL' => Str::random(32),
                        'SignatureVersion' => 1,
                        // Request-specific
                        'SubscribeURL' => 'http://google.com',
                        'Token' => Str::random(10),
                    ])
            ]);
        $response->assertSee('invalid topic ARN');
    }

    /**
     * @test
     */
    public function it_processes_a_delivery()
    {
        Config::set('mail-tracker.tracker-queue', 'alt-queue');
        Bus::fake();
        $message = [
            'notificationType' => 'Delivery',
        ];

        $response = $this->post(action('\jdavidbakr\MailTracker\SNSController@callback'), [
                'message' => json_encode([
                    'Message' => json_encode($message),
                    'MessageId' => Str::uuid(),
                    'Timestamp' => Carbon::now()->timestamp,
                    'TopicArn' => Str::uuid(),
                    'Type' => 'Notification',
                    'Signature' => Str::uuid(),
                    'SigningCertURL' => Str::uuid(),
                    'SignatureVersion' => Str::uuid(),
                ])
            ]);

        $response->assertSee('notification processed');
        Bus::assertDispatched(RecordDeliveryJob::class, function ($job) use ($message) {
            return $job->message == (object)$message &&
                $job->queue == 'alt-queue';
        });
    }

    /**
     * @test
     */
    public function it_processes_a_bounce()
    {
        Config::set('mail-tracker.tracker-queue', 'alt-queue');
        Bus::fake();
        $message = [
            'notificationType' => 'Bounce',
        ];

        $response = $this->post(action('\jdavidbakr\MailTracker\SNSController@callback'), [
                'message' => json_encode([
                    'Message' => json_encode($message),
                    'MessageId' => Str::uuid(),
                    'Timestamp' => Carbon::now()->timestamp,
                    'TopicArn' => Str::uuid(),
                    'Type' => 'Notification',
                    'Signature' => Str::uuid(),
                    'SigningCertURL' => Str::uuid(),
                    'SignatureVersion' => Str::uuid(),
                ])
            ]);

        $response->assertSee('notification processed');
        Bus::assertDispatched(RecordBounceJob::class, function ($job) use ($message) {
            return $job->message == (object)$message &&
                $job->queue == 'alt-queue';
        });
    }

    /**
     * @test
     */
    public function it_processes_a_complaint()
    {
        Config::set('mail-tracker.tracker-queue', 'alt-queue');
        Bus::fake();
        $message = [
            'notificationType' => 'Complaint',
        ];

        $response = $this->post(action('\jdavidbakr\MailTracker\SNSController@callback'), [
                'message' => json_encode([
                    'Message' => json_encode($message),
                    'MessageId' => Str::uuid(),
                    'Timestamp' => Carbon::now()->timestamp,
                    'TopicArn' => Str::uuid(),
                    'Type' => 'Notification',
                    'Signature' => Str::uuid(),
                    'SigningCertURL' => Str::uuid(),
                    'SignatureVersion' => Str::uuid(),
                ])
            ]);

        $response->assertSee('notification processed');
        Bus::assertDispatched(RecordComplaintJob::class, function ($job) use ($message) {
            return $job->message == (object)$message &&
                $job->queue == 'alt-queue';
        });
    }

    /**
     * @test
     */
    public function it_handles_ampersands_in_links()
    {
        Event::fake(LinkClickedEvent::class);
        Config::set('mail-tracker.track-links', true);
        Config::set('mail-tracker.inject-pixel', true);
        Config::set('mail.driver', 'array');
        (new MailServiceProvider(app()))->register();

        $faker = Factory::create();
        $email = $faker->email;
        $subject = $faker->sentence;
        $name = $faker->firstName . ' ' .$faker->lastName;
        View::addLocation(__DIR__);

        Mail::send('email.testAmpersand', [], function ($message) use ($email, $subject, $name) {
            $message->from('from@johndoe.com', 'From Name');
            $message->sender('sender@johndoe.com', 'Sender Name');

            $message->to($email, $name);

            $message->cc('cc@johndoe.com', 'CC Name');
            $message->bcc('bcc@johndoe.com', 'BCC Name');

            $message->replyTo('reply-to@johndoe.com', 'Reply-To Name');

            $message->subject($subject);

            $message->priority(3);
        });
        $driver = app('mailer')->getSymfonyTransport();
        $this->assertEquals(1, count($driver->messages()));

        $mes = $driver->messages()[0];
        $body = $mes->getOriginalMessage()->getBody()->getBody();
        $hash = $mes->getOriginalMessage()->getHeaders()->get('X-Mailer-Hash')->getValue();

        $matches = null;
        preg_match_all('/(<a[^>]*href=[\'"])([^\'"]*)/', $body, $matches);
        $links = $matches[2];
        $aLink = $links[1];

        $expected_url = "http://www.google.com?q=foo&x=bar";
        $this->assertNotNull($aLink);
        $this->assertNotEquals($expected_url, $aLink);

        $response = $this->call('GET', $aLink);
        $response->assertRedirect($expected_url);

        Event::assertDispatched(LinkClickedEvent::class);

        $this->assertDatabaseHas('sent_emails_url_clicked', [
            'url' => $expected_url,
            'clicks' => 1,
        ]);

        $track = MailTracker::sentEmailModel()->newQuery()->whereHash($hash)->first();
        $this->assertNotNull($track);
        $this->assertEquals(1, $track->clicks);
    }

    /**
     * @test
     */
    public function it_handles_apostrophes_in_links()
    {
        Event::fake(LinkClickedEvent::class);
        Config::set('mail-tracker.track-links', true);
        Config::set('mail-tracker.inject-pixel', true);
        Config::set('mail.driver', 'array');
        (new MailServiceProvider(app()))->register();

        $faker = Factory::create();
        $email = $faker->email;
        $subject = $faker->sentence;
        $name = $faker->firstName . ' ' . $faker->lastName;
        View::addLocation(__DIR__);

        Mail::send('email.testApostrophe', [], function ($message) use ($email, $subject, $name) {
            $message->from('from@johndoe.com', 'From Name');
            $message->sender('sender@johndoe.com', 'Sender Name');
            $message->to($email, $name);
            $message->cc('cc@johndoe.com', 'CC Name');
            $message->bcc('bcc@johndoe.com', 'BCC Name');
            $message->replyTo('reply-to@johndoe.com', 'Reply-To Name');
            $message->subject($subject);
            $message->priority(3);
        });
        $driver = app('mailer')->getSymfonyTransport();
        $this->assertEquals(1, count($driver->messages()));

        $mes = $driver->messages()[0];
        $body = $mes->getOriginalMessage()->getBody()->getBody();
        $hash = $mes->getOriginalMessage()->getHeaders()->get('X-Mailer-Hash')->getValue();

        $matches = null;
        preg_match_all('/(<a[^>]*href=[\"])([^\"]*)/', $body, $matches);
        $links = $matches[2];
        $aLink = $links[0];

        $expected_url = "http://www.google.com?q=foo'bar";
        $this->assertNotNull($aLink);
        $this->assertNotEquals($expected_url, $aLink);

        $response = $this->call('GET', $aLink);
        $response->assertRedirect($expected_url);

        Event::assertDispatched(LinkClickedEvent::class);

        $this->assertDatabaseHas('sent_emails_url_clicked', [
            'url' => $expected_url,
            'clicks' => 1,
        ]);

        $track = MailTracker::sentEmailModel()->newQuery()->whereHash($hash)->first();
        $this->assertNotNull($track);
        $this->assertEquals(1, $track->clicks);
    }


    /**
     * @test
     */
    public function it_retrieves_header_data()
    {
        $faker = Factory::create();
        $email = $faker->email;
        $subject = $faker->sentence;
        $name = $faker->firstName . ' ' .$faker->lastName;
        $header_test = Str::random(10);
        \View::addLocation(__DIR__);
        try {
            \Mail::send('email.test', [], function ($message) use ($email, $subject, $name, $header_test) {
                $message->from('from@johndoe.com', 'From Name');
                $message->sender('sender@johndoe.com', 'Sender Name');

                $message->to($email, $name);

                $message->cc('cc@johndoe.com', 'CC Name');
                $message->bcc('bcc@johndoe.com', 'BCC Name');

                $message->replyTo('reply-to@johndoe.com', 'Reply-To Name');

                $message->subject($subject);

                $message->priority(3);

                $message->getHeaders()->addTextHeader('X-Header-Test', $header_test);
            });
        } catch (TransportException $e) {
        }

        $track = MailTracker::sentEmailModel()->newQuery()->orderBy('id', 'desc')->first();
        $this->assertEquals($header_test, $track->getHeader('X-Header-Test'));
    }

    /**
     * @test
     */
    public function it_retrieves_long_header_data()
    {
        $faker = Factory::create();
        $email = $faker->email;
        $subject = $faker->sentence;
        $name = $faker->firstName . ' ' .$faker->lastName;
        $header_test = Str::random(100) .', ' . Str::random(100) .', '. Str::random(100);
        View::addLocation(__DIR__);
        try {
            Mail::send('email.test', [], function ($message) use ($email, $subject, $name, $header_test) {
                $message->from('from@johndoe.com', 'From Name');
                $message->sender('sender@johndoe.com', 'Sender Name');

                $message->to($email, $name);

                $message->cc('cc@johndoe.com', 'CC Name');
                $message->bcc('bcc@johndoe.com', 'BCC Name');

                $message->replyTo('reply-to@johndoe.com', 'Reply-To Name');

                $message->subject($subject);

                $message->priority(3);

                $message->getHeaders()->addTextHeader('X-Header-Test', $header_test);
            });
        } catch (TransportException $e) {
        }

        $track = MailTracker::sentEmailModel()->newQuery()->orderBy('id', 'desc')->first();
        $this->assertEquals($header_test, $track->getHeader('X-Header-Test'));
    }

    /**
     * @test
     */
    public function it_retrieves_multiple_cc_recipients_from_header_data()
    {
        $faker = Factory::create();
        $email = $faker->email;
        $subject = $faker->sentence;
        $name = $faker->firstName . ' ' .$faker->lastName;
        View::addLocation(__DIR__);
        try {
            Mail::send('email.test', [], function ($message) use ($email, $subject, $name) {
                $message->from('from@johndoe.com', 'From Name');
                $message->sender('sender@johndoe.com', 'Sender Name');

                $message->to($email, $name);

                $message->cc('cc.averylongemail1@johndoe.com', 'CC This Person With a Long Name 1');
                $message->cc('cc.averylongemail2@johndoe.com', 'CC This Person With a Long Name 2');
                $message->cc('cc.averylongemail3@johndoe.com', 'CC This Person With a Long Name 3');
                $message->cc('cc.averylongemail4@johndoe.com', 'CC This Person With a Long Name 4');
                $message->cc('cc.averylongemail5@johndoe.com', 'CC This Person With a Long Name 5');
                $message->cc('cc.averylongemail6@johndoe.com', 'CC This Person With a Long Name 6');
                $message->cc('cc.averylongemail7@johndoe.com', 'CC This Person With a Long Name 7');
                $message->cc('cc.averylongemail8@johndoe.com', 'CC This Person With a Long Name 8');
                $message->cc('cc.averylongemail9@johndoe.com', 'CC This Person With a Long Name 9');
                $message->bcc('bcc@johndoe.com', 'BCC Name');

                $message->replyTo('reply-to@johndoe.com', 'Reply-To Name');

                $message->subject($subject);

                $message->priority(3);
            });
        } catch (TransportException $e) {
        }

        $track = MailTracker::sentEmailModel()->newQuery()->orderBy('id', 'desc')->first();

        $this->assertEquals(
            'CC This Person With a Long Name 1 <cc.averylongemail1@johndoe.com>, ' .
            'CC This Person With a Long Name 2 <cc.averylongemail2@johndoe.com>, ' .
            'CC This Person With a Long Name 3 <cc.averylongemail3@johndoe.com>, ' .
            'CC This Person With a Long Name 4 <cc.averylongemail4@johndoe.com>, ' .
            'CC This Person With a Long Name 5 <cc.averylongemail5@johndoe.com>, ' .
            'CC This Person With a Long Name 6 <cc.averylongemail6@johndoe.com>, ' .
            'CC This Person With a Long Name 7 <cc.averylongemail7@johndoe.com>, ' .
            'CC This Person With a Long Name 8 <cc.averylongemail8@johndoe.com>, ' .
            'CC This Person With a Long Name 9 <cc.averylongemail9@johndoe.com>',
            $track->getHeader('Cc')
        );
    }

    /**
     * @test
     */
    public function it_handles_secondary_connection()
    {
        // Create an old email to purge
        Config::set('mail-tracker.expire-days', 1);

        Config::set('mail-tracker.connection', 'secondary');
        $this->app['migrator']->setConnection('secondary');
        $this->artisan('migrate', ['--database' => 'secondary']);

        $old_email = MailTracker::sentEmailModel()->newQuery()->create([
            'hash' => Str::random(32),
        ]);
        $old_url = MailTracker::sentEmailUrlClickedModel()->newQuery()->create([
            'sent_email_id' => $old_email->id,
            'hash' => Str::random(32),
        ]);
        // Go into the future to make sure that the old email gets removed
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::now()->addWeek());

        Event::fake([
            EmailSentEvent::class
        ]);

        $faker = Factory::create();
        $email = $faker->email;
        $subject = $faker->sentence;
        $name = $faker->firstName . ' ' .$faker->lastName;
        \View::addLocation(__DIR__);
        try {
            \Mail::send('email.test', [], function ($message) use ($email, $subject, $name) {
                $message->from('from@johndoe.com', 'From Name');
                $message->sender('sender@johndoe.com', 'Sender Name');

                $message->to($email, $name);

                $message->cc('cc@johndoe.com', 'CC Name');
                $message->bcc('bcc@johndoe.com', 'BCC Name');

                $message->replyTo('reply-to@johndoe.com', 'Reply-To Name');

                $message->subject($subject);

                $message->priority(3);
            });
        } catch (TransportException $e) {
        }

        Event::assertDispatched(EmailSentEvent::class);

        $this->assertDatabaseHas('sent_emails', [
            'recipient_name' => $name,
            'recipient_email' => $email,
            'sender_name' => 'From Name',
            'sender_email' => 'from@johndoe.com',
            'subject' => $subject,
        ], 'secondary');
        $this->assertNull($old_email->fresh());
        $this->assertNull($old_url->fresh());
    }

    /**
     * @test
     */
    public function it_can_retrieve_url_clicks_from_eloquent()
    {
        Event::fake();
        $track = MailTracker::sentEmailModel()->newQuery()->create([
            'hash' => Str::random(32),
        ]);
        $message_id = Str::random(32);
        $track->message_id = $message_id;
        $track->save();

        $urlClick = MailTracker::sentEmailUrlClickedModel()->newQuery()->create([
            'sent_email_id' => $track->id,
            'url' => 'https://example.com',
            'hash' => Str::random(32)
        ]);
        $urlClick->save();
        $this->assertTrue($track->urlClicks->count() === 1);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $track->urlClicks);
        $this->assertInstanceOf(MailTracker::$sentEmailUrlClickedModel, $track->urlClicks->first());
    }

    /**
     * @test
     */
    public function it_handles_headers_with_colons()
    {
        $headerData = '{"some_id":2,"some_othger_id":"0dd75231-31bb-4e67-8ab7-a83315f75a44","some_field":"A Field Value"}';
        $track = MailTracker::sentEmailModel()->newQuery()->create([
            'hash' => Str::random(32),
            'headers' => 'X-MyHeader: '.$headerData,
        ]);

        $retrieval = $track->getHeader('X-MyHeader');

        $this->assertEquals($headerData, $retrieval);
    }

    public function testLogContentInFilesystem()
    {
        $faker = Factory::create();
        $email = $faker->email;
        $name = $faker->firstName . ' ' .$faker->lastName;
        $content = 'Text to e-mail';
        View::addLocation(__DIR__);
        $str = Mockery::mock(Str::class);
        app()->instance(Str::class, $str);
        $str->shouldReceive('random')
            ->once()
            ->andReturn('random-hash');


        config()->set('mail-tracker.log-content-strategy', 'filesystem');
        config()->set('mail-tracker.tracker-filesystem', 'filesystem');
        config()->set('mail-tracker.tracker-filesystem-folder', 'mail-tracker');
        config()->set('filesystems.disks.testing.driver', 'local');
        config()->set('filesystems.disks.testing.root', realpath(__DIR__.'/../storage'));
        config()->set('filesystems.default', 'testing');

        Storage::fake(config('mail-tracker.tracker-filesystem'));

        try {
            Mail::raw($content, function ($message) use ($email, $name) {
                $message->from('from@johndoe.com', 'From Name');

                $message->to($email, $name);
            });
        } catch (Exception $e) {
        }

        $this->assertDatabaseHas('sent_emails', [
            'hash' => 'random-hash',
            'sender_name' => 'From Name',
            'sender_email' => 'from@johndoe.com',
            'recipient_name' => $name,
            'recipient_email' => $email,
            'content' => null
        ]);

        $tracker = MailTracker::sentEmailModel()->newQuery()->where('hash', '=','random-hash')->first();
        $this->assertNotNull($tracker);
        $this->assertEquals($content, $tracker->content);
        $folder = config('mail-tracker.tracker-filesystem-folder', 'mail-tracker');
        $filePath = $tracker->meta->get('content_file_path');
        $expectedPath = "{$folder}/random-hash.html";
        $this->assertEquals($expectedPath, $filePath);
        Storage::disk(config('mail-tracker.tracker-filesystem'))->assertExists($filePath);
    }

    /** @test */
    public function sent_email_model_can_be_created()
    {
        $sentEmail = MailTracker::sentEmailModel();

        $this->assertInstanceOf(SentEmail::class, $sentEmail);
    }

    /** @test */
    public function sent_email_model_can_be_changed()
    {
        MailTracker::useSentEmailModel(SentEmailStub::class);

        $sentEmail = MailTracker::sentEmailModel();

        $this->assertInstanceOf(SentEmailStub::class, $sentEmail);

        MailTracker::useSentEmailModel(SentEmail::class);
    }

    /** @test */
    public function sent_email_url_clicked_model_can_be_created()
    {
        $sentEmailUrlClicked = MailTracker::sentEmailUrlClickedModel();

        $this->assertInstanceOf(SentEmailUrlClicked::class, $sentEmailUrlClicked);
    }

    /** @test */
    public function sent_email_url_clicked_model_can_be_changed()
    {
        MailTracker::useSentEmailUrlClickedModel(SentEmailUrlClickedStub::class);

        $sentEmailUrlClicked = MailTracker::sentEmailUrlClickedModel();

        $this->assertInstanceOf(SentEmailUrlClickedStub::class, $sentEmailUrlClicked);

        MailTracker::useSentEmailUrlClickedModel(SentEmailUrlClicked::class);
    }
}

class SentEmailStub extends Model
{
}

class SentEmailUrlClickedStub extends Model
{
}
