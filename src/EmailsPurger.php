<?php

namespace jdavidbakr\MailTracker;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class EmailsPurger
{

    public function purge(int|null $expireDays = null): void
    {
        if(!$expireDays) {
            $expireDays = config('mail-tracker.expire-days');
        }

        if(!$expireDays){
            return;
        }

        MailTracker::sentEmailModel()->newQuery()
            ->where('created_at', '<', Carbon::now()->subDays($expireDays))
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