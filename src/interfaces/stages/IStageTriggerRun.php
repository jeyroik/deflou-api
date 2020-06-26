<?php
namespace deflou\interfaces\stages;

use deflou\interfaces\applications\activities\IHasActivity;
use deflou\interfaces\triggers\IHasTriggerObject;
use deflou\interfaces\triggers\ITriggerResponse;
use extas\interfaces\http\IHasHttpIO;
use extas\interfaces\jsonrpc\IHasJsonRpcRequest;
use extas\interfaces\jsonrpc\IHasJsonRpcResponse;

/**
 * Interface IStageTriggerRun
 *
 * @package deflou\interfaces\stages
 * @author jeyroik <jeyroik@gmail.com>
 */
interface IStageTriggerRun extends
    IHasHttpIO,
    IHasJsonRpcRequest,
    IHasJsonRpcResponse,
    IHasTriggerObject,
    IHasActivity
{
    public const NAME = 'deflou.trigger.run';

    /**
     * @param ITriggerResponse $triggerResponse
     */
    public function __invoke(ITriggerResponse &$triggerResponse): void;
}
