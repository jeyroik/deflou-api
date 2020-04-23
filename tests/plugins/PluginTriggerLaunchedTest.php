<?php
namespace tests\plugins;

use deflou\components\applications\activities\Activity;
use deflou\components\applications\activities\ActivityRepository;
use deflou\components\applications\activities\events\EventTriggerLaunched;
use deflou\components\applications\anchors\Anchor;
use deflou\components\applications\anchors\AnchorRepository;
use deflou\components\applications\Application;
use deflou\components\applications\ApplicationRepository;
use deflou\components\plugins\triggers\PluginTriggerLaunched;
use deflou\components\triggers\Trigger;
use deflou\components\triggers\TriggerResponse;
use deflou\interfaces\applications\activities\IActivityRepository;
use deflou\interfaces\applications\anchors\IAnchorRepository;
use deflou\interfaces\applications\IApplicationRepository;
use extas\components\players\Player;
use extas\components\players\PlayerRepository;
use extas\components\protocols\ProtocolRepository;
use extas\components\SystemContainer;
use extas\interfaces\players\IPlayerRepository;
use extas\interfaces\protocols\IProtocolRepository;
use extas\interfaces\repositories\IRepository;
use extas\interfaces\samples\parameters\ISampleParameter;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\PromiseInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class PluginTriggerLaunchedTest
 *
 * @package tests\plugins
 * @author jeyroik@gmail.com
 */
class PluginTriggerLaunchedTest extends TestCase
{
    protected ?IRepository $activityRepo = null;
    protected ?IRepository $appRepo = null;
    protected ?IRepository $playerRepo = null;
    protected ?IRepository $anchorRepo = null;

    protected function setUp(): void
    {
        parent::setUp();
        $env = \Dotenv\Dotenv::create(getcwd() . '/tests/');
        $env->load();
        defined('APP__ROOT') || define('APP__ROOT', getcwd());

        $this->appRepo = new ApplicationRepository();
        $this->anchorRepo = new AnchorRepository();
        $this->activityRepo = new ActivityRepository();
        $this->playerRepo = new PlayerRepository();

        SystemContainer::addItem(
            IApplicationRepository::class,
            ApplicationRepository::class
        );

        SystemContainer::addItem(
            IAnchorRepository::class,
            AnchorRepository::class
        );

        SystemContainer::addItem(
            IActivityRepository::class,
            ActivityRepository::class
        );

        SystemContainer::addItem(
            IPlayerRepository::class,
            PlayerRepository::class
        );

        SystemContainer::addItem(
            IProtocolRepository::class,
            ProtocolRepository::class
        );
    }

    public function tearDown(): void
    {
        $this->anchorRepo->delete([Anchor::FIELD__ID => 'test_anchor']);
        $this->appRepo->delete([Application::FIELD__NAME => 'deflou']);
        $this->activityRepo->delete([Activity::FIELD__NAME => 'trigger.launched_']);
        $this->playerRepo->delete([Player::FIELD__NAME => 'test_player']);
    }

    public function testMissedCurrentInstanceApplicationName()
    {
        $plugin = new PluginTriggerLaunched();
        $this->expectExceptionMessage('Missed current instance application ()');
        $plugin(
            new Activity(),
            new Activity(),
            new Trigger(),
            new Anchor(),
            new TriggerResponse()
        );
    }

    public function testMissedEventForCurrentInstance()
    {
        $this->appRepo->create(new Application([
            Application::FIELD__NAME => 'deflou'
        ]));
        $plugin = new PluginTriggerLaunched();
        $this->expectExceptionMessage('Missed event trigger.launched for the current instance');
        putenv('DF__APP_NAME=deflou');
        $plugin(
            new Activity(),
            new Activity(),
            new Trigger(),
            new Anchor(),
            new TriggerResponse()
        );
    }

