<?php

use jdavidbakr\MailTracker\MailTracker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Orchestra\Testbench\BrowserKit\TestCase;

class AddressVerificationTest extends TestCase
{
    /**
     * Setup the test environment.
     */
    public function setUp()
    {
        parent::setUp();

        $this->artisan('migrate', ['--database' => 'testing']);
    }

    /**
     * Get package providers.  At a minimum this is the package being tested, but also
     * would include packages upon which our package depends, e.g. Cartalyst/Sentry
     * In a normal app environment these would be added to the 'providers' array in
     * the config/app.php file.
     *
     * @param  \Illuminate\Foundation\Application  $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return ['jdavidbakr\MailTracker\MailTrackerServiceProvider'];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        $app['config']->set('database.connections.secondary', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => ''
        ]);
        $app['config']->set('aws.credentials', [
            'key'    => 'aws-key',
            'secret' => 'aws-secret',
        ]);
        $app['config']->set('mail.from.address', 'test@example.com');
        $app['config']->set('app.debug', true);
    }

    public function testSendMessage()
    {
        // Create an old email to purge
        Config::set('mail-tracker.expire-days', 1);
        $old_email = \jdavidbakr\MailTracker\Model\SentEmail::create([
                'hash'=>str_random(32),
            ]);
        $old_url = \jdavidbakr\MailTracker\Model\SentEmailUrlClicked::create([
                'sent_email_id'=>$old_email->id,
                'hash'=>str_random(32),
            ]);
        // Go into the future to make sure that the old email gets removed
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::now()->addWeek());

        Event::fake();

        $faker = Faker\Factory::create();
        $email = $faker->email;
        $subject = $faker->sentence;
        $name = $faker->firstName . ' ' .$faker->lastName;
        \View::addLocation(__DIR__);
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

        Event::assertDispatched(jdavidbakr\MailTracker\Events\EmailSentEvent::class);

        $this->seeInDatabase('sent_emails', [
                'recipient'=>$name.' <'.$email.'>',
                'subject'=>$subject,
                'sender'=>'From Name <from@johndoe.com>',
                'recipient'=>"{$name} <{$email}>",
            ]);
        $this->assertNull($old_email->fresh());
        $this->assertNull($old_url->fresh());
    }

    public function testSendMessageWithMailRaw()
    {
        $faker = Faker\Factory::create();
        $email = $faker->email;
        $name = $faker->firstName . ' ' .$faker->lastName;
        $content = 'Text to e-mail';
        \View::addLocation(__DIR__);

        Mail::raw($content, function($message) use ($email, $name)
        {
            $message->from('from@johndoe.com', 'From Name');

            $message->to($email, $name);
        });

        $this->seeInDatabase('sent_emails', [
            'recipient'=>$name.' <'.$email.'>',
            'sender'=>'From Name <from@johndoe.com>',
            'recipient'=>"{$name} <{$email}>",
            'content'=>$content
        ]);
    }

    /**
     * @test
     */
    public function it_doesnt_track_if_told_not_to()
    {
        Event::fake();

        $faker = Faker\Factory::create();
        $email = $faker->email;
        $subject = $faker->sentence;
        $name = $faker->firstName . ' ' .$faker->lastName;
        \View::addLocation(__DIR__);
        \Mail::send('email.test', [], function ($message) use ($email, $subject, $name) {
            $message->from('from@johndoe.com', 'From Name');
            $message->sender('sender@johndoe.com', 'Sender Name');

            $message->to($email, $name);

            $message->cc('cc@johndoe.com', 'CC Name');
            $message->bcc('bcc@johndoe.com', 'BCC Name');

            $message->replyTo('reply-to@johndoe.com', 'Reply-To Name');

            $message->subject($subject);

            $message->priority(3);

            $message->getHeaders()->addTextHeader('X-No-Track', str_random(10));
        });

        $this->dontSeeInDatabase('sent_emails', [
                'recipient'=>$name.' <'.$email.'>',
                'subject'=>$subject,
                'sender'=>'From Name <from@johndoe.com>',
                'recipient'=>"{$name} <{$email}>",
            ]);
    }

