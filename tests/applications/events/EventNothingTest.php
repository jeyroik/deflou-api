<?php
namespace tests\applications\events;

use deflou\components\applications\activities\Activity;
use deflou\components\applications\activities\events\EventNothing;
use deflou\components\applications\anchors\Anchor;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

/**
 * Class EventNothingTest
 *
 * @package tests
 * @author jeyroik@gmail.com
 */
class EventNothingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $env = Dotenv::create(getcwd() . '/tests/');
        $env->load();
    }

    public function testTriggering()
    {
        $event = new EventNothing();

        $response = $event(
            new Activity([
                Activity::FIELD__NAME => 'test_event',
                Activity::FIELD__SAMPLE_NAME => 'test_event',
                Activity::FIELD__APPLICATION_NAME => 'test',
                Activity::FIELD__TYPE => Activity::TYPE__EVENT
            ]),
            new Anchor([
                Anchor::FIELD__PLAYER_NAME => 'test_player'
            ])
        );
        $this->assertTrue($response->hasParameter('current_date'));
    }
}
