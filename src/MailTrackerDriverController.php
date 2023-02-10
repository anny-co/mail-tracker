<?php
declare(strict_types=1);

namespace jdavidbakr\MailTracker;

use jdavidbakr\MailTracker\Contracts\MailTrackerDriver;

abstract class MailTrackerDriverController implements MailTrackerDriver
{

    public string $id;

}