    public function testPing()
    {
        $track = \jdavidbakr\MailTracker\Model\SentEmail::create([
                'hash'=>str_random(32),
            ]);

        Event::fake();

        $pings = $track->opens;
        $pings++;

        $url = action('\jdavidbakr\MailTracker\MailTrackerController@getT', [$track->hash]);
        $this->visit($url);

        $track = $track->fresh();
        $this->assertEquals($pings, $track->opens);

        Event::assertDispatched(jdavidbakr\MailTracker\Events\ViewEmailEvent::class);
    }

    public function testLink()
    {
        $track = \jdavidbakr\MailTracker\Model\SentEmail::create([
                'hash'=>str_random(32),
            ]);

        Event::fake();

        $clicks = $track->clicks;
        $clicks++;

        $redirect = 'http://'.str_random(15).'.com/'.str_random(10).'/'.str_random(10).'/'.rand(0, 100).'/'.rand(0, 100).'?page='.rand(0, 100).'&x='.str_random(32);

        $url = action('\jdavidbakr\MailTracker\MailTrackerController@getL', [
                \jdavidbakr\MailTracker\MailTracker::hash_url($redirect), // Replace slash with dollar sign
                $track->hash
            ]);
        $this->call('GET', $url);
        $this->assertRedirectedTo($redirect);

        Event::assertDispatched(jdavidbakr\MailTracker\Events\LinkClickedEvent::class);

        $this->seeInDatabase('sent_emails_url_clicked', [
                'url'=>$redirect,
                'clicks'=>1,
            ]);

        $track = $track->fresh();
        $this->assertEquals($clicks, $track->clicks);

        // Do it with an invalid hash
        $url = action('\jdavidbakr\MailTracker\MailTrackerController@getL', [
                \jdavidbakr\MailTracker\MailTracker::hash_url($redirect), // Replace slash with dollar sign
                'bad-hash'
            ]);
        $this->call('GET', $url);
        $this->assertRedirectedTo($redirect);
    }

    /**
     * @test
     *
     * Note that to complete this test, you must have aws credentials as well as a valid
     * from address in the mail config.
     */
    public function it_retrieves_the_mesage_id_from_ses()
    {
        Config::set('mail.driver', 'ses');
        $headers = Mockery::mock();
        $headers->shouldReceive('get')
            ->with('X-No-Track')
            ->once()
            ->andReturn(null);
        $this->mailer_hash = '';
        $headers->shouldReceive('addTextHeader')
            ->once()
            ->andReturnUsing(function ($key, $value) {
                $this->mailer_hash = $value;
            });
        $mailer_hash_header = Mockery::mock();
        $mailer_hash_header->shouldReceive('getFieldBody')
            ->once()
            ->andReturnUsing(function () {
                return $this->mailer_hash;
            });
        $headers->shouldReceive('get')
            ->with('X-Mailer-Hash')
            ->once()
            ->andReturn($mailer_hash_header);
        $headers->shouldReceive('get')
            ->with('X-SES-Message-ID')
            ->once()
            ->andReturn(Mockery::mock([
                'getFieldBody'=>'aws-mailer-hash'
            ]));
        $headers->shouldReceive('toString')
            ->once();
        $event = Mockery::mock(\Swift_Events_SendEvent::class, [
            'getMessage'=>Mockery::mock([
                'getTo'=>[
                    'destination@example.com'=>'Destination Person'
                ],
                'getFrom'=>[
                    'from@example.com'=>'From Name'
                ],
                'getHeaders'=>$headers,
                'getSubject'=>'The message subject',
                'getBody'=>'The body',
                'getContentType'=>'text/html',
                'setBody'=>null,
                'getChildren'=>[],
                'getId'=>'message-id',
            ]),
        ]);
        $tracker = new \jdavidbakr\MailTracker\MailTracker();

        $tracker->beforeSendPerformed($event);
        $tracker->sendPerformed($event);

        $sent_email = \jdavidbakr\MailTracker\Model\SentEmail::orderBy('id', 'desc')->first();
        $this->assertEquals('aws-mailer-hash', $sent_email->message_id);
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
        $this->post($url, [
                'message'=>json_encode([
                        // Required
                        'Message'=>'test subscription message',
                        'MessageId'=>str_random(10),
                        'Timestamp'=>\Carbon\Carbon::now()->timestamp,
                        'TopicArn'=>str_random(10),
                        'Type'=>'SubscriptionConfirmation',
                        'Signature'=>str_random(32),
                        'SigningCertURL'=>str_random(32),
                        'SignatureVersion'=>1,
                        // Request-specific
                        'SubscribeURL'=>'http://google.com',
                        'Token'=>str_random(10),
                    ])
            ]);
        $this->see('subscription confirmed');
    }

    /**
     * @test
     */
    public function it_processes_with_registered_topic()
    {
        $topic = str_random(32);
        Config::set('mail-tracker.sns-topic', $topic);
        $url = action('\jdavidbakr\MailTracker\SNSController@callback');
        $this->post($url, [
                'message'=>json_encode([
                        // Required
                        'Message'=>'test subscription message',
                        'MessageId'=>str_random(10),
                        'Timestamp'=>\Carbon\Carbon::now()->timestamp,
                        'TopicArn'=>$topic,
                        'Type'=>'SubscriptionConfirmation',
                        'Signature'=>str_random(32),
                        'SigningCertURL'=>str_random(32),
                        'SignatureVersion'=>1,
                        // Request-specific
                        'SubscribeURL'=>'http://google.com',
                        'Token'=>str_random(10),
                    ])
            ]);
        $this->see('subscription confirmed');
    }

    /**
     * @test
     */
    public function it_ignores_invalid_topic()
    {
        $topic = str_random(32);
        Config::set('mail-tracker.sns-topic', $topic);
        $url = action('\jdavidbakr\MailTracker\SNSController@callback');
        $this->post($url, [
                'message'=>json_encode([
                        // Required
                        'Message'=>'test subscription message',
                        'MessageId'=>str_random(10),
                        'Timestamp'=>\Carbon\Carbon::now()->timestamp,
                        'TopicArn'=>str_random(32),
                        'Type'=>'SubscriptionConfirmation',
                        'Signature'=>str_random(32),
                        'SigningCertURL'=>str_random(32),
                        'SignatureVersion'=>1,
                        // Request-specific
                        'SubscribeURL'=>'http://google.com',
                        'Token'=>str_random(10),
                    ])
            ]);
        $this->See('invalid topic ARN');
    }

    /**
     * @test
     */
    public function it_processes_a_delivery()
    {
        $track = \jdavidbakr\MailTracker\Model\SentEmail::create([
                'hash'=>str_random(32),
            ]);
        $message_id = str_random(32);
        $track->message_id = $message_id;
        $track->save();

        $this->post(action('\jdavidbakr\MailTracker\SNSController@callback'), [
                'message'=>json_encode([
                        // Required
                        'Message'=>json_encode([
                            'notificationType'=>'Delivery',
                            'mail'=>[
                                'timestamp'=>\Carbon\Carbon::now()->timestamp,
                                'messageId'=>$message_id,
                                'source'=>$track->sender,
                                'sourceArn'=>str_random(32),
                                'sendingAccountId'=>str_random(10),
                                'destination'=>[$track->recipient],
                            ],
                            'delivery'=>[
                                'timestamp'=>\Carbon\Carbon::now()->timestamp,
                                'processingTimeMillis'=>1000,
                                'recipients'=>[$track->recipient],
                                'smtpResponse'=>'test smtp response',
                                'reportingMTA'=>str_random(10),
                            ],
                        ]),
                        'MessageId'=>str_random(10),
                        'Timestamp'=>\Carbon\Carbon::now()->timestamp,
                        'TopicArn'=>str_random(10),
                        'Type'=>'Notification',
                        'Signature'=>str_random(32),
                        'SigningCertURL'=>str_random(32),
                        'SignatureVersion'=>1,
                        // Request-specific

                    ])
            ]);
        $this->see('notification processed');
        $track = $track->fresh();
        $meta = $track->meta;
        $this->assertEquals('test smtp response', $meta->get('smtpResponse'));
        $this->assertTrue($meta->get('success'));
    }

