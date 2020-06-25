<?php
namespace deflou\interfaces\stages;

use deflou\interfaces\applications\activities\IHasActivity;
use deflou\interfaces\triggers\ITrigger;
use extas\interfaces\http\IHasHttpIO;
use extas\interfaces\jsonrpc\IHasJsonRpcRequest;
use extas\interfaces\jsonrpc\IHasJsonRpcResponse;

/**
 * Interface IStageTriggerEnrich
 *
 * Enriching an applicable for the current event trigger with a data.
 *
 * @package deflou\interfaces\stages
 * @author jeyroik@gmail.com
 */
interface IStageTriggerEnrich extends IHasHttpIO, IHasJsonRpcRequest, IHasJsonRpcResponse, IHasActivity
{
    public const NAME = 'deflou.trigger.enrich';

    /**
     * @param ITrigger $trigger
     */
    public function __invoke(ITrigger &$trigger): void;
}
