<?php
declare(strict_types=1);

namespace jdavidbakr\MailTracker\Tests;

use jdavidbakr\MailTracker\Drivers\SNSDriver;
use jdavidbakr\MailTracker\MailTrackerManager;
use Mockery\MockInterface;

class MailTrackerDriverTest extends SetUpTest
{

    /** @test */
    public function legacy_sns_route_calls_callback_controller()
    {
        /** @var MailTrackerManager $manager */
        $manager = $this->app->get(MailTrackerManager::class);

        $manager->extend('ses', function(){
            return \Mockery::mock(SNSDriver::class, function(MockInterface $mock) {
                $mock->shouldReceive('callback')
                    ->andReturn(response('success'));
            });
        });

        $this->post(route('mailTracker_SNS'), [])
            ->assertContent('success');
    }
}

