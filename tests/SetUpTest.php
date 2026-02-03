<?php

namespace jdavidbakr\MailTracker\Tests;

use Orchestra\Testbench\TestCase;

abstract class SetUpTest extends TestCase
{
    /**
     * Setup the test environment.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--database' => 'testbench']);

        return;
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
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('database.connections.secondary', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => ''
        ]);
        $app['config']->set('aws.credentials', [
            'key' => 'aws-key',
            'secret' => 'aws-secret',
        ]);
        $app['config']->set('mail.from.address', 'test@example.com');
        $app['config']->set('mail.default', 'log');
        $app['config']->set('app.debug', true);
    }
}
