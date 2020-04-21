<?php
namespace tests\jsonrpc;

use deflou\components\applications\activities\actions\ActionNothing;
use deflou\components\applications\activities\Activity;
use deflou\components\applications\activities\ActivityRepository;
use deflou\components\applications\activities\ActivitySample;
use deflou\components\applications\activities\ActivitySampleRepository;
use deflou\components\applications\activities\events\EventNothing;
use deflou\components\applications\activities\events\EventTriggerLaunched;
use deflou\components\applications\anchors\Anchor;
use deflou\components\applications\anchors\AnchorRepository;
use deflou\components\applications\Application;
use deflou\components\applications\ApplicationRepository;
use deflou\components\applications\ApplicationSample;
use deflou\components\applications\ApplicationSampleRepository;
use deflou\components\jsonrpc\operations\CreateEvent;
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
use extas\components\jsonrpc\Request;
use extas\components\jsonrpc\Response;
use extas\components\players\Player;
use extas\components\players\PlayerRepository;
use extas\components\plugins\Plugin;
use extas\components\plugins\PluginRepository;
use extas\components\servers\requests\ServerRequest;
use extas\components\servers\responses\ServerResponse;
use extas\components\SystemContainer;
use extas\interfaces\jsonrpc\IRequest;
use extas\interfaces\jsonrpc\IResponse;
use extas\interfaces\jsonrpc\operations\IOperationDispatcher;
use extas\interfaces\parameters\IParameter;
use extas\interfaces\players\IPlayerRepository;
use extas\interfaces\repositories\IRepository;
use extas\interfaces\samples\parameters\ISampleParameter;
use PHPUnit\Framework\TestCase;
use Slim\Http\Response as PsrResponse;
use tests\ActionWithException;
use tests\PluginEnrich;
use tests\PluginEnrichWithException;
use tests\PluginLaunchedWithException;

/**
 * Class CreateEventTest
 *
 * @package tests\jsonrpc
 * @author jeyroik@gmail.com
 */
class CreateEventTest extends TestCase
{
    protected ?IRepository $playerRepo = null;
    protected ?IRepository $anchorRepo = null;
    protected ?IRepository $appRepo = null;
    protected ?IRepository $activityRepo = null;
    protected ?IRepository $triggerRepo = null;
    protected ?IRepository $pluginRepo = null;
    protected ?IRepository $triggersResponsesRepo = null;

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
        $this->pluginRepo = new PluginRepository();
        $this->triggerRepo = new TriggerRepository();