    /**
     * @test
     */
    public function it_processes_a_bounce()
    {
        Event::fake();
        $track = \jdavidbakr\MailTracker\Model\SentEmail::create([
                'hash'=>str_random(32),
            ]);
        $message_id = str_random(32);
        $track->message_id = $message_id;
        $track->save();

        $this->post(action('\jdavidbakr\MailTracker\SNSController@callback'), [
                'message'=>json_encode([
                        // Required
                        'Message'=>json_encode([
                            'notificationType'=>'Bounce',
                            'mail'=>[
                                'timestamp'=>\Carbon\Carbon::now()->timestamp,
                                'messageId'=>$message_id,
                                'source'=>$track->sender,
                                'sourceArn'=>str_random(32),
                                'sendingAccountId'=>str_random(10),
                                'destination'=>[$track->recipient],
                            ],
                            'bounce'=>[
                                'bounceType'=>'Permanent',
                                'bounceSubType'=>'General',
                                'bouncedRecipients'=>[
                                    [
                                        'status'=>'5.0.0',
                                        'action'=>'failed',
                                        'diagnosticCode'=>'smtp; 550 user unknown',
                                        'emailAddress'=>'recipient@example.com',
                                    ],
                                ],
                                'timestamp'=>\Carbon\Carbon::now()->timestamp,
                                'feedbackId'=>str_random(10),
                            ],
                        ]),
                        'MessageId'=>str_random(10),
                        'Timestamp'=>\Carbon\Carbon::now()->timestamp,
                        'TopicArn'=>str_random(10),
                        'Type'=>'Notification',
                        'Signature'=>str_random(32),
                        'SigningCertURL'=>str_random(32),
                        'SignatureVersion'=>1,
                        // Request-specific

                    ])
            ]);
        $this->see('notification processed');
        $track = $track->fresh();
        $meta = $track->meta;
        $this->assertFalse($meta->get('success'));
        Event::assertDispatched(jdavidbakr\MailTracker\Events\PermanentBouncedMessageEvent::class, function ($event) {
            return $event->email_address == 'recipient@example.com';
        });
    }

    /**
     * @test
     */
    public function it_processes_a_complaint()
    {
        Event::fake();
        $track = \jdavidbakr\MailTracker\Model\SentEmail::create([
                'hash'=>str_random(32),
            ]);
        $message_id = str_random(32);
        $track->message_id = $message_id;
        $track->save();

        $this->post(action('\jdavidbakr\MailTracker\SNSController@callback'), [
                'message'=>json_encode([
                        // Required
                        'Message'=>json_encode([
                            'notificationType'=>'Complaint',
                            'mail'=>[
                                'timestamp'=>\Carbon\Carbon::now()->timestamp,
                                'messageId'=>$message_id,
                                'source'=>$track->sender,
                                'sourceArn'=>str_random(32),
                                'sendingAccountId'=>str_random(10),
                                'destination'=>[$track->recipient],
                            ],
                            'complaint'=>[
                                'complainedRecipients'=>[
                                    [
                                        'emailAddress'=>'recipient@example.com',
                                    ],
                                ],
                                'timestamp'=>\Carbon\Carbon::now()->timestamp,
                                'feedbackId'=>str_random(10),
                                'userAgent'=>str_random(10),
                                'complaintFeedbackType'=>'feedback type',
                                'arrivalDate'=>\Carbon\Carbon::now(),
                            ],
                        ]),
                        'MessageId'=>str_random(10),
                        'Timestamp'=>\Carbon\Carbon::now()->timestamp,
                        'TopicArn'=>str_random(10),
                        'Type'=>'Notification',
                        'Signature'=>str_random(32),
                        'SigningCertURL'=>str_random(32),
                        'SignatureVersion'=>1,
                        // Request-specific

                    ])
            ]);
        $this->see('notification processed');
        $track = $track->fresh();
        $meta = $track->meta;
        $this->assertFalse($meta->get('success'));
        Event::assertDispatched(jdavidbakr\MailTracker\Events\PermanentBouncedMessageEvent::class, function ($event) {
            return $event->email_address == 'recipient@example.com';
        });
    }/** @noinspection ProblematicWhitespace */

