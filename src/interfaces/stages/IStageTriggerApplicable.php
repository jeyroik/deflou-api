<?php
namespace deflou\interfaces\stages;

use deflou\interfaces\applications\activities\IHasActivity;
use deflou\interfaces\triggers\IHasTriggerObject;
use extas\interfaces\http\IHasHttpIO;
use extas\interfaces\jsonrpc\IHasJsonRpcRequest;
use extas\interfaces\jsonrpc\IHasJsonRpcResponse;

/**
 * Interface IStageTriggerApplicable
 *
 * Checking is a trigger applicable for the current event fields/parameters.
 *
 * @package deflou\interfaces\stages
 * @author jeyroik <jeyroik@gmail.com>
 */
interface IStageTriggerApplicable extends
    IHasHttpIO,
    IHasJsonRpcRequest,
    IHasJsonRpcResponse,
    IHasActivity,
    IHasTriggerObject
{
    public const NAME = 'deflou.trigger.applicable';

    /**
     * @return bool
     */
    public function __invoke(): bool;
}
