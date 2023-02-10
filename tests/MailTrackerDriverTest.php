<?php
declare(strict_types=1);

namespace jdavidbakr\MailTracker\Tests;

use jdavidbakr\MailTracker\Drivers\SNS\SNSDriver;
use jdavidbakr\MailTracker\MailTrackerManager;
use Mockery\Mock;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class MailTrackerDriverTest extends SetUpTest
{

    /** @test */
    public function legacy_sns_route_calls_callback_controller()
    {
        /** @var MailTrackerManager $manager */
        $manager = $this->app->get(MailTrackerManager::class);

        $manager->extend('sns', function(){
            return \Mockery::mock(SNSDriver::class, function(MockInterface $mock) {
                $mock->shouldReceive('callback')
                    ->andReturn(response('success'));
            });
        });

        $this->post(route('mailTracker_SNS'), [])
            ->assertContent('success');
    }
}

