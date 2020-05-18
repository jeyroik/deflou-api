<?php
namespace tests\jsonrpc;

use deflou\components\applications\activities\actions\ActionNothing;
use deflou\components\applications\activities\Activity;
use deflou\components\applications\activities\ActivityRepository;
use deflou\components\applications\activities\ActivitySampleRepository;
use deflou\components\applications\activities\events\EventNothing;
use deflou\components\applications\activities\events\EventTriggerLaunched;
use deflou\components\applications\anchors\Anchor;
use deflou\components\applications\anchors\AnchorRepository;
use deflou\components\applications\Application;
use deflou\components\applications\ApplicationRepository;
use deflou\components\applications\ApplicationSampleRepository;
use deflou\components\jsonrpc\operations\CreateTriggerEvent;
use deflou\components\triggers\Trigger;
use deflou\components\triggers\TriggerRepository;
use deflou\components\triggers\TriggerResponse;
use deflou\components\triggers\TriggerResponseRepository;
use deflou\interfaces\applications\activities\IActivity;
use deflou\interfaces\applications\activities\IActivityRepository;
use deflou\interfaces\applications\activities\IActivitySampleRepository;
use deflou\interfaces\applications\anchors\IAnchorRepository;
use deflou\interfaces\applications\IApplicationRepository;
use deflou\interfaces\applications\IApplicationSampleRepository;
use deflou\interfaces\stages\IStageDeFlouTriggerEnrich;
use deflou\interfaces\stages\IStageDeflouTriggerLaunched;
use deflou\interfaces\triggers\ITrigger;
use deflou\interfaces\triggers\ITriggerRepository;
use deflou\interfaces\triggers\ITriggerResponseRepository;
use Dotenv\Dotenv;
use extas\components\conditions\Condition;
use extas\components\conditions\ConditionEqual;
use extas\components\conditions\ConditionRepository;
use extas\components\extensions\Extension;
use extas\components\extensions\ExtensionHasCondition;
use extas\components\extensions\ExtensionRepository;
use extas\components\extensions\TSnuffExtensions;
use extas\components\http\TSnuffHttp;
use extas\components\players\Player;
use extas\components\players\PlayerRepository;
use extas\components\plugins\Plugin;
use extas\components\plugins\PluginRepository;
use extas\interfaces\conditions\ICondition;
use extas\interfaces\conditions\IConditionRepository;
use extas\interfaces\conditions\IHasCondition;
use extas\interfaces\extensions\IExtensionHasCondition;
use extas\interfaces\jsonrpc\IResponse;
use extas\interfaces\jsonrpc\operations\IOperationDispatcher;
use extas\interfaces\players\IPlayerRepository;
use extas\interfaces\repositories\IRepository;
use extas\interfaces\samples\parameters\ISampleParameter;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use tests\ActionWithException;
use tests\PluginEnrichWithException;
use tests\PluginLaunchedWithException;

/**
 * Class CreateEventTest
 *
 * @package tests\jsonrpc
 * @author jeyroik@gmail.com
 */
class CreateTriggerEventTest extends TestCase
{
    use TSnuffHttp;
    use TSnuffExtensions;

    protected ?IRepository $playerRepo = null;
    protected ?IRepository $anchorRepo = null;
    protected ?IRepository $appRepo = null;
    protected ?IRepository $activityRepo = null;
    protected ?IRepository $triggerRepo = null;
    protected ?IRepository $pluginRepo = null;
    protected ?IRepository $triggersResponsesRepo = null;
    protected ?IRepository $condRepo = null;
    protected ?IRepository $extRepo = null;

