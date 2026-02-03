<?php

namespace jdavidbakr\MailTracker\Tests;

use Illuminate\Support\Facades\Storage;
use jdavidbakr\MailTracker\Model\SentEmail;

class DeleteFileTest extends SetUpTest
{

    /** @test */
    public function it_deletes_file_after_model_is_deleted()
    {
        $disk = 'testing';
        config([
            'mail-tracker.log-content-strategy' => 'filesystem',
            'mail-tracker.tracker-filesystem' => $disk,
            'mail-tracker.tracker-filesystem-folder' => 'mail-tracker',
            'filesystems.disks.testing.driver' => 'local',
            'filesystems.default' => 'testing',
        ]);

        Storage::fake($disk);

        // create model and file
        $filePath = 'mail-tracker/random-hash.html';
        $sentEmail = SentEmail::query()->create([
            'hash' => 'random-hash',
            'sender_name' => 'From Name',
            'sender_email' => 'from@johndoe.com',
            'recipient_name' => 'name',
            'recipient_email' => 'email@test.com',
            'content' => null,
            'meta' => collect(['content_file_path' => $filePath])
        ]);

        Storage::disk($disk)->put($filePath, 'html-content of email');
        $sentEmail->delete();

        Storage::assertMissing($filePath);
    }
}