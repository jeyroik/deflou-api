<?php
namespace tests\plugins;

use deflou\components\applications\activities\actions\ActionNothing;
use deflou\components\applications\activities\Activity;
use deflou\components\applications\activities\ActivityRepository;
use deflou\components\applications\activities\events\EventNothing;
use deflou\components\applications\anchors\Anchor;
use deflou\components\applications\anchors\AnchorRepository;
use deflou\components\applications\Application;
use deflou\components\applications\ApplicationRepository;
use deflou\components\jsonrpc\operations\CreateTriggerEvent;
use deflou\components\plugins\api\PluginRestTriggerRunRoute;
use deflou\components\triggers\Trigger;
use deflou\components\triggers\TriggerRepository;
use deflou\components\triggers\TriggerResponse;
use deflou\components\triggers\TriggerResponseRepository;
use deflou\interfaces\applications\activities\IActivityRepository;
use deflou\interfaces\applications\anchors\IAnchorRepository;
use deflou\interfaces\triggers\ITrigger;
use deflou\interfaces\triggers\ITriggerRepository;
use deflou\interfaces\triggers\ITriggerResponseRepository;
use extas\components\extensions\Extension;
use extas\components\extensions\ExtensionHasCondition;
use extas\components\extensions\ExtensionRepository;
use extas\components\jsonrpc\operations\filters\FilterDefault;
use extas\components\jsonrpc\operations\Operation;
use extas\components\jsonrpc\operations\OperationRepository;
use extas\components\players\PlayerRepository;
use extas\components\plugins\PluginRepository;
use extas\components\SystemContainer;
use extas\interfaces\extensions\IExtensionHasCondition;
use extas\interfaces\jsonrpc\IResponse;
use extas\interfaces\jsonrpc\operations\IOperationRepository;
use extas\interfaces\players\IPlayerRepository;
use extas\interfaces\repositories\IRepository;
use PHPUnit\Framework\TestCase;
use extas\components\jsonrpc\App;

/**
 * Class PluginRestTriggerRunRouteTest
 *
 * @package tests\plugins
 * @author jeyroik@gmail.com
 */
class PluginRestTriggerRunRouteTest extends TestCase
{
    protected ?IRepository $anchorRepo = null;
    protected ?IRepository $activityRepo = null;
    protected ?IRepository $appRepo = null;
    protected ?IRepository $triggerRepo = null;
    protected ?IRepository $playerRepo = null;
    protected ?IRepository $triggersResponsesRepo = null;

    protected ?IRepository $opRepo = null;
    protected ?IRepository $pluginRepo = null;
    protected ?IRepository $extRepo = null;