    protected function setUp(): void
    {
        parent::setUp();
        $env = Dotenv::create(getcwd() . '/tests/');
        $env->load();

        $this->anchorRepo = new AnchorRepository();
        $this->appRepo = new ApplicationRepository();
        $this->activityRepo = new ActivityRepository();
        $this->playerRepo = new PlayerRepository();
        $this->triggersResponsesRepo = new TriggerResponseRepository();
        $this->triggerRepo = new TriggerRepository();
        $this->condRepo = new ConditionRepository();
        $this->extRepo = new ExtensionRepository();
        $this->pluginRepo = new class extends PluginRepository {
            public function reload()
            {
                parent::$stagesWithPlugins = [];
            }
        };

        $this->addReposForExt([
            IConditionRepository::class => ConditionRepository::class,
            IAnchorRepository::class => AnchorRepository::class,
            'anchorRepository' => AnchorRepository::class,
            IApplicationRepository::class => ApplicationRepository::class,
            IApplicationSampleRepository::class => ApplicationSampleRepository::class,
            IActivityRepository::class => ActivityRepository::class,
            IActivitySampleRepository::class => ActivitySampleRepository::class,
            ITriggerRepository::class => TriggerRepository::class,
            'triggerRepository' => TriggerRepository::class,
            IPlayerRepository::class => PlayerRepository::class,
            ITriggerResponseRepository::class => TriggerResponseRepository::class
        ]);
        $this->createRepoExt([
            'anchorRepository', 'triggerRepository'
        ]);
    }

    public function tearDown(): void
    {
        $this->anchorRepo->delete([Anchor::FIELD__ID => 'test']);
        $this->appRepo->delete([Application::FIELD__SAMPLE_NAME => 'test_app']);
        $this->activityRepo->delete([Activity::FIELD__NAME => ['test_event', 'test_action']]);
        $this->playerRepo->delete([Player::FIELD__NAME => 'test']);
        $this->triggerRepo->delete([ITrigger::FIELD__NAME => 'test']);
        $this->triggersResponsesRepo->delete([TriggerResponse::FIELD__PLAYER_NAME => 'test_player']);
        $this->pluginRepo->delete([
            Plugin::FIELD__CLASS => [
                PluginEnrichWithException::class,
                PluginLaunchedWithException::class
            ]
        ]);
        $this->condRepo->delete([ICondition::FIELD__NAME => 'eq']);
        $this->extRepo->delete([Extension::FIELD__CLASS => ExtensionHasCondition::class]);
        $this->deleteSnuffExtensions();
    }

    /**
     * @param int $code
     * @param string $message
     * @param array $data
     * @return array
     */
    protected function getError(int $code, string $message, array $data = []): array
    {
        return [
            IResponse::RESPONSE__ERROR_CODE => $code,
            IResponse::RESPONSE__ERROR_DATA => $data,
            IResponse::RESPONSE__ERROR_MESSAGE => $message
        ];
    }

    /**
     * @return array
     */
    protected function getResponseMockData(): array
    {
        return [
            IResponse::RESPONSE__ID => '2f5d0719-5b82-4280-9b3b-10f23aff226b',
            IResponse::RESPONSE__VERSION => IResponse::VERSION_CURRENT
        ];
    }

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return IOperationDispatcher
     */
    protected function getOperation(RequestInterface $request, ResponseInterface $response): IOperationDispatcher
    {
        return new class ([
            CreateTriggerEvent::FIELD__PSR_REQUEST => $request,
            CreateTriggerEvent::FIELD__PSR_RESPONSE => $response
        ]) extends CreateTriggerEvent {
            protected function notApplicableTrigger(ITrigger $trigger, IActivity $event): void
            {
                throw new \Exception('Not applicable trigger "' . $trigger->getName() . '"');
            }
        };
    }

    public function testMissedAnchor()
    {
        $operation = new CreateTriggerEvent([
            CreateTriggerEvent::FIELD__PSR_REQUEST => $this->getPsrRequest('.missed.anchor'),
            CreateTriggerEvent::FIELD__PSR_RESPONSE => $this->getPsrResponse()
        ]);

        $this->responseHasError($operation, $this->getError(400, 'Unknown anchor ""'));
    }

    public function testUnknownAnchor()
    {
        $operation = new CreateTriggerEvent([
            CreateTriggerEvent::FIELD__PSR_REQUEST => $this->getPsrRequest('.unknown.anchor'),
            CreateTriggerEvent::FIELD__PSR_RESPONSE => $this->getPsrResponse()
        ]);

        $this->responseHasError($operation, $this->getError(400, 'Unknown anchor "unknown"'));
    }

    public function testUnknownEvent()
    {
        $operation = new CreateTriggerEvent([
            CreateTriggerEvent::FIELD__PSR_REQUEST => $this->getPsrRequest('.unknown.event'),
            CreateTriggerEvent::FIELD__PSR_RESPONSE => $this->getPsrResponse()
        ]);

        $this->anchorRepo->create(new Anchor([
            Anchor::FIELD__ID => 'test',
            Anchor::FIELD__EVENT_NAME => 'unknown'
        ]));

        $this->responseHasError($operation, $this->getError(400, 'Missed event "unknown"'));
    }

    public function testRunEventDispatcher()
    {
        $operation = new CreateTriggerEvent([
            CreateTriggerEvent::FIELD__PSR_REQUEST => $this->getPsrRequest('.missed.anchor.parameter'),
            CreateTriggerEvent::FIELD__PSR_RESPONSE => $this->getPsrResponse()
        ]);

        $this->anchorRepo->create(new Anchor([
            Anchor::FIELD__ID => 'test',
            Anchor::FIELD__EVENT_NAME => 'test_event'
        ]));

        $this->activityRepo->create(new Activity([
            Activity::FIELD__NAME => 'test_event',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT,
            Activity::FIELD__CLASS => EventTriggerLaunched::class
        ]));

        $this->responseHasError($operation, $this->getError(
            400,
            'Missed "' . EventTriggerLaunched::FIELD__ANCHOR . '" parameter'
        ));
    }

    public function testAnchorGeneralType()
    {
        $operation = $this->getOperation(
            $this->getPsrRequest('.not.applicable.trigger'),
            $this->getPsrResponse()
        );

        $this->anchorRepo->create(new Anchor([
            Anchor::FIELD__ID => 'test',
            Anchor::FIELD__EVENT_NAME => 'test_event',
            Anchor::FIELD__TYPE => Anchor::TYPE__GENERAL
        ]));

        $this->activityRepo->create(new Activity([
            Activity::FIELD__NAME => 'test_event',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT,
            Activity::FIELD__CLASS => EventNothing::class
        ]));

        $this->triggerRepo->create(new Trigger([
            Trigger::FIELD__NAME => 'test',
            Trigger::FIELD__EVENT_NAME => 'test_event',
            Trigger::FIELD__EVENT_PARAMETERS => [
                'test' => [
                    ISampleParameter::FIELD__NAME => 'test'
                ]
            ]
        ]));

        $this->responseHasError($operation, $this->getError(
            400,
            'Not applicable trigger "test"'
        ));
    }

    public function testAnchorPlayerType()
    {
        $operation = $this->getOperation(
            $this->getPsrRequest('.not.applicable.trigger'),
            $this->getPsrResponse()
        );
        $this->anchorRepo->create(new Anchor([
            Anchor::FIELD__ID => 'test',
            Anchor::FIELD__EVENT_NAME => 'test_event',
            Anchor::FIELD__PLAYER_NAME => 'test_player',
            Anchor::FIELD__TYPE => Anchor::TYPE__PLAYER
        ]));

        $this->activityRepo->create(new Activity([
            Activity::FIELD__NAME => 'test_event',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT,
            Activity::FIELD__CLASS => EventNothing::class
        ]));

        $this->triggerRepo->create(new Trigger([
            Trigger::FIELD__NAME => 'test',
            Trigger::FIELD__EVENT_NAME => 'test_event',
            Trigger::FIELD__PLAYER_NAME => 'test_player',
            Trigger::FIELD__EVENT_PARAMETERS => [
                'test' => [
                    ISampleParameter::FIELD__NAME => 'test'
                ]
            ]
        ]));

        $this->responseHasError($operation, $this->getError(
            400,
            'Not applicable trigger "test"'
        ));
    }

