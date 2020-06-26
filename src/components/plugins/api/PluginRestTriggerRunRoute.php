<?php
namespace deflou\components\plugins\api;

use extas\components\jsonrpc\Router;
use extas\components\plugins\Plugin;
use extas\interfaces\stages\IStageJsonRpcInit;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\App;

/**
 * Class PluginRestTriggerRunRoute
 *
 * @package deflou\components\plugins\api
 * @author jeyroik <jeyroik@gmail.com>
 */
class PluginRestTriggerRunRoute extends Plugin implements IStageJsonRpcInit
{
    public function __invoke(App &$app): void
    {
        $app->any('/new/event/{anchor}/', function (Request $request, Response $response, array $args) {
            $router = new Router([
                Router::FIELD__PSR_REQUEST => $request,
                Router::FIELD__PSR_RESPONSE => $response,
                Router::FIELD__ARGUMENTS => $args
            ]);

            return $router->dispatch();
        });
    }
}
