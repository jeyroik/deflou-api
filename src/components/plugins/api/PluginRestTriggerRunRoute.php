<?php
namespace deflou\components\plugins\api;

use deflou\interfaces\applications\activities\IHasEvent;
use extas\components\jsonrpc\App;
use extas\components\jsonrpc\Router;
use extas\components\plugins\Plugin;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use extas\interfaces\jsonrpc\IRequest;

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
        $app->post('/new/event/{app_anchor}/', function (Request $request, Response $response, array $args) {
            $router = new Router();
            $data = $_REQUEST;
            $jsonData = json_decode($request->getBody()->getContents(),true);
            $data = array_merge($data, $jsonData, $args);

            $router->dispatch($request, $response, new \extas\components\jsonrpc\Request([
                IRequest::FIELD__METHOD => 'event.create',
                IRequest::FIELD__ID => '',
                IRequest::FIELD__PARAMS => [
                    IRequest::FIELD__PARAMS_DATA => $data
                ],
                IRequest::FIELD__PARAMS_FILTER => [],
            ]));
        });
    }
}