    public function testAnchorTriggerType()
    {
        $operation = $this->getOperation(
            $this->getPsrRequest('.not.applicable.trigger'),
            $this->getPsrResponse()
        );

        $this->anchorRepo->create(new Anchor([
            Anchor::FIELD__ID => 'test',
            Anchor::FIELD__EVENT_NAME => 'test_event',
            Anchor::FIELD__TRIGGER_NAME => 'test',
            Anchor::FIELD__TYPE => Anchor::TYPE__TRIGGER
        ]));

        $this->activityRepo->create(new Activity([
            Activity::FIELD__NAME => 'test_event',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT,
            Activity::FIELD__CLASS => EventNothing::class
        ]));

        $this->triggerRepo->create(new Trigger([
            Trigger::FIELD__NAME => 'test',
            Trigger::FIELD__EVENT_PARAMETERS => [
                'test' => [
                    ISampleParameter::FIELD__NAME => 'test'
                ]
            ]
        ]));

        $this->responseHasError($operation, $this->getError(
            400,
            'Not applicable trigger "test"'
        ));
    }

    public function testEventConditionFailed()
    {
        $operation = $this->getOperation(
            $this->getPsrRequest('.not.applicable.trigger'),
            $this->getPsrResponse()
        );

        $this->anchorRepo->create(new Anchor([
            Anchor::FIELD__ID => 'test',
            Anchor::FIELD__EVENT_NAME => 'test_event',
            Anchor::FIELD__TRIGGER_NAME => 'test',
            Anchor::FIELD__TYPE => Anchor::TYPE__TRIGGER
        ]));

        $this->activityRepo->create(new Activity([
            Activity::FIELD__NAME => 'test_event',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT,
            Activity::FIELD__CLASS => EventNothing::class
        ]));

        $this->triggerRepo->create(new Trigger([
            Trigger::FIELD__NAME => 'test',
            Trigger::FIELD__EVENT_PARAMETERS => [
                'test' => [
                    ISampleParameter::FIELD__NAME => 'test',
                    IHasCondition::FIELD__CONDITION => '=',
                    IHasCondition::FIELD__VALUE => 6
                ]
            ]
        ]));

        $this->condRepo->create(new Condition([
            Condition::FIELD__NAME => 'eq',
            Condition::FIELD__ALIASES => ['eq', '='],
            Condition::FIELD__CLASS => ConditionEqual::class
        ]));

        $this->extRepo->create(new Extension([
            Extension::FIELD__CLASS => ExtensionHasCondition::class,
            Extension::FIELD__INTERFACE => IExtensionHasCondition::class,
            Extension::FIELD__METHODS => [
                "isConditionTrue", "getCondition", "getConditionName", "setConditionName"
            ],
            Extension::FIELD__SUBJECT => 'extas.sample.parameter'
        ]));

        $this->responseHasError($operation, $this->getError(
            400,
            'Not applicable trigger "test"'
        ));
    }

    public function testMissedAction()
    {
        $operation = $this->getOperation(
            $this->getPsrRequest('.not.applicable.trigger'),
            $this->getPsrResponse()
        );

        $this->anchorRepo->create(new Anchor([
            Anchor::FIELD__ID => 'test',
            Anchor::FIELD__EVENT_NAME => 'test_event',
            Anchor::FIELD__TRIGGER_NAME => 'test',
            Anchor::FIELD__TYPE => Anchor::TYPE__TRIGGER
        ]));

        $this->activityRepo->create(new Activity([
            Activity::FIELD__NAME => 'test_event',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT,
            Activity::FIELD__CLASS => EventNothing::class
        ]));

        $this->triggerRepo->create(new Trigger([
            Trigger::FIELD__NAME => 'test',
            Trigger::FIELD__ACTION_NAME => 'unknown',
            Trigger::FIELD__EVENT_PARAMETERS => []
        ]));

        $this->responseHasError($operation, $this->getError(
            400,
            'Missed action "unknown"'
        ));
    }