        SystemContainer::addItem(
            IAnchorRepository::class,
            AnchorRepository::class
        );
        SystemContainer::addItem(
            IApplicationRepository::class,
            ApplicationRepository::class
        );
        SystemContainer::addItem(
            IApplicationSampleRepository::class,
            ApplicationSampleRepository::class
        );
        SystemContainer::addItem(
            IActivityRepository::class,
            ActivityRepository::class
        );
        SystemContainer::addItem(
            IActivitySampleRepository::class,
            ActivitySampleRepository::class
        );
        SystemContainer::addItem(
            ITriggerRepository::class,
            TriggerRepository::class
        );
        SystemContainer::addItem(
            IPlayerRepository::class,
            PlayerRepository::class
        );
        SystemContainer::addItem(
            ITriggerResponseRepository::class,
            TriggerResponseRepository::class
        );
    }

    public function tearDown(): void
    {
        $this->anchorRepo->delete([Anchor::FIELD__ID => 'test']);
        $this->appRepo->delete([Application::FIELD__SAMPLE_NAME => 'test_app']);
        $this->activityRepo->delete([Activity::FIELD__NAME => ['test_event', 'test_action']]);
        $this->playerRepo->delete([Player::FIELD__NAME => 'test']);
        $this->triggerRepo->delete([ITrigger::FIELD__NAME => 'test']);
        $this->triggersResponsesRepo->delete([TriggerResponse::FIELD__PLAYER_NAME => 'test_player']);
        $this->pluginRepo->delete([Plugin::FIELD__STAGE => 'extas.triggers_responses.create.before']);
    }

    protected function getServerRequest(array $params = [])
    {
        $params = empty($params)
            ? [
                'data' => [
                    CreateEvent::REQUEST__ANCHOR => 'test'
                ]
            ]
            : $params;

        return new ServerRequest([
            ServerRequest::FIELD__PARAMETERS => [
                [
                    IParameter::FIELD__NAME => IRequest::SUBJECT,
                    IParameter::FIELD__VALUE => new Request([
                        IRequest::FIELD__PARAMS => $params
                    ])
                ]
            ]
        ]);
    }

    protected function getServerResponse()
    {
        return new ServerResponse([
            ServerResponse::FIELD__PARAMETERS => [
                [
                    IParameter::FIELD__NAME => IResponse::SUBJECT,
                    IParameter::FIELD__VALUE => new Response([
                        Response::FIELD__RESPONSE => new PsrResponse()
                    ])
                ]
            ]
        ]);
    }

    /**
     * @param ServerResponse $serverResponse
     * @return IResponse
     */
    protected function getJsonRpcResponse(ServerResponse $serverResponse)
    {
        return $serverResponse->getParameter(IResponse::SUBJECT)->getValue();
    }

    /**
     * @param IResponse $response
     * @return array
     */
    protected function decodeRpcResponse(IResponse $response): array
    {
        return json_decode($response->getPsrResponse()->getBody(), true);
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
     * @param IResponse $jsonRpcResponse
     * @return array
     */
    protected function getResponseData(IResponse $jsonRpcResponse): array
    {
        return [
            IResponse::RESPONSE__ID => $jsonRpcResponse->getData()[IRequest::FIELD__ID] ?? '',
            IResponse::RESPONSE__VERSION => IResponse::VERSION_CURRENT
        ];
    }

    /**
     * @return IOperationDispatcher
     */
    protected function getOperation(): IOperationDispatcher
    {
        return new class () extends CreateEvent {
            protected function notApplicableTrigger(ITrigger $trigger, IActivity $event): void
            {
                throw new \Exception('Not applicable trigger "' . $trigger->getName() . '"');
            }
        };
    }

    public function testMissedAnchor()
    {
        $operation = new CreateEvent();
        $serverRequest = $this->getServerRequest([
            'data' => []
        ]);
        $serverResponse = $this->getServerResponse();
        $operation($serverRequest, $serverResponse);

        $jsonRpcResponse = $this->getJsonRpcResponse($serverResponse);
        $this->assertTrue($jsonRpcResponse->hasError());

        $response = $this->getResponseData($jsonRpcResponse);
        $response[IResponse::RESPONSE__ERROR] = $this->getError(400, 'Unknown anchor ""');
        $this->assertEquals($response, $this->decodeRpcResponse($jsonRpcResponse));
    }

    public function testUnknownAnchor()
    {
        $operation = new CreateEvent();
        $serverRequest = $this->getServerRequest([
            'data' => [
                CreateEvent::REQUEST__ANCHOR => 'unknown'
            ]
        ]);
        $serverResponse = $this->getServerResponse();
        $operation($serverRequest, $serverResponse);

        $jsonRpcResponse = $this->getJsonRpcResponse($serverResponse);
        $this->assertTrue($jsonRpcResponse->hasError());

        $response = $this->getResponseData($jsonRpcResponse);
        $response[IResponse::RESPONSE__ERROR] = $this->getError(400, 'Unknown anchor "unknown"');
        $this->assertEquals($response, $this->decodeRpcResponse($jsonRpcResponse));
    }

    public function testUnknownEvent()
    {
        $operation = new CreateEvent();
        $serverRequest = $this->getServerRequest();
        $serverResponse = $this->getServerResponse();

        $this->anchorRepo->create(new Anchor([
            Anchor::FIELD__ID => 'test',
            Anchor::FIELD__EVENT_NAME => 'unknown'
        ]));

        $operation($serverRequest, $serverResponse);

        $jsonRpcResponse = $this->getJsonRpcResponse($serverResponse);
        $this->assertTrue($jsonRpcResponse->hasError());

        $response = $this->getResponseData($jsonRpcResponse);
        $response[IResponse::RESPONSE__ERROR] = $this->getError(400, 'Missed event "unknown"');
        $this->assertEquals($response, $this->decodeRpcResponse($jsonRpcResponse));
    }

    public function testRunEventDispatcher()
    {
        $operation = new CreateEvent();
        $serverRequest = $this->getServerRequest([
            'data' => [
                CreateEvent::REQUEST__ANCHOR => 'test',
                EventTriggerLaunched::FIELD__TRIGGER_RESPONSE => [],
                EventTriggerLaunched::FIELD__TRIGGER_NAME => ''
            ]
        ]);
        $serverResponse = $this->getServerResponse();

        $this->anchorRepo->create(new Anchor([
            Anchor::FIELD__ID => 'test',
            Anchor::FIELD__EVENT_NAME => 'test_event'
        ]));

        $this->activityRepo->create(new Activity([
            Activity::FIELD__NAME => 'test_event',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT,
            Activity::FIELD__CLASS => EventTriggerLaunched::class
        ]));

        $operation($serverRequest, $serverResponse);

        $jsonRpcResponse = $this->getJsonRpcResponse($serverResponse);
        $this->assertTrue($jsonRpcResponse->hasError());

        $response = $this->getResponseData($jsonRpcResponse);
        $response[IResponse::RESPONSE__ERROR] = $this->getError(
            400,
            'Missed "' . EventTriggerLaunched::FIELD__ANCHOR . '" parameter'
        );
        $this->assertEquals($response, $this->decodeRpcResponse($jsonRpcResponse));
    }

    public function testAnchorGeneralType()
    {
        $operation = $this->getOperation();
        $serverRequest = $this->getServerRequest();
        $serverResponse = $this->getServerResponse();

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

        $operation($serverRequest, $serverResponse);

        $jsonRpcResponse = $this->getJsonRpcResponse($serverResponse);
        $this->assertTrue($jsonRpcResponse->hasError());

        $response = $this->getResponseData($jsonRpcResponse);
        $response[IResponse::RESPONSE__ERROR] = $this->getError(
            400,
            'Not applicable trigger "test"'
        );
        $this->assertEquals($response, $this->decodeRpcResponse($jsonRpcResponse));
    }

    public function testAnchorPlayerType()
    {
        $operation = $this->getOperation();
        $serverRequest = $this->getServerRequest();
        $serverResponse = $this->getServerResponse();

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

        $operation($serverRequest, $serverResponse);

        $jsonRpcResponse = $this->getJsonRpcResponse($serverResponse);
        $this->assertTrue($jsonRpcResponse->hasError());

        $response = $this->getResponseData($jsonRpcResponse);
        $response[IResponse::RESPONSE__ERROR] = $this->getError(
            400,
            'Not applicable trigger "test"'
        );
        $this->assertEquals($response, $this->decodeRpcResponse($jsonRpcResponse));
    }

    public function testAnchorTriggerType()
    {
        $operation = $this->getOperation();
        $serverRequest = $this->getServerRequest();
        $serverResponse = $this->getServerResponse();

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

        $operation($serverRequest, $serverResponse);

        $jsonRpcResponse = $this->getJsonRpcResponse($serverResponse);
        $this->assertTrue($jsonRpcResponse->hasError());

        $response = $this->getResponseData($jsonRpcResponse);
        $response[IResponse::RESPONSE__ERROR] = $this->getError(
            400,
            'Not applicable trigger "test"'
        );
        $this->assertEquals($response, $this->decodeRpcResponse($jsonRpcResponse));
    }

    public function testMissedAction()
    {
        $operation = $this->getOperation();
        $serverRequest = $this->getServerRequest();
        $serverResponse = $this->getServerResponse();

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

        $operation($serverRequest, $serverResponse);

        $jsonRpcResponse = $this->getJsonRpcResponse($serverResponse);
        $this->assertTrue($jsonRpcResponse->hasError());

        $response = $this->getResponseData($jsonRpcResponse);
        $response[IResponse::RESPONSE__ERROR] = $this->getError(
            400,
            'Missed action "unknown"'
        );
        $this->assertEquals($response, $this->decodeRpcResponse($jsonRpcResponse));
    }

    public function testEnrichTrigger()
    {
        $operation = $this->getOperation();
        $serverRequest = $this->getServerRequest();
        $serverResponse = $this->getServerResponse();

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

        $operation($serverRequest, $serverResponse);

        $jsonRpcResponse = $this->getJsonRpcResponse($serverResponse);
        $this->assertTrue($jsonRpcResponse->hasError());

        $response = $this->getResponseData($jsonRpcResponse);
        $response[IResponse::RESPONSE__ERROR] = $this->getError(
            400,
            PluginEnrichWithException::EXCEPTION__MESSAGE
        );
        $this->assertEquals($response, $this->decodeRpcResponse($jsonRpcResponse));
    }

    public function testActionDispatcher()
    {
        $operation = $this->getOperation();
        $serverRequest = $this->getServerRequest();
        $serverResponse = $this->getServerResponse();

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

        $this->pluginRepo->create(new Plugin([
            Plugin::FIELD__CLASS => PluginEnrich::class,
            Plugin::FIELD__STAGE => IStageDeFlouTriggerEnrich::NAME
        ]));

        $operation($serverRequest, $serverResponse);

        $jsonRpcResponse = $this->getJsonRpcResponse($serverResponse);
        $this->assertTrue($jsonRpcResponse->hasError());

        $response = $this->getResponseData($jsonRpcResponse);
        $response[IResponse::RESPONSE__ERROR] = $this->getError(
            400,
            ActionWithException::EXCEPTION__MESSAGE
        );
        $this->assertEquals($response, $this->decodeRpcResponse($jsonRpcResponse));
    }

    public function testActionLaunchedStage()
    {
        $operation = $this->getOperation();
        $serverRequest = $this->getServerRequest();
        $serverResponse = $this->getServerResponse();

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

        $this->pluginRepo->create(new Plugin([
            Plugin::FIELD__CLASS => PluginLaunchedWithException::class,
            Plugin::FIELD__STAGE => IStageDeflouTriggerLaunched::NAME
        ]));

        $operation($serverRequest, $serverResponse);

        $jsonRpcResponse = $this->getJsonRpcResponse($serverResponse);
        $this->assertTrue($jsonRpcResponse->hasError());

        $response = $this->getResponseData($jsonRpcResponse);
        $response[IResponse::RESPONSE__ERROR] = $this->getError(
            400,
            ActionWithException::EXCEPTION__MESSAGE
        );
        $this->assertEquals($response, $this->decodeRpcResponse($jsonRpcResponse));
    }

    public function testEverythingIsOk()
    {
        $operation = $this->getOperation();
        $serverRequest = $this->getServerRequest();
        $serverResponse = $this->getServerResponse();

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

        $operation($serverRequest, $serverResponse);

        $jsonRpcResponse = $this->getJsonRpcResponse($serverResponse);
        $this->assertFalse($jsonRpcResponse->hasError());

        $response = $this->getResponseData($jsonRpcResponse);
        $response[IResponse::RESPONSE__RESULT] = [];
        $this->assertEquals($response, $this->decodeRpcResponse($jsonRpcResponse));
    }
}