    /**
     * @test
     */
    public function it_handles_ampersands_in_links()
    {
        Event::fake();
        Config::set('mail-tracker.track-links', true);
        Config::set('mail-tracker.inject-pixel', true);
        Config::set('mail.driver', 'array');
        (new Illuminate\Mail\MailServiceProvider(app()))->register();
        // Must re-register the MailTracker to get the test to work
        $this->app['mailer']->getSwiftMailer()->registerPlugin(new MailTracker());

        $faker = Faker\Factory::create();
        $email = $faker->email;
        $subject = $faker->sentence;
        $name = $faker->firstName . ' ' .$faker->lastName;
        \View::addLocation(__DIR__);

        \Mail::send('email.testAmpersand', [], function ($message) use ($email, $subject, $name) {
            $message->from('from@johndoe.com', 'From Name');
            $message->sender('sender@johndoe.com', 'Sender Name');

            $message->to($email, $name);

            $message->cc('cc@johndoe.com', 'CC Name');
            $message->bcc('bcc@johndoe.com', 'BCC Name');

            $message->replyTo('reply-to@johndoe.com', 'Reply-To Name');

            $message->subject($subject);

            $message->priority(3);
        });

        $driver = $this->app['swift.transport']->driver();
        $this->assertEquals(1, count($driver->messages()));

        $mes = $driver->messages()[0];
        $body = $mes->getBody();
        $hash = $mes->getHeaders()->get('X-Mailer-Hash')->getValue();

        $matches = null;
        preg_match_all('/(<a[^>]*href=[\'"])([^\'"]*)/', $body, $matches);
        $links = $matches[2];
        $aLink = $links[1];

        $expected_url = "http://www.google.com?q=foo&x=bar";
        $this->assertNotNull($aLink);
        $this->assertNotEquals($expected_url, $aLink);

        $this->call('GET', $aLink);
        $this->assertRedirectedTo($expected_url);

        Event::assertDispatched(jdavidbakr\MailTracker\Events\LinkClickedEvent::class);

        $this->seeInDatabase('sent_emails_url_clicked', [
            'url' => $expected_url,
            'clicks' => 1,
        ]);

        $track = \jdavidbakr\MailTracker\Model\SentEmail::whereHash($hash)->first();
        $this->assertNotNull($track);
        $this->assertEquals(1, $track->clicks);
    }

    /**
     * @test
     */
    public function it_retrieves_header_data()
    {
        $faker = Faker\Factory::create();
        $email = $faker->email;
        $subject = $faker->sentence;
        $name = $faker->firstName . ' ' .$faker->lastName;
        $header_test = str_random(10);
        \View::addLocation(__DIR__);
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

        $track = \jdavidbakr\MailTracker\Model\SentEmail::orderBy('id', 'desc')->first();
        $this->assertEquals($header_test, $track->getHeader('X-Header-Test'));
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

        $old_email = \jdavidbakr\MailTracker\Model\SentEmail::create([
            'hash'=>str_random(32),
        ]);
        $old_url = \jdavidbakr\MailTracker\Model\SentEmailUrlClicked::create([
            'sent_email_id'=>$old_email->id,
            'hash'=>str_random(32),
        ]);
        // Go into the future to make sure that the old email gets removed
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::now()->addWeek());

        Event::fake();

        $faker = Faker\Factory::create();
        $email = $faker->email;
        $subject = $faker->sentence;
        $name = $faker->firstName . ' ' .$faker->lastName;
        \View::addLocation(__DIR__);
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

        Event::assertDispatched(jdavidbakr\MailTracker\Events\EmailSentEvent::class);

        $this->seeInDatabase('sent_emails', [
            'recipient'=>$name.' <'.$email.'>',
            'subject'=>$subject,
            'sender'=>'From Name <from@johndoe.com>'
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
        $track = \jdavidbakr\MailTracker\Model\SentEmail::create([
            'hash' => str_random(32),
        ]);
        $message_id = str_random(32);
        $track->message_id = $message_id;
        $track->save();

        $urlClick = \jdavidbakr\MailTracker\Model\SentEmailUrlClicked::create([
            'sent_email_id' => $track->id,
            'url' => 'https://example.com',
            'hash' => str_random(32)
        ]);
        $urlClick->save();
        $this->assertTrue($track->urlClicks->count() === 1);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $track->urlClicks);
        $this->assertInstanceOf(\jdavidbakr\MailTracker\Model\SentEmailUrlClicked::class, $track->urlClicks->first());
    }
}
