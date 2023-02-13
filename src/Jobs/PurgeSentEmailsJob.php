<?php

namespace jdavidbakr\MailTracker\Jobs;

use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use jdavidbakr\MailTracker\MailTracker;

class PurgeSentEmailsJob implements ShouldQueue, ShouldBeUnique
{
    public function handle()
    {
        $expireDays = config('mail-tracker.expire-days');
        if (!$expireDays) {
            return;
        }

        MailTracker::sentEmailModel()->newQuery()->where('created_at', '<', Carbon::now()
            ->subDays(config('mail-tracker.expire-days')))
            ->select(columns: ['id', 'meta'])
            ->chunk(1000, function (Collection $emails) {
                // collect paths
                $paths = [];
                $emails->each(function ($email) use (&$paths) {
                    if ($email->meta && ($filePath = $email->meta->get('content_file_path'))) {
                        $paths[] = $filePath;
                    }
                });

                // delete files
                try{
                    Storage::disk(config('mail-tracker.tracker-filesystem'))->delete($paths);
                } catch (\Exception $exception){
                    // fail silently
                }

                // delete from database
                MailTracker::sentEmailUrlClickedModel()->newQuery()->whereIn('sent_email_id', $emails->pluck('id'))->delete();
                MailTracker::sentEmailModel()->newQuery()->whereIn('id', $emails->pluck('id'))->delete();
            });
    }
}