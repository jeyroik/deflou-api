<?php
namespace deflou\components\plugins\api;

use extas\components\jsonrpc\App;
use extas\components\jsonrpc\Router;
use extas\components\plugins\Plugin;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use extas\interfaces\jsonrpc\IRequest;
use extas\components\jsonrpc\Request as JsonRpcRequest;

/**
 * Class PluginRestTriggerRunRoute
 *
 * @package deflou\components\plugins\api
 * @author jeyroik <jeyroik@gmail.com>
 */
class PluginRestTriggerRunRoute extends Plugin
{
    /**
     * @param App $app
     */
    public function __invoke(App &$app)
    {
        $app->any('/new/event/{anchor}/', function (Request $request, Response $response, array $args) {
            $router = new Router();
            $data = $_REQUEST;
            $jsonData = json_decode($request->getBody()->getContents(),true);
            $jsonData = $jsonData ?: [];
            $data = array_merge($data, $jsonData, $args);

            return $router->dispatch($request, $response, new JsonRpcRequest([
                IRequest::FIELD__METHOD => 'trigger.event.create',
                IRequest::FIELD__ID => '',
                IRequest::FIELD__PARAMS => [
                    IRequest::FIELD__PARAMS_DATA => $data
                ],
                IRequest::FIELD__PARAMS_FILTER => [],
            ]));
        });
    }
}
