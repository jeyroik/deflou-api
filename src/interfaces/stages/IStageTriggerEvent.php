<?php
namespace deflou\interfaces\stages;

use deflou\interfaces\applications\activities\IActivity;
use extas\interfaces\http\IHasHttpIO;
use extas\interfaces\jsonrpc\IHasJsonRpcRequest;
use extas\interfaces\jsonrpc\IHasJsonRpcResponse;

/**
 * Interface IStageTriggerEvent
 *
 * Event determination.
 *
 * @package deflou\interfaces\stages
 * @author jeyroik <jeyroik@gmail.com>
 */
interface IStageTriggerEvent extends IHasHttpIO, IHasJsonRpcRequest, IHasJsonRpcResponse
{
    public const NAME = 'deflou.trigger.event';

    /**
     * @param IActivity $event
     */
    public function __invoke(IActivity &$event): void;
}
