<?php

declare(strict_types=1);

namespace jdavidbakr\MailTracker\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use jdavidbakr\MailTracker\Contracts\MailTrackerDriver;
use jdavidbakr\MailTracker\MailTrackerManager;

class CallbackController extends Controller
{
    public function __invoke(Request $request, string $driver, MailTrackerManager $manager): Response
    {
        /** @var MailTrackerDriver $driver */
        $driver = $manager->driver($driver);

        if (! $driver) {
            abort(404);
        }

        return $driver->callback($request);
    }
}
