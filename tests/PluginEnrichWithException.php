<?php
namespace tests;

use deflou\components\applications\activities\THasActivity;
use deflou\interfaces\stages\IStageTriggerEnrich;
use deflou\interfaces\triggers\ITrigger;
use extas\components\http\THasHttpIO;
use extas\components\jsonrpc\THasJsonRpcRequest;
use extas\components\jsonrpc\THasJsonRpcResponse;
use extas\components\plugins\Plugin;

/**
 * Class PluginEnrichSample
 *
 * @package tests
 * @author jeyroik@gmail.com
 */
class PluginEnrichWithException extends Plugin implements IStageTriggerEnrich
{
    public const EXCEPTION__MESSAGE = 'Worked enrich';

    use THasHttpIO;
    use THasJsonRpcRequest;
    use THasJsonRpcResponse;
    use THasActivity;

    /**
     * @param ITrigger $trigger
     * @throws \Exception
     */
    public function __invoke(ITrigger &$trigger): void
    {
        throw new \Exception(static::EXCEPTION__MESSAGE);
    }
}
