<?php
namespace deflou\interfaces\stages;

use deflou\interfaces\applications\activities\IHasActivity;
use extas\interfaces\http\IHasHttpIO;
use extas\interfaces\jsonrpc\IHasJsonRpcRequest;
use extas\interfaces\jsonrpc\IHasJsonRpcResponse;

/**
 * Interface IStageTriggerTriggers
 *
 * Getting triggers list for the current event.
 *
 * @package deflou\interfaces\stages
 * @author jeyroik <jeyroik@gmail.com>
 */
interface IStageTriggerTriggers extends IHasHttpIO, IHasJsonRpcRequest, IHasJsonRpcResponse, IHasActivity
{
    public const NAME = 'deflou.trigger.triggers';

    /**
     * @param array $triggers
     */
    public function __invoke(array &$triggers): void;
}
