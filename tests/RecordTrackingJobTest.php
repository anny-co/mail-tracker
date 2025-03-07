<?php

namespace jdavidbakr\MailTracker\Tests;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use jdavidbakr\MailTracker\Events\ViewEmailEvent;
use jdavidbakr\MailTracker\Jobs\RecordTrackingJob;
use jdavidbakr\MailTracker\MailTracker;

class RecordTrackingJobTest extends SetUpTest
{
    /** @test */
    public function it_records_views()
    {
        Event::fake();
        $track = MailTracker::sentEmailModel()->newQuery()->create([
            'hash' => Str::random(32),
        ]);
        $job = new RecordTrackingJob($track, '127.0.0.1');

        $job->handle();

        Event::assertDispatched(ViewEmailEvent::class, function ($event) use ($track) {
            return $track->id == $event->sentEmail->id &&
                $event->ipAddress == '127.0.0.1';
        });
        $this->assertDatabaseHas('sent_emails', [
            'id' => $track->id,
            'opens' => 1,
        ]);
    }
}
