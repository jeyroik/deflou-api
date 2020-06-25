<?php
namespace deflou\components\plugins\triggers;

use deflou\components\applications\activities\THasActivity;
use deflou\components\triggers\THasTriggerObject;
use deflou\interfaces\stages\IStageTriggerRun;
use deflou\interfaces\triggers\ITriggerAction;
use deflou\interfaces\triggers\ITriggerResponse;
use extas\components\http\THasHttpIO;
use extas\components\jsonrpc\THasJsonRpcRequest;
use extas\components\jsonrpc\THasJsonRpcResponse;
use extas\components\plugins\Plugin;

/**
 * Class PluginTriggerRun
 *
 * @package deflou\components\plugins\triggers
 * @author jeyroik <jeyroik@gmail.com>
 */
class PluginTriggerRun extends Plugin implements IStageTriggerRun
{
    use THasHttpIO;
    use THasJsonRpcRequest;
    use THasJsonRpcResponse;
    use THasTriggerObject;
    use THasActivity;

    /**
     * @param ITriggerResponse $triggerResponse
     */
    public function __invoke(ITriggerResponse &$triggerResponse): void
    {
        $trigger = $this->getTrigger();
        $action = $trigger->getAction();
        $event = $this->getActivity();
        $anchor = $event->getParameterValue('anchor');

        /**
         * @var ITriggerAction $dispatcher
         */
        $dispatcher = $action->buildClassWithParameters();
        $triggerResponse = $dispatcher($action, $event, $trigger, $anchor);
    }
}
