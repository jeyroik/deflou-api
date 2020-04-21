<?php
namespace tests\applications\events;

use deflou\components\applications\activities\Activity;
use deflou\components\applications\activities\events\EventTriggerLaunched;
use deflou\components\applications\anchors\Anchor;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

/**
 * Class EventTriggerLaunchedTest
 *
 * @package tests
 * @author jeyroik@gmail.com
 */
class EventTriggerLaunchedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $env = Dotenv::create(getcwd() . '/tests/');
        $env->load();
    }

    /**
     * @throws \Exception
     */
    public function testMissedTriggerName()
    {
        $event = new EventTriggerLaunched();

        $this->expectExceptionMessage(
            'Missed "' . EventTriggerLaunched::FIELD__TRIGGER_NAME . '" parameter'
        );
        $event(
            new Activity([
                Activity::FIELD__NAME => 'test_event',
                Activity::FIELD__SAMPLE_NAME => 'test_event',
                Activity::FIELD__APPLICATION_NAME => 'test',
                Activity::FIELD__TYPE => Activity::TYPE__EVENT,
                Activity::FIELD__PARAMETERS => [
                    EventTriggerLaunched::FIELD__ANCHOR => [],
                    EventTriggerLaunched::FIELD__TRIGGER_RESPONSE => []
                ]
            ]),
            new Anchor([
                Anchor::FIELD__PLAYER_NAME => 'test_player'
            ])
        );
    }

    /**
     * @throws \Exception
     */
    public function testMissedTriggerResponse()
    {
        $event = new EventTriggerLaunched();

        $this->expectExceptionMessage(
            'Missed "' . EventTriggerLaunched::FIELD__TRIGGER_RESPONSE . '" parameter'
        );
        $event(
            new Activity([
                Activity::FIELD__NAME => 'test_event',
                Activity::FIELD__SAMPLE_NAME => 'test_event',
                Activity::FIELD__APPLICATION_NAME => 'test',
                Activity::FIELD__TYPE => Activity::TYPE__EVENT,
                Activity::FIELD__PARAMETERS => [
                    EventTriggerLaunched::FIELD__ANCHOR => [],
                    EventTriggerLaunched::FIELD__TRIGGER_NAME => ''
                ]
            ]),
            new Anchor([
                Anchor::FIELD__PLAYER_NAME => 'test_player'
            ])
        );
    }

    /**
     * @throws \Exception
     */
    public function testMissedAnchor()
    {
        $event = new EventTriggerLaunched();

        $this->expectExceptionMessage(
            'Missed "' . EventTriggerLaunched::FIELD__ANCHOR . '" parameter'
        );
        $event(
            new Activity([
                Activity::FIELD__NAME => 'test_event',
                Activity::FIELD__SAMPLE_NAME => 'test_event',
                Activity::FIELD__APPLICATION_NAME => 'test',
                Activity::FIELD__TYPE => Activity::TYPE__EVENT,
                Activity::FIELD__PARAMETERS => [
                    EventTriggerLaunched::FIELD__TRIGGER_RESPONSE => [],
                    EventTriggerLaunched::FIELD__TRIGGER_NAME => ''
                ]
            ]),
            new Anchor([
                Anchor::FIELD__PLAYER_NAME => 'test_player'
            ])
        );
    }
}
