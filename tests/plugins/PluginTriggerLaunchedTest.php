<?php
namespace tests\plugins;

use deflou\components\applications\activities\Activity;
use deflou\components\applications\activities\ActivityRepository;
use deflou\components\applications\events\EventTriggerLaunched;
use deflou\components\applications\anchors\Anchor;
use deflou\components\applications\anchors\AnchorRepository;
use deflou\components\applications\Application;
use deflou\components\applications\ApplicationRepository;
use deflou\components\plugins\triggers\PluginTriggerLaunched;
use deflou\components\triggers\Trigger;
use deflou\components\triggers\TriggerResponse;
use deflou\interfaces\triggers\ITriggerResponse;
use extas\components\loggers\BufferLogger;
use extas\components\loggers\TSnuffLogging;
use extas\components\players\Player;
use extas\components\players\PlayerRepository;
use extas\components\protocols\ProtocolRepository;
use extas\interfaces\samples\parameters\ISampleParameter;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class PluginTriggerLaunchedTest
 *
 * @package tests\plugins
 * @author jeyroik@gmail.com
 */
class PluginTriggerLaunchedTest extends TestCase
{
    use TSnuffLogging;

    protected function setUp(): void
    {
        parent::setUp();
        $env = \Dotenv\Dotenv::create(getcwd() . '/tests/');
        $env->load();
        defined('APP__ROOT') || define('APP__ROOT', getcwd());

        $this->turnSnuffLoggingOn();
        $this->registerSnuffRepos([
            'deflouApplicationRepository' => ApplicationRepository::class,
            'anchorRepository' => AnchorRepository::class,
            'deflouAnchorRepository' => AnchorRepository::class,
            'deflouActivityRepository' => ActivityRepository::class,
            'playerRepository' => PlayerRepository::class,
            'protocolRepository' => ProtocolRepository::class,
            'httpClient' => Client::class
        ]);
    }