    public function testEnrichTrigger()
    {
        $operation = $this->getOperation(
            $this->getPsrRequest('.not.applicable.trigger'),
            $this->getPsrResponse()
        );

        $this->anchorRepo->create(new Anchor([
            Anchor::FIELD__ID => 'test',
            Anchor::FIELD__EVENT_NAME => 'test_event',
            Anchor::FIELD__TRIGGER_NAME => 'test',
            Anchor::FIELD__TYPE => Anchor::TYPE__TRIGGER
        ]));

        $this->activityRepo->create(new Activity([
            Activity::FIELD__NAME => 'test_event',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT,
            Activity::FIELD__CLASS => EventNothing::class
        ]));

        $this->activityRepo->create(new Activity([
            Activity::FIELD__NAME => 'test_action',
            Activity::FIELD__TYPE => Activity::TYPE__ACTION,
            Activity::FIELD__CLASS => ActionNothing::class
        ]));

        $this->triggerRepo->create(new Trigger([
            Trigger::FIELD__NAME => 'test',
            Trigger::FIELD__ACTION_NAME => 'test_action',
            Trigger::FIELD__EVENT_PARAMETERS => []
        ]));

        $this->pluginRepo->create(new Plugin([
            Plugin::FIELD__CLASS => PluginEnrichWithException::class,
            Plugin::FIELD__STAGE => IStageDeFlouTriggerEnrich::NAME
        ]));

        $this->responseHasError($operation, $this->getError(
            400,
            PluginEnrichWithException::EXCEPTION__MESSAGE
        ));
    }

    public function testActionDispatcher()
    {
        $operation = $this->getOperation(
            $this->getPsrRequest('.not.applicable.trigger'),
            $this->getPsrResponse()
        );

        $this->anchorRepo->create(new Anchor([
            Anchor::FIELD__ID => 'test',
            Anchor::FIELD__EVENT_NAME => 'test_event',
            Anchor::FIELD__TRIGGER_NAME => 'test',
            Anchor::FIELD__TYPE => Anchor::TYPE__TRIGGER
        ]));

        $this->activityRepo->create(new Activity([
            Activity::FIELD__NAME => 'test_event',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT,
            Activity::FIELD__CLASS => EventNothing::class
        ]));

        $this->activityRepo->create(new Activity([
            Activity::FIELD__NAME => 'test_action',
            Activity::FIELD__TYPE => Activity::TYPE__ACTION,
            Activity::FIELD__CLASS => ActionWithException::class
        ]));

        $this->triggerRepo->create(new Trigger([
            Trigger::FIELD__NAME => 'test',
            Trigger::FIELD__ACTION_NAME => 'test_action',
            Trigger::FIELD__EVENT_PARAMETERS => []
        ]));

        $this->pluginRepo->reload();

        $this->responseHasError($operation, $this->getError(
            400,
            ActionWithException::EXCEPTION__MESSAGE
        ));
    }

    public function testActionLaunchedStage()
    {
        $operation = $this->getOperation(
            $this->getPsrRequest('.not.applicable.trigger'),
            $this->getPsrResponse()
        );

        $this->appRepo->create(new Application([
            Application::FIELD__NAME => 'test_app',
            Application::FIELD__SAMPLE_NAME => 'test'
        ]));

        $this->anchorRepo->create(new Anchor([
            Anchor::FIELD__ID => 'test',
            Anchor::FIELD__EVENT_NAME => 'test_event',
            Anchor::FIELD__TRIGGER_NAME => 'test',
            Anchor::FIELD__TYPE => Anchor::TYPE__TRIGGER
        ]));

        $this->activityRepo->create(new Activity([
            Activity::FIELD__NAME => 'test_event',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT,
            Activity::FIELD__APPLICATION_NAME => 'test_app',
            Activity::FIELD__CLASS => EventNothing::class
        ]));

        $this->activityRepo->create(new Activity([
            Activity::FIELD__NAME => 'test_action',
            Activity::FIELD__TYPE => Activity::TYPE__ACTION,
            Activity::FIELD__APPLICATION_NAME => 'test_app',
            Activity::FIELD__CLASS => ActionNothing::class
        ]));

        $this->triggerRepo->create(new Trigger([
            Trigger::FIELD__NAME => 'test',
            Trigger::FIELD__ACTION_NAME => 'test_action',
            Trigger::FIELD__EVENT_PARAMETERS => []
        ]));

        $this->pluginRepo->create(new Plugin([
            Plugin::FIELD__CLASS => PluginLaunchedWithException::class,
            Plugin::FIELD__STAGE => IStageDeflouTriggerLaunched::NAME
        ]));

        $this->pluginRepo->reload();
        $this->responseHasError($operation, $this->getError(
            400,
            PluginLaunchedWithException::EXCEPTION__MESSAGE
        ));
    }

