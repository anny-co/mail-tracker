<?php
declare(strict_types=1);

namespace jdavidbakr\MailTracker;

use Illuminate\Support\Manager;
use jdavidbakr\MailTracker\Contracts\MailTrackerDriver;
use jdavidbakr\MailTracker\Drivers\SMTP\SMTPDriver;
use jdavidbakr\MailTracker\Drivers\SNS\SNSDriver;

class MailTrackerManager extends Manager
{

    public function getDefaultDriver(): string
    {
        return 'smtp';
    }

    /**
     * @return SMTPDriver
     */
    public function createSmtpDriver(): MailTrackerDriver {
        return new SMTPDriver();
    }

    /**
     * @return SNSDriver
     */
    public function createSnsDriver(): MailTrackerDriver {
        return new SNSDriver();
    }

}
