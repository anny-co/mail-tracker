<?php

namespace jdavidbakr\MailTracker\Tests;

use Carbon\Carbon;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use jdavidbakr\MailTracker\EmailsPurger;
use jdavidbakr\MailTracker\Jobs\PurgeSentEmailsJob;
use jdavidbakr\MailTracker\MailTracker;

class PurgeEmailsTest extends SetUpTest
{
    protected function createEmailAndClick(): array
    {
        $oldSentEmail = MailTracker::sentEmailModel()->newQuery()->create([
            'hash' => Str::random(32),
        ]);
        $oldUrlClicked = MailTracker::sentEmailUrlClickedModel()->newQuery()->create([
            'sent_email_id' => $oldSentEmail->id,
            'hash' => Str::random(32),
        ]);

        return [$oldSentEmail, $oldUrlClicked];
    }

    /** @test */
    public function it_schedules_purging()
    {
        MailTracker::shouldSchedulePurging('05:00');

        $this->travelTo(now()->addDay()->setTime(5, 0, 0));

        $events = $this->getScheduledEventsForCommand(PurgeSentEmailsJob::class, now()->addDay()->setTime(5, 0));

        $this->assertCount(1, $events);
    }

    /** @test */
    public function it_purges_old_emails()
    {
        config()->set('mail-tracker.expire-days', 1);
        [$oldSentEmail, $oldUrlClicked] = $this->createEmailAndClick();

        $this->travelTo(now()->addDay()->addMinute());

        $purger = new EmailsPurger();
        $purger->purge(1);

        $this->assertNull($oldSentEmail->fresh());
        $this->assertNull($oldUrlClicked->fresh());
    }

    /** @test */
    public function job_purges_old_emails()
    {
        config()->set('mail-tracker.expire-days', 1);
        [$oldSentEmail, $oldUrlClicked] = $this->createEmailAndClick();

        $this->travelTo(now()->addDay()->addMinute());

        $job = new PurgeSentEmailsJob();
        Bus::dispatchSync($job);

        $this->assertNull($oldSentEmail->fresh());
        $this->assertNull($oldUrlClicked->fresh());
    }

    /** @test */
    public function command_purges_old_emails()
    {
        config()->set('mail-tracker.expire-days', 1);
        [$oldSentEmail, $oldUrlClicked] = $this->createEmailAndClick();

        $this->travelTo(now()->addDay()->addMinute());

        $this->artisan('mail-tracker:purge');

        $this->assertNull($oldSentEmail->fresh());
        $this->assertNull($oldUrlClicked->fresh());
    }

    /** @test */
    public function command_overrides_expire_days()
    {
        config()->set('mail-tracker.expire-days', 10);
        [$oldSentEmail, $oldUrlClicked] = $this->createEmailAndClick();

        $this->travelTo(now()->addDays(2)->addMinute());

        $this->artisan('mail-tracker:purge --expire-days=2');

        $this->assertNull($oldSentEmail->fresh());
        $this->assertNull($oldUrlClicked->fresh());
    }

    /** @test */
    public function it_dont_purges_emails()
    {
        config()->set('mail-tracker.expire-days', 1);
        [$oldSentEmail, $oldUrlClicked] = $this->createEmailAndClick();

        $this->travelTo(now()->addDay()->subMinute());

        $purger = new EmailsPurger();
        $purger->purge(1);

        $this->assertNotNull($oldSentEmail->fresh());
        $this->assertNotNull($oldUrlClicked->fresh());
    }

    /** @test */
    public function it_dont_purges_with_config_0()
    {
        config()->set('mail-tracker.expire-days', 0);
        [$oldSentEmail, $oldUrlClicked] = $this->createEmailAndClick();

        $this->travelTo(now()->addDay()->addMinute());

        $purger = new EmailsPurger();
        $purger->purge();

        $this->assertNotNull($oldSentEmail->fresh());
        $this->assertNotNull($oldUrlClicked->fresh());
    }

    /** @test */
    public function it_purges_emails_from_secondary_connection()
    {
        config()->set('mail-tracker.expire-days', 1);
        config()->set('mail-tracker.connection', 'secondary');
        $this->app['migrator']->setConnection('secondary');
        $this->artisan('migrate', ['--database' => 'secondary']);

        [$oldSentEmail, $oldUrlClicked] = $this->createEmailAndClick();

        $this->travelTo(now()->addDay()->addMinute());

        $purger = new EmailsPurger();
        $purger->purge();

        $this->assertNull($oldSentEmail->fresh());
        $this->assertNull($oldUrlClicked->fresh());
    }

    /** @test */
    public function it_deletes_content_files()
    {
        $disk = 'testing';
        config([
            'mail-tracker.log-content-strategy' => 'filesystem',
            'mail-tracker.tracker-filesystem' => $disk,
            'mail-tracker.tracker-filesystem-folder' => 'mail-tracker',
            'filesystems.disks.testing.driver' => 'local',
            'filesystems.default' => 'testing',
            'mail-tracker.expire-days' => 1,
        ]);

        Storage::fake($disk);

        [$oldSentEmail, $oldUrlClicked] = $this->createEmailAndClick(); //

        // create file
        $filePath = 'mail-tracker/random-hash.html';
        $oldSentEmail->meta = collect(['content_file_path' => $filePath]);
        $oldSentEmail->save();

        Storage::disk($disk)->put($filePath, 'html-content of email');

        $this->travelTo(now()->addDay()->addMinute());

        $purger = new EmailsPurger();
        $purger->purge();

        Storage::assertMissing($filePath);
    }

    private function getScheduledEventsForCommand(string $commandName, Carbon $atTime = null): Collection
    {
        /**
         * @var Schedule $schedule
         */
        $schedule = $this->app->make(Schedule::class);

        return collect($schedule->events())->filter(function (Event $event) use ($commandName, $atTime) {
            if (! str_contains($event->command, $commandName) && strcmp($event->description, $commandName) !== 0) {
                return false;
            }

            // optionally filter out events that are not due at the given time.
            if ($atTime !== null) {
                $this->travelTo($atTime);
                Carbon::setTestNow($atTime);
                $isDue = $event->isDue($this->app);
                $this->travelBack();

                return $isDue;
            } else {
                return true;
            }
        });
    }
}
