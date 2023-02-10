<?php
declare(strict_types=1);

namespace jdavidbakr\MailTracker\Drivers\Mailgun;

use Illuminate\Http\Request;
use jdavidbakr\MailTracker\MailTrackerDriverController;

class MailgunDriver extends MailTrackerDriverController
{

    public string $id = 'mailgun';

    public function boot()
    {
        // TODO: Implement installRoutes() method.
    }

    public function callback(Request $request)
    {
        // TODO: Implement callback() method.
    }


}
