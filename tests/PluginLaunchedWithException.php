<?php
namespace tests;

use deflou\components\applications\activities\THasActivity;
use deflou\components\triggers\THasTriggerObject;
use deflou\interfaces\stages\IStageTriggerLaunched;
use deflou\interfaces\triggers\ITriggerResponse;
use extas\components\http\THasHttpIO;
use extas\components\jsonrpc\THasJsonRpcRequest;
use extas\components\jsonrpc\THasJsonRpcResponse;
use extas\components\plugins\Plugin;

/**
 * Class PluginLaunchedWithException
 *
 * @package tests
 * @author jeyroik@gmail.com
 */
class PluginLaunchedWithException extends Plugin implements IStageTriggerLaunched
{
    public const EXCEPTION__MESSAGE = 'Worked launched';

    use THasHttpIO;
    use THasJsonRpcRequest;
    use THasJsonRpcResponse;
    use THasActivity;
    use THasTriggerObject;

    /**
     * @param ITriggerResponse $triggerResponse
     * @throws \Exception
     */
    public function __invoke(ITriggerResponse $triggerResponse): void
    {
        throw new \Exception(static::EXCEPTION__MESSAGE, 400);
    }
}
