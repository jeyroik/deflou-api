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
use deflou\components\plugins\triggers\PluginTriggerApplicable;
use deflou\components\plugins\triggers\PluginTriggerApplicableNot;
use deflou\components\plugins\triggers\PluginTriggerEnrich;
use deflou\components\plugins\triggers\PluginTriggerEvent;
use deflou\components\plugins\triggers\PluginTriggerLaunched;
use deflou\components\plugins\triggers\PluginTriggerRun;
use deflou\components\plugins\triggers\PluginTriggerTriggers;
use deflou\components\triggers\Trigger;
use deflou\components\triggers\TriggerRepository;
use deflou\components\triggers\TriggerResponseRepository;
use deflou\interfaces\stages\IStageTriggerApplicable;
use deflou\interfaces\stages\IStageTriggerApplicableNot;
use deflou\interfaces\stages\IStageTriggerEnrich;
use deflou\interfaces\stages\IStageTriggerEvent;
use deflou\interfaces\stages\IStageTriggerLaunched;

use deflou\interfaces\stages\IStageTriggerRun;
use deflou\interfaces\stages\IStageTriggerTriggers;
use extas\components\conditions\Condition;
use extas\components\conditions\ConditionEqual;
use extas\components\conditions\ConditionRepository;
use extas\components\extensions\Extension;
use extas\components\extensions\ExtensionHasCondition;
use extas\components\extensions\TSnuffExtensions;
use extas\components\http\TSnuffHttp;
use extas\components\players\PlayerRepository;
use extas\components\plugins\Plugin;
use extas\components\plugins\TSnuffPlugins;
use extas\components\repositories\TSnuffRepository;
use extas\interfaces\conditions\IHasCondition;
use extas\interfaces\extensions\IExtensionHasCondition;
use extas\interfaces\jsonrpc\IResponse;
use extas\interfaces\jsonrpc\operations\IOperationDispatcher;
use extas\interfaces\samples\parameters\ISampleParameter;

use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;
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
    use TSnuffPlugins;
    use TSnuffRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $env = Dotenv::create(getcwd() . '/tests/');
        $env->load();
        $this->registerSnuffRepos([
            'conditionRepository' => ConditionRepository::class,
            'anchorRepository' => AnchorRepository::class,
            'deflouApplicationRepository' => ApplicationRepository::class,
            'deflouApplicationSampleRepository' => ApplicationSampleRepository::class,
            'deflouActivityRepository' => ActivityRepository::class,
            'deflouActivitySampleRepository' => ActivitySampleRepository::class,
            'deflouTriggerRepository' => TriggerRepository::class,
            'playerRepository' => PlayerRepository::class,
            'deflouTriggerResponseRepository' => TriggerResponseRepository::class
        ]);
    }

    public function tearDown(): void
    {
        $this->unregisterSnuffRepos();
    }

    public function testMissedAnchor()
    {
        $operation = $this->getOperation('.missed.anchor');
        $this->responseHasError($operation, $this->getError(400, 'Missed or unknown anchor ""'));
    }

    public function testUnknownAnchor()
    {
        $operation = $this->getOperation('.unknown.anchor');
        $this->responseHasError($operation, $this->getError(400, 'Missed or unknown anchor "unknown"'));
    }

    /**
     * @throws \Exception
     */
    public function testUnknownEvent()
    {
        $operation = $this->getOperation('.unknown.event');

        $this->createWithSnuffRepo('anchorRepository', new Anchor([
            Anchor::FIELD__ID => 'test',
            Anchor::FIELD__EVENT_NAME => 'unknown'
        ]));

        $this->responseHasError($operation, $this->getError(400, 'Missed or unknown event "unknown"'));
    }

    /**
     * @throws \Exception
     */
    public function testRunEventDispatcher()
    {
        $operation = $this->getOperation('.missed.anchor.parameter');

        $this->createWithSnuffRepo('anchorRepository', new Anchor([
            Anchor::FIELD__ID => 'test',
            Anchor::FIELD__EVENT_NAME => 'test_event'
        ]));

        $this->createWithSnuffRepo('deflouActivityRepository', new Activity([
            Activity::FIELD__NAME => 'test_event',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT,
            Activity::FIELD__CLASS => EventTriggerLaunched::class
        ]));

        $this->responseHasError($operation, $this->getError(
            400,
            'Missed "' . EventTriggerLaunched::FIELD__ANCHOR . '" parameter'
        ));
    }

    /**
     * @throws \Exception
     */
    public function testAnchorGeneralType()
    {
        $operation = $this->getOperation('.not.applicable.trigger');

        $this->createWithSnuffRepo('anchorRepository', new Anchor([
            Anchor::FIELD__ID => 'test',
            Anchor::FIELD__EVENT_NAME => 'test_event',
            Anchor::FIELD__TYPE => Anchor::TYPE__GENERAL
        ]));

        $this->createWithSnuffRepo('deflouActivityRepository', new Activity([
            Activity::FIELD__NAME => 'test_event',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT,
            Activity::FIELD__CLASS => EventNothing::class
        ]));

        $this->createWithSnuffRepo('deflouTriggerRepository', new Trigger([
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

    /**
     * @throws \Exception
     */
    public function testAnchorPlayerType()
    {
        $operation = $this->getOperation('.not.applicable.trigger');
        $this->createWithSnuffRepo('anchorRepository', new Anchor([
            Anchor::FIELD__ID => 'test',
            Anchor::FIELD__EVENT_NAME => 'test_event',
            Anchor::FIELD__PLAYER_NAME => 'test_player',
            Anchor::FIELD__TYPE => Anchor::TYPE__PLAYER
        ]));

        $this->createWithSnuffRepo('deflouActivityRepository', new Activity([
            Activity::FIELD__NAME => 'test_event',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT,
            Activity::FIELD__CLASS => EventNothing::class
        ]));

        $this->createWithSnuffRepo('deflouTriggerRepository', new Trigger([
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

    /**
     * @throws \Exception
     */
    public function testAnchorTriggerType()
    {
        $operation = $this->getOperation('.not.applicable.trigger');

        $this->createWithSnuffRepo('anchorRepository', new Anchor([
            Anchor::FIELD__ID => 'test',
            Anchor::FIELD__EVENT_NAME => 'test_event',
            Anchor::FIELD__TRIGGER_NAME => 'test',
            Anchor::FIELD__TYPE => Anchor::TYPE__TRIGGER
        ]));

        $this->createWithSnuffRepo('deflouActivityRepository', new Activity([
            Activity::FIELD__NAME => 'test_event',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT,
            Activity::FIELD__CLASS => EventNothing::class
        ]));

        $this->createWithSnuffRepo('deflouTriggerRepository', new Trigger([
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

    /**
     * @throws \Exception
     */
    public function testEventConditionFailed()
    {
        $operation = $this->getOperation('.not.applicable.trigger');

        $this->createWithSnuffRepo('anchorRepository', new Anchor([
            Anchor::FIELD__ID => 'test',
            Anchor::FIELD__EVENT_NAME => 'test_event',
            Anchor::FIELD__TRIGGER_NAME => 'test',
            Anchor::FIELD__TYPE => Anchor::TYPE__TRIGGER
        ]));

        $this->createWithSnuffRepo('deflouActivityRepository', new Activity([
            Activity::FIELD__NAME => 'test_event',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT,
            Activity::FIELD__CLASS => EventNothing::class
        ]));

        $this->createWithSnuffRepo('deflouTriggerRepository', new Trigger([
            Trigger::FIELD__NAME => 'test',
            Trigger::FIELD__EVENT_PARAMETERS => [
                'test' => [
                    ISampleParameter::FIELD__NAME => 'test',
                    IHasCondition::FIELD__CONDITION => '=',
                    IHasCondition::FIELD__VALUE => 6
                ]
            ]
        ]));

        $this->createWithSnuffRepo('conditionRepository', new Condition([
            Condition::FIELD__NAME => 'eq',
            Condition::FIELD__ALIASES => ['eq', '='],
            Condition::FIELD__CLASS => ConditionEqual::class
        ]));

        $this->createWithSnuffRepo('extensionRepository', new Extension([
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

    /**
     * @throws \Exception
     */
    public function testMissedAction()
    {
        $operation = $this->getOperation('.not.applicable.trigger');

        $this->createWithSnuffRepo('anchorRepository', new Anchor([
            Anchor::FIELD__ID => 'test',
            Anchor::FIELD__EVENT_NAME => 'test_event',
            Anchor::FIELD__TRIGGER_NAME => 'test',
            Anchor::FIELD__TYPE => Anchor::TYPE__TRIGGER
        ]));

        $this->createWithSnuffRepo('deflouActivityRepository', new Activity([
            Activity::FIELD__NAME => 'test_event',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT,
            Activity::FIELD__CLASS => EventNothing::class
        ]));

        $this->createWithSnuffRepo('deflouTriggerRepository', new Trigger([
            Trigger::FIELD__NAME => 'test',
            Trigger::FIELD__ACTION_NAME => 'unknown',
            Trigger::FIELD__EVENT_PARAMETERS => []
        ]));

        $this->responseHasError($operation, $this->getError(
            400,
            'Missed or unknown action "unknown"'
        ));
    }

    /**
     * @throws \Exception
     */
    public function testEnrichTrigger()
    {
        $operation = $this->getOperation('.not.applicable.trigger');

        $this->createWithSnuffRepo('anchorRepository', new Anchor([
            Anchor::FIELD__ID => 'test',
            Anchor::FIELD__EVENT_NAME => 'test_event',
            Anchor::FIELD__TRIGGER_NAME => 'test',
            Anchor::FIELD__TYPE => Anchor::TYPE__TRIGGER
        ]));

        $this->createWithSnuffRepo('deflouActivityRepository', new Activity([
            Activity::FIELD__NAME => 'test_event',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT,
            Activity::FIELD__CLASS => EventNothing::class
        ]));

        $this->createWithSnuffRepo('deflouActivityRepository', new Activity([
            Activity::FIELD__NAME => 'test_action',
            Activity::FIELD__TYPE => Activity::TYPE__ACTION,
            Activity::FIELD__CLASS => ActionNothing::class
        ]));

        $this->createWithSnuffRepo('deflouTriggerRepository', new Trigger([
            Trigger::FIELD__NAME => 'test',
            Trigger::FIELD__ACTION_NAME => 'test_action',
            Trigger::FIELD__EVENT_PARAMETERS => []
        ]));

        $this->createWithSnuffRepo('pluginRepository', new Plugin([
            Plugin::FIELD__CLASS => PluginEnrichWithException::class,
            Plugin::FIELD__STAGE => IStageTriggerEnrich::NAME
        ]));

        $this->responseHasError($operation, $this->getError(
            400,
            PluginEnrichWithException::EXCEPTION__MESSAGE
        ));
    }

    /**
     * @throws \Exception
     */
    public function testActionDispatcher()
    {
        $operation = $this->getOperation('.not.applicable.trigger');

        $this->createWithSnuffRepo('anchorRepository', new Anchor([
            Anchor::FIELD__ID => 'test',
            Anchor::FIELD__EVENT_NAME => 'test_event',
            Anchor::FIELD__TRIGGER_NAME => 'test',
            Anchor::FIELD__TYPE => Anchor::TYPE__TRIGGER
        ]));

        $this->createWithSnuffRepo('deflouActivityRepository', new Activity([
            Activity::FIELD__NAME => 'test_event',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT,
            Activity::FIELD__CLASS => EventNothing::class
        ]));

        $this->createWithSnuffRepo('deflouActivityRepository', new Activity([
            Activity::FIELD__NAME => 'test_action',
            Activity::FIELD__TYPE => Activity::TYPE__ACTION,
            Activity::FIELD__CLASS => ActionWithException::class
        ]));

        $this->createWithSnuffRepo('deflouTriggerRepository', new Trigger([
            Trigger::FIELD__NAME => 'test',
            Trigger::FIELD__ACTION_NAME => 'test_action',
            Trigger::FIELD__EVENT_PARAMETERS => []
        ]));

        $this->responseHasError($operation, $this->getError(
            400,
            ActionWithException::EXCEPTION__MESSAGE
        ));
    }

    /**
     * @throws \Exception
     */
    public function testActionLaunchedStage()
    {
        $operation = $this->getOperation('.not.applicable.trigger');

        $this->createWithSnuffRepo('deflouApplicationRepository', new Application([
            Application::FIELD__NAME => 'test_app',
            Application::FIELD__SAMPLE_NAME => 'test'
        ]));

        $this->createWithSnuffRepo('anchorRepository', new Anchor([
            Anchor::FIELD__ID => 'test',
            Anchor::FIELD__EVENT_NAME => 'test_event',
            Anchor::FIELD__TRIGGER_NAME => 'test',
            Anchor::FIELD__TYPE => Anchor::TYPE__TRIGGER
        ]));

        $this->createWithSnuffRepo('deflouActivityRepository', new Activity([
            Activity::FIELD__NAME => 'test_event',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT,
            Activity::FIELD__APPLICATION_NAME => 'test_app',
            Activity::FIELD__CLASS => EventNothing::class
        ]));

        $this->createWithSnuffRepo('deflouActivityRepository', new Activity([
            Activity::FIELD__NAME => 'test_action',
            Activity::FIELD__TYPE => Activity::TYPE__ACTION,
            Activity::FIELD__APPLICATION_NAME => 'test_app',
            Activity::FIELD__CLASS => ActionNothing::class
        ]));

        $this->createWithSnuffRepo('deflouTriggerRepository', new Trigger([
            Trigger::FIELD__NAME => 'test',
            Trigger::FIELD__ACTION_NAME => 'test_action',
            Trigger::FIELD__EVENT_PARAMETERS => []
        ]));

        $this->createWithSnuffRepo('pluginRepository', new Plugin([
            Plugin::FIELD__CLASS => PluginLaunchedWithException::class,
            Plugin::FIELD__STAGE => IStageTriggerLaunched::NAME
        ]));

        $this->responseHasError($operation, $this->getError(
            400,
            PluginLaunchedWithException::EXCEPTION__MESSAGE
        ));
    }

    /**
     * @throws \Exception
     */
    public function testEverythingIsOk()
    {
        $operation = $this->getOperation('.applicable.trigger');

        $this->createWithSnuffRepo('deflouApplicationRepository', new Application([
            Application::FIELD__NAME => 'test_app',
            Application::FIELD__SAMPLE_NAME => 'test'
        ]));

        $this->createWithSnuffRepo('anchorRepository', new Anchor([
            Anchor::FIELD__ID => 'test',
            Anchor::FIELD__EVENT_NAME => 'test_event',
            Anchor::FIELD__TRIGGER_NAME => 'test',
            Anchor::FIELD__TYPE => Anchor::TYPE__TRIGGER
        ]));

        $this->createWithSnuffRepo('deflouActivityRepository', new Activity([
            Activity::FIELD__NAME => 'test_event',
            Activity::FIELD__APPLICATION_NAME => 'test_app',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT,
            Activity::FIELD__CLASS => EventNothing::class
        ]));

        $this->createWithSnuffRepo('deflouActivityRepository', new Activity([
            Activity::FIELD__NAME => 'test_action',
            Activity::FIELD__APPLICATION_NAME => 'test_app',
            Activity::FIELD__TYPE => Activity::TYPE__ACTION,
            Activity::FIELD__CLASS => ActionNothing::class
        ]));

        $this->createWithSnuffRepo('deflouTriggerRepository', new Trigger([
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

        $this->createWithSnuffRepo('conditionRepository', new Condition([
            Condition::FIELD__NAME => 'eq',
            Condition::FIELD__CLASS => ConditionEqual::class,
            Condition::FIELD__ALIASES => ['eq', '=']
        ]));

        $this->createWithSnuffRepo('extensionRepository', new Extension([
            Extension::FIELD__CLASS => ExtensionHasCondition::class,
            Extension::FIELD__INTERFACE => IExtensionHasCondition::class,
            Extension::FIELD__METHODS => [
                "isConditionTrue", "getCondition", "getConditionName", "setConditionName"
            ],
            Extension::FIELD__SUBJECT => 'extas.sample.parameter'
        ]));

        $response = $operation();

        $jsonRpcResponse = $this->getJsonRpcResponse($response);
        $this->assertFalse(isset($jsonRpcResponse[IResponse::RESPONSE__ERROR]));

        $response = $this->getResponseMockData();
        $response[IResponse::RESPONSE__RESULT] = [];
        $this->assertEquals($response, $jsonRpcResponse);
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
     * @param string $requestSuffix
     * @return IOperationDispatcher
     */
    protected function getOperation(string $requestSuffix = ''): IOperationDispatcher
    {
        $this->createSnuffPlugin(PluginTriggerApplicable::class, [IStageTriggerApplicable::NAME]);
        $this->createSnuffPlugin(PluginTriggerApplicableNot::class, [IStageTriggerApplicableNot::NAME]);
        $this->createSnuffPlugin(PluginTriggerEnrich::class, [IStageTriggerEnrich::NAME]);
        $this->createSnuffPlugin(PluginTriggerEvent::class, [IStageTriggerEvent::NAME]);
        $this->createSnuffPlugin(PluginTriggerLaunched::class, [IStageTriggerLaunched::NAME]);
        $this->createSnuffPlugin(PluginTriggerRun::class, [IStageTriggerRun::NAME]);
        $this->createSnuffPlugin(PluginTriggerTriggers::class, [IStageTriggerTriggers::NAME]);
        
        return new CreateTriggerEvent([
            CreateTriggerEvent::FIELD__PSR_REQUEST => $this->getPsrRequest($requestSuffix),
            CreateTriggerEvent::FIELD__PSR_RESPONSE => $this->getPsrResponse()
        ]);
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
