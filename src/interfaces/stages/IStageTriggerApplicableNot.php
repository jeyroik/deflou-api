<?php
namespace deflou\interfaces\stages;

use deflou\interfaces\applications\activities\IHasActivity;
use deflou\interfaces\triggers\IHasTriggerObject;
use extas\interfaces\http\IHasHttpIO;
use extas\interfaces\jsonrpc\IHasJsonRpcRequest;
use extas\interfaces\jsonrpc\IHasJsonRpcResponse;

/**
 * Interface IStageTriggerApplicableNot
 *
 * Current trigger is not applicable for the current event fields/parameters.
 *
 * @package deflou\interfaces\stages
 * @author jeyroik <jeyroik@gmail.com>
 */
interface IStageTriggerApplicableNot extends
    IHasHttpIO,
    IHasJsonRpcRequest,
    IHasJsonRpcResponse,
    IHasActivity,
    IHasTriggerObject
{
    public const NAME = 'deflou.trigger.applicable.not';

    public function __invoke(): void;
}
