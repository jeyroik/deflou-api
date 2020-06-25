<?php
namespace deflou\components\plugins\triggers;

use deflou\components\applications\activities\THasActivity;
use deflou\components\triggers\THasTriggerObject;
use deflou\interfaces\stages\IStageTriggerApplicableNot;
use extas\components\http\THasHttpIO;
use extas\components\jsonrpc\THasJsonRpcRequest;
use extas\components\jsonrpc\THasJsonRpcResponse;
use extas\components\plugins\Plugin;
use Psr\Log\LoggerInterface;

/**
 * Class PluginTriggerApplicableNot
 *
 * @method notice($message, array $context)
 *
 * @package deflou\components\plugins\triggers
 * @author jeyroik <jeyroik@gmail.com>
 */
class PluginTriggerApplicableNot extends Plugin implements IStageTriggerApplicableNot
{
    use THasHttpIO;
    use THasJsonRpcRequest;
    use THasJsonRpcResponse;
    use THasTriggerObject;
    use THasActivity;

    /**
     *
     */
    public function __invoke(): void
    {
        $this->notice('Can not apply trigger "' . $this->getTrigger()->getName() . '" to an event', [
            'trigger' => $this->getTrigger()->getName(),
            'event' => $this->getActivity()->getName(),
            'request' => $this->getJsonRpcRequest()->__toArray()
        ]);
    }
}