    public function tearDown(): void
    {
        $this->unregisterSnuffRepos();
        $this->turnSnuffLoggingOff();
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \extas\components\exceptions\MissedOrUnknown
     */
    public function testMissedCurrentInstanceApplicationName()
    {
        $plugin = new PluginTriggerLaunched([
            PluginTriggerLaunched::FIELD__TRIGGER => new Trigger(),
            PluginTriggerLaunched::FIELD__ACTIVITY => new Activity()
        ]);
        $plugin(new TriggerResponse());
        $this->assertArrayHasKey('warning', BufferLogger::$log);
        $this->assertTrue(
            in_array('Missed or unknown current instance application ""', BufferLogger::$log['warning']),
            print_r(BufferLogger::$log, true)
        );
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \extas\components\exceptions\MissedOrUnknown
     */
    public function testMissedEventForCurrentInstance()
    {
        $this->createWithSnuffRepo('deflouApplicationRepository', new Application([
            Application::FIELD__NAME => 'deflou'
        ]));
        $plugin = new PluginTriggerLaunched([
            PluginTriggerLaunched::FIELD__TRIGGER => new Trigger(),
            PluginTriggerLaunched::FIELD__ACTIVITY => new Activity()
        ]);
        putenv('DF__APP_NAME=deflou');
        $plugin(new TriggerResponse());
        $this->assertArrayHasKey('warning', BufferLogger::$log);
        $this->assertTrue(
            in_array(
                'Missed or unknown event "trigger.launched" for the current instance',
                BufferLogger::$log['warning']
            ),
            print_r(BufferLogger::$log, true)
        );
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \extas\components\exceptions\MissedOrUnknown
     */
    public function testMissedEventAnchorForCurrentInstance()
    {
        $this->createWithSnuffRepo('deflouApplicationRepository', new Application([
            Application::FIELD__NAME => 'deflou'
        ]));
        $this->createWithSnuffRepo('deflouActivityRepository', new Activity([
            Activity::FIELD__NAME => 'trigger.launched_',
            Activity::FIELD__SAMPLE_NAME => 'trigger.launched',
            Activity::FIELD__APPLICATION_NAME => 'deflou',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT
        ]));
        $this->createWithSnuffRepo('playerRepository', new Player([
            Player::FIELD__NAME => 'test_player'
        ]));

        $plugin = new PluginTriggerLaunched([
            PluginTriggerLaunched::FIELD__TRIGGER => new Trigger(),
            PluginTriggerLaunched::FIELD__ACTIVITY => new Activity()
        ]);
        putenv('DF__APP_NAME=deflou');
        $plugin(new TriggerResponse());
        $this->assertArrayHasKey('warning', BufferLogger::$log);
        $this->assertTrue(
            in_array(
                'Missed anchor for a trigger.launched event',
                BufferLogger::$log['warning']
            ),
            print_r(BufferLogger::$log, true)
        );
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \extas\components\exceptions\MissedOrUnknown
     */
    public function testFailSendEvent()
    {
        $this->createWithSnuffRepo('deflouApplicationRepository', new Application([
            Application::FIELD__NAME => 'deflou',
            Application::FIELD__SAMPLE_NAME => 'deflou',
            Application::FIELD__PARAMETERS => [
                'host' => [
                    ISampleParameter::FIELD__NAME => 'host',
                    ISampleParameter::FIELD__VALUE => '*unknown.unk' . mt_rand(100, 999)
                ]
            ]
        ]));
        $this->createWithSnuffRepo('deflouActivityRepository', new Activity([
            Activity::FIELD__NAME => 'trigger.launched_',
            Activity::FIELD__SAMPLE_NAME => 'trigger.launched',
            Activity::FIELD__APPLICATION_NAME => 'deflou',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT
        ]));
        $this->createWithSnuffRepo('playerRepository', new Player([
            Player::FIELD__NAME => 'test_player'
        ]));
        $this->createWithSnuffRepo('anchorRepository', new Anchor([
            Anchor::FIELD__ID => 'test_anchor',
            Anchor::FIELD__EVENT_NAME => 'trigger.launched_',
            Anchor::FIELD__PLAYER_NAME => 'test_player',
            Anchor::FIELD__TRIGGER_NAME => 'test'
        ]));

        $plugin = new class ([
            PluginTriggerLaunched::FIELD__TRIGGER => new Trigger(),
            PluginTriggerLaunched::FIELD__ACTIVITY => new Activity()
        ]) extends PluginTriggerLaunched {
            protected function getSendingData($response, $currentEventAnchor)
            {
                throw new \Exception('Error');
            }

            protected function failSendEvent($e, $instance): void
            {
                throw new \Exception('Fail');
            }
        };
        $this->expectExceptionMessage('Fail');
        putenv('DF__APP_NAME=deflou');
        $plugin(new TriggerResponse());
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \extas\components\exceptions\MissedOrUnknown
     */
    public function testSuccessSending()
    {
        putenv('DF__APP_NAME=deflou');
        putenv('DF__VERSION=3.0');

        $this->createWithSnuffRepo('deflouApplicationRepository', new Application([
            Application::FIELD__NAME => 'deflou',
            Application::FIELD__SAMPLE_NAME => 'deflou',
        ]));
        $this->createWithSnuffRepo('deflouActivityRepository', new Activity([
            Activity::FIELD__NAME => 'trigger.launched_',
            Activity::FIELD__SAMPLE_NAME => 'trigger.launched',
            Activity::FIELD__APPLICATION_NAME => 'deflou',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT
        ]));
        $this->createWithSnuffRepo('playerRepository', new Player([
            Player::FIELD__NAME => 'test_player'
        ]));
        $currentEventAnchor = $this->createWithSnuffRepo('anchorRepository', new Anchor([
            Anchor::FIELD__ID => 'test_anchor',
            Anchor::FIELD__EVENT_NAME => 'trigger.launched_',
            Anchor::FIELD__PLAYER_NAME => 'test_player',
            Anchor::FIELD__TRIGGER_NAME => 'test'
        ]));

        $trigger = new Trigger([
            Trigger::FIELD__NAME => 'test',
            Trigger::FIELD__PLAYER_NAME => 'test_player'
        ]);

        $triggerResponse = new TriggerResponse();
        $anchor = new Anchor([
            Anchor::FIELD__ID => 'test'
        ]);

        $plugin = new class ([
            PluginTriggerLaunched::FIELD__TRIGGER => $trigger,
            PluginTriggerLaunched::FIELD__ACTIVITY => new Activity([
                Activity::FIELD__PARAMETERS => [
                    'anchor' => [
                        'name' => 'anchor',
                        'value' => $anchor
                    ]
                ]
            ])
        ]) extends PluginTriggerLaunched {
            public array $sendingData = [];
            protected function getSendingData($response, $currentEventAnchor)
            {
                return $this->sendingData = [
                    EventTriggerLaunched::FIELD__TRIGGER_NAME => $this->getTrigger()->getName(),
                    EventTriggerLaunched::FIELD__TRIGGER_RESPONSE => $response->__toArray(),
                    EventTriggerLaunched::FIELD__ANCHOR => $this->getActivity()
                        ->getParameterValue('anchor')
                        ->__toArray(),
                    'anchor' => $currentEventAnchor->getId(),
                    'version' => '2.0',
                    'df_version' => getenv('DF__VERSION'),
                    'id' => 'Uuid::uuid6()->toString()'
                ];
            }
        };

        $plugin($triggerResponse);

        $this->assertEquals([
            EventTriggerLaunched::FIELD__TRIGGER_NAME => $trigger->getName(),
            EventTriggerLaunched::FIELD__TRIGGER_RESPONSE => $triggerResponse->__toArray(),
            EventTriggerLaunched::FIELD__ANCHOR => $anchor->__toArray(),
            'anchor' => $currentEventAnchor->getId(),
            'version' => '2.0',
            'df_version' => getenv('DF__VERSION'),
            'id' => 'Uuid::uuid6()->toString()'
        ], $plugin->sendingData);
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \extas\components\exceptions\MissedOrUnknown
     */
    public function testSuccessGetSendingData()
    {
        putenv('DF__APP_NAME=deflou');
        putenv('DF__VERSION=3.0');

        $this->createWithSnuffRepo('deflouApplicationRepository', new Application([
            Application::FIELD__NAME => 'deflou',
            Application::FIELD__SAMPLE_NAME => 'deflou',
        ]));
        $this->createWithSnuffRepo('deflouActivityRepository', new Activity([
            Activity::FIELD__NAME => 'trigger.launched_',
            Activity::FIELD__SAMPLE_NAME => 'trigger.launched',
            Activity::FIELD__APPLICATION_NAME => 'deflou',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT
        ]));
        $this->createWithSnuffRepo('playerRepository', new Player([
            Player::FIELD__NAME => 'test_player'
        ]));
        $this->createWithSnuffRepo('anchorRepository', new Anchor([
            Anchor::FIELD__ID => 'test_anchor',
            Anchor::FIELD__EVENT_NAME => 'trigger.launched_',
            Anchor::FIELD__PLAYER_NAME => 'test_player',
            Anchor::FIELD__TRIGGER_NAME => 'test'
        ]));

        $trigger = new Trigger([
            Trigger::FIELD__NAME => 'test',
            Trigger::FIELD__PLAYER_NAME => 'test_player'
        ]);

        $triggerResponse = new TriggerResponse();
        $anchor = new Anchor([
            Anchor::FIELD__ID => 'test'
        ]);

        $plugin = new class ([
            PluginTriggerLaunched::FIELD__TRIGGER => $trigger,
            PluginTriggerLaunched::FIELD__ACTIVITY => new Activity([
                Activity::FIELD__PARAMETERS => [
                    'anchor' => [
                        'name' => 'anchor',
                        'value' => $anchor
                    ]
                ]
            ])
        ]) extends PluginTriggerLaunched{
            public function __invoke(ITriggerResponse $response): void
            {
                parent::__invoke($response);
                throw new \Exception('Is ok');
            }
        };

        $this->expectExceptionMessage('Is ok');
        $plugin($triggerResponse);
    }
}
