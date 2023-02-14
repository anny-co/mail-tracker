<?php
declare(strict_types=1);

namespace jdavidbakr\MailTracker;

use Illuminate\Support\Manager;
use jdavidbakr\MailTracker\Contracts\MailTrackerDriver;
use jdavidbakr\MailTracker\Drivers\LocalDriver;
use jdavidbakr\MailTracker\Drivers\Mailgun\MailgunDriver;
use jdavidbakr\MailTracker\Drivers\SNSDriver;

class MailTrackerManager extends Manager
{

    public function getDefaultDriver(): string
    {
        return 'ses';
    }


    public function createSmtpDriver(): MailTrackerDriver
    {
        return new LocalDriver();
    }

    public function createArrayDriver(): MailTrackerDriver
    {
        return new LocalDriver();
    }

    public function createLogDriver(): MailTrackerDriver
    {
        return new LocalDriver();
    }

    /**
     * TODO: Need to test how to work with failover driver
     * @return LocalDriver
     */
    public function createFailoverDriver(): MailTrackerDriver
    {
        return new LocalDriver();
    }

    /**
     * @return SNSDriver
     */
    public function createSnsDriver(): MailTrackerDriver
    {
        return new SNSDriver();
    }

    /**
     * @return SNSDriver
     */
    public function createSesDriver(): MailTrackerDriver
    {
        return new SNSDriver();
    }

    public function createMailgunDriver(): MailTrackerDriver
    {
        $signingKey = config('mail-tracker.drivers.mailgun.signing-key',
            config('services.mailgun.signing_key')
        );

        $shouldVerifySignature = config('mail-tracker.drivers.mailgun.should-verify-signature');

        return new MailgunDriver($signingKey, $shouldVerifySignature);
    }
}