    public function testMissedEventAnchorForCurrentInstance()
    {
        $this->appRepo->create(new Application([
            Application::FIELD__NAME => 'deflou'
        ]));
        $this->activityRepo->create(new Activity([
            Activity::FIELD__NAME => 'trigger.launched_',
            Activity::FIELD__SAMPLE_NAME => 'trigger.launched',
            Activity::FIELD__APPLICATION_NAME => 'deflou',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT
        ]));
        $this->playerRepo->create(new Player([
            Player::FIELD__NAME => 'test_player'
        ]));

        $plugin = new PluginTriggerLaunched();
        $this->expectExceptionMessage('Missed anchor for a trigger.launched event');
        putenv('DF__APP_NAME=deflou');
        $plugin(
            new Activity(),
            new Activity(),
            new Trigger([
                Trigger::FIELD__PLAYER_NAME => 'test_player'
            ]),
            new Anchor(),
            new TriggerResponse()
        );
    }

    public function testFailSendEvent()
    {
        $this->appRepo->create(new Application([
            Application::FIELD__NAME => 'deflou',
            Application::FIELD__SAMPLE_NAME => 'deflou',
            Application::FIELD__PARAMETERS => [
                'host' => [
                    ISampleParameter::FIELD__NAME => 'host',
                    ISampleParameter::FIELD__VALUE => '*unknown.unk' . mt_rand(100, 999)
                ]
            ]
        ]));
        $this->activityRepo->create(new Activity([
            Activity::FIELD__NAME => 'trigger.launched_',
            Activity::FIELD__SAMPLE_NAME => 'trigger.launched',
            Activity::FIELD__APPLICATION_NAME => 'deflou',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT
        ]));
        $this->playerRepo->create(new Player([
            Player::FIELD__NAME => 'test_player'
        ]));
        $this->anchorRepo->create(new Anchor([
            Anchor::FIELD__ID => 'test_anchor',
            Anchor::FIELD__EVENT_NAME => 'trigger.launched_',
            Anchor::FIELD__PLAYER_NAME => 'test_player',
            Anchor::FIELD__TRIGGER_NAME => 'test'
        ]));

        $plugin = new class extends PluginTriggerLaunched {
            protected function getSendingData($trigger, $response, $anchor, $currentEventAnchor)
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
        $plugin(
            new Activity(),
            new Activity(),
            new Trigger([
                Trigger::FIELD__NAME => 'test',
                Trigger::FIELD__PLAYER_NAME => 'test_player'
            ]),
            new Anchor(),
            new TriggerResponse()
        );
    }

    public function testSuccessSending()
    {
        putenv('DF__APP_NAME=deflou');
        putenv('DF__VERSION=3.0');

        $this->appRepo->create(new Application([
            Application::FIELD__NAME => 'deflou',
            Application::FIELD__SAMPLE_NAME => 'deflou',
        ]));
        $this->activityRepo->create(new Activity([
            Activity::FIELD__NAME => 'trigger.launched_',
            Activity::FIELD__SAMPLE_NAME => 'trigger.launched',
            Activity::FIELD__APPLICATION_NAME => 'deflou',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT
        ]));
        $this->playerRepo->create(new Player([
            Player::FIELD__NAME => 'test_player'
        ]));
        $currentEventAnchor = $this->anchorRepo->create(new Anchor([
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

        $plugin = new class extends PluginTriggerLaunched {
            public array $sendingData = [];
            protected function getSendingData($trigger, $response, $anchor, $currentEventAnchor)
            {
                return $this->sendingData = [
                    EventTriggerLaunched::FIELD__TRIGGER_NAME => $trigger->getName(),
                    EventTriggerLaunched::FIELD__TRIGGER_RESPONSE => $response->__toArray(),
                    EventTriggerLaunched::FIELD__ANCHOR => $anchor->__toArray(),
                    'anchor' => $currentEventAnchor->getId(),
                    'version' => '2.0',
                    'df_version' => getenv('DF__VERSION'),
                    'id' => 'Uuid::uuid6()->toString()'
                ];
            }
        };

        putenv('DF__APP_NAME=deflou');
        $plugin(
            new Activity(),
            new Activity(),
            $trigger,
            $anchor,
            $triggerResponse
        );

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
}