    protected function setUp(): void
    {
        parent::setUp();
        $env = \Dotenv\Dotenv::create(getcwd() . '/tests/');
        $env->load();
        defined('APP__ROOT') || define('APP__ROOT', getcwd());

        $this->pluginRepo = new PluginRepository();
        $this->appRepo = new ApplicationRepository();
        $this->anchorRepo = new AnchorRepository();
        $this->activityRepo = new ActivityRepository();
        $this->extRepo = new ExtensionRepository();
        $this->triggerRepo = new TriggerRepository();
        $this->opRepo = new OperationRepository();
        $this->triggersResponsesRepo = new TriggerResponseRepository();

        SystemContainer::addItem(
            IAnchorRepository::class,
            AnchorRepository::class
        );

        SystemContainer::addItem(
            IActivityRepository::class,
            ActivityRepository::class
        );

        SystemContainer::addItem(
            ITriggerRepository::class,
            TriggerRepository::class
        );

        SystemContainer::addItem(
            IOperationRepository::class,
            OperationRepository::class
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
        $this->anchorRepo->delete([Anchor::FIELD__ID => 'test_anchor']);
        $this->appRepo->delete([Application::FIELD__SAMPLE_NAME => 'test_app']);
        $this->activityRepo->delete([Activity::FIELD__NAME => ['test_event', 'test_action']]);
        $this->triggerRepo->delete([ITrigger::FIELD__NAME => 'test']);
        $this->triggersResponsesRepo->delete([TriggerResponse::FIELD__TRIGGER_NAME => 'test']);
        $this->extRepo->delete([Extension::FIELD__CLASS => ExtensionHasCondition::class]);
        $this->opRepo->delete([Operation::FIELD__CLASS => CreateTriggerEvent::class]);
    }

    public function testAddRoute()
    {
        $app = new App();
        $plugin = new PluginRestTriggerRunRoute();
        $plugin($app);
        $container = $app->getContainer();
        $router = $container->get('router');
        $routes = $router->getRoutes();
        /**
         * - /api/jsonrpc
         * - /specs
         * - /new/event/{app_anchor}/
         */
        $this->assertCount(3, $routes);
    }

    public function testRestRoute()
    {
        $request = new \Slim\Http\Request(
            'POST',
            new \Slim\Http\Uri('http', 'localhost', 80, '/new/event/test_anchor/'),
            new \Slim\Http\Headers([
                'Content-type' => 'text/html'
            ]),
            [],
            [],
            new \Slim\Http\Stream(fopen('php://input', 'r'))
        );

        $response = new \Slim\Http\Response();

        $app = new App();
        $plugin = new PluginRestTriggerRunRoute();
        $plugin($app);
        $container = $app->getContainer();
        /**
         * @var \Slim\Router $router
         */
        $router = $container->get('router');
        $routes = $router->getRoutes();

        $this->anchorRepo->create(new Anchor([
            Anchor::FIELD__ID => 'test_anchor',
            Anchor::FIELD__EVENT_NAME => 'test_event',
            Anchor::FIELD__TRIGGER_NAME => 'test',
            Anchor::FIELD__TYPE => Anchor::TYPE__TRIGGER
        ]));

        $this->appRepo->create(new Application([
            Application::FIELD__NAME => 'test_app',
            Application::FIELD__SAMPLE_NAME => 'test'
        ]));

        $this->activityRepo->create(new Activity([
            Activity::FIELD__NAME => 'test_event',
            Activity::FIELD__TYPE => Activity::TYPE__EVENT,
            Activity::FIELD__APPLICATION_NAME => 'test_app',
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
            Trigger::FIELD__EVENT_NAME => 'test_event',
            Trigger::FIELD__ACTION_NAME => 'test_action',
            Trigger::FIELD__EVENT_PARAMETERS => [],
            Trigger::FIELD__ACTION_PARAMETERS => []
        ]));

        $this->extRepo->create(new Extension([
            Extension::FIELD__CLASS => ExtensionHasCondition::class,
            Extension::FIELD__INTERFACE => IExtensionHasCondition::class,
            Extension::FIELD__METHODS => [
                "isConditionTrue", "getCondition", "getConditionName", "setConditionName"
            ],
            Extension::FIELD__SUBJECT => 'extas.sample.parameter'
        ]));

        $this->opRepo->create(new Operation([
            Operation::FIELD__NAME => 'trigger.event.create',
            Operation::FIELD__CLASS => CreateTriggerEvent::class,
            Operation::FIELD__METHOD => 'create',
            Operation::FIELD__FILTER_CLASS => FilterDefault::class
        ]));

        foreach ($routes as $route) {
            if ($route->getPattern() == '/new/event/{app_anchor}/') {
                $dispatcher = $route->getCallable();
                $response = $dispatcher($request, $response, []);
            }
        }

        $this->assertEquals(200, $response->getStatusCode());

        $page = (string) $response->getBody();

        $this->assertEquals($this->getResponseData(), $page);
    }

    /**
     * @return string
     */
    protected function getResponseData(): string
    {
        return json_encode([
            IResponse::RESPONSE__ID => '',
            IResponse::RESPONSE__VERSION => IResponse::VERSION_CURRENT,
            IResponse::RESPONSE__RESULT => []
        ]);
    }
}
