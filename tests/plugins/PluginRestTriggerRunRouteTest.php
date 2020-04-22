<?php
namespace tests\plugins;

use deflou\components\plugins\api\PluginRestTriggerRunRoute;
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
}