    public function testEverythingIsOk()
    {
        $operation = $this->getOperation(
            $this->getPsrRequest('.applicable.trigger'),
            $this->getPsrResponse()
        );

        $this->appRepo->create(new Application([
            Application::FIELD__NAME => 'test_app',
            Application::FIELD__SAMPLE_NAME => 'test'
        ]));

        $this->anchorRepo->create(new Anchor([
            Anchor::FIELD__ID => 'test',
            Anchor::FIELD__EVENT_NAME => 'test_event',
            Anchor::FIELD__TRIGGER_NAME => 'test',
            Anchor::FIELD__TYPE => Anchor::TYPE__TRIGGER
        ]));

        $this->activityRepo->create(new Activity([
            Activity::FIELD__NAME => 'test_event',
            Activity::FIELD__APPLICATION_NAME => 'test_app',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT,
            Activity::FIELD__CLASS => EventNothing::class
        ]));

        $this->activityRepo->create(new Activity([
            Activity::FIELD__NAME => 'test_action',
            Activity::FIELD__APPLICATION_NAME => 'test_app',
            Activity::FIELD__TYPE => Activity::TYPE__ACTION,
            Activity::FIELD__CLASS => ActionNothing::class
        ]));

        $this->triggerRepo->create(new Trigger([
            Trigger::FIELD__NAME => 'test',
            Trigger::FIELD__ACTION_NAME => 'test_action',
            Trigger::FIELD__EVENT_PARAMETERS => [
                'test' => [
                    ISampleParameter::FIELD__NAME => 'test',
                    IHasCondition::FIELD__CONDITION => '=',
                    IHasCondition::FIELD__VALUE => 5
                ]
            ]
        ]));

        $this->condRepo->create(new Condition([
            Condition::FIELD__NAME => 'eq',
            Condition::FIELD__CLASS => ConditionEqual::class,
            Condition::FIELD__ALIASES => ['eq', '=']
        ]));

        $this->extRepo->create(new Extension([
            Extension::FIELD__CLASS => ExtensionHasCondition::class,
            Extension::FIELD__INTERFACE => IExtensionHasCondition::class,
            Extension::FIELD__METHODS => [
                "isConditionTrue", "getCondition", "getConditionName", "setConditionName"
            ],
            Extension::FIELD__SUBJECT => 'extas.sample.parameter'
        ]));

        $this->pluginRepo->reload();
        $response = $operation();

        $jsonRpcResponse = $this->getJsonRpcResponse($response);
        $this->assertFalse(isset($jsonRpcResponse[IResponse::RESPONSE__ERROR]));

        $response = $this->getResponseMockData();
        $response[IResponse::RESPONSE__RESULT] = [];
        $this->assertEquals($response, $jsonRpcResponse);
    }

    /**
     * @param IOperationDispatcher $operation
     * @param array $error
     */
    protected function responseHasError(IOperationDispatcher $operation, array $error): void
    {
        $response = $operation();
        $jsonRpcResponse = $this->getJsonRpcResponse($response);
        $this->assertTrue(isset($jsonRpcResponse[IResponse::RESPONSE__ERROR]));

        $response = $this->getResponseMockData();
        $response[IResponse::RESPONSE__ERROR] = $error;
        $this->assertEquals($response, $jsonRpcResponse);
    }
}
