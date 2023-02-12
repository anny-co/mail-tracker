<?php

namespace jdavidbakr\MailTracker\Tests;

use jdavidbakr\MailTracker\Contracts\MailerResolver;
use jdavidbakr\MailTracker\MailTracker;

class MailerResolverTest extends SetUpTest
{

    protected function resolver(): MailerResolver {
        return $this->app->make(MailerResolver::class);
    }

    /** @test */
    public function it_resolves_mailer_from_event_data()
    {
        $this->assertEquals('mailer-test',
            $this->resolver()->resolve(['mailer' => 'mailer-test'])
        );
    }

    /** @test */
    public function it_resolves_mailer_from_mail_tracker()
    {
        MailTracker::usedMailer('mailer-test');

        $this->assertEquals('mailer-test',
            $this->resolver()->resolve([])
        );

        MailTracker::usedMailer(null);
    }

    /** @test */
    public function it_resolves_mailer_from_config()
    {
        $this->assertEquals('smtp',
            $this->resolver()->resolve()
        );
    }

    /** @test */
    public function it_resolves_mailer_from_old_config()
    {
        config()->set('mail.driver', 'smtp-old');

        $this->assertEquals('smtp-old',
            $this->resolver()->resolve()
        );
    }
}