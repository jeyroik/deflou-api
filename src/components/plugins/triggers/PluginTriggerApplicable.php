<?php
namespace deflou\components\plugins\triggers;

use deflou\components\applications\activities\THasActivity;
use deflou\components\triggers\THasTriggerObject;
use deflou\interfaces\applications\activities\IActivity;
use deflou\interfaces\stages\IStageTriggerApplicable;
use extas\components\exceptions\MissedOrUnknown;
use extas\components\http\THasHttpIO;
use extas\components\jsonrpc\THasJsonRpcRequest;
use extas\components\jsonrpc\THasJsonRpcResponse;
use extas\components\plugins\Plugin;
use extas\interfaces\conditions\IConditionParameter;

/**
 * Class PluginTriggerApplicable
 *
 * @package deflou\components\plugins\triggers
 * @author jeyroik <jeyroik@gmail.com>
 */
class PluginTriggerApplicable extends Plugin implements IStageTriggerApplicable
{
    use THasHttpIO;
    use THasJsonRpcRequest;
    use THasJsonRpcResponse;
    use THasTriggerObject;
    use THasActivity;

    /**
     * @return bool
     * @throws MissedOrUnknown
     */
    public function __invoke(): bool
    {
        $trigger = $this->getTrigger();
        $event = $this->getActivity();

        $triggerParameters = $trigger->getEventParameters();
        foreach ($triggerParameters as $triggerParameter) {
            if (!$this->eventHasApplicableField($event, $triggerParameter)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param IActivity $event
     * @param IConditionParameter $triggerParameter
     * @return bool
     * @throws MissedOrUnknown
     */
    protected function eventHasApplicableField(IActivity $event, IConditionParameter $triggerParameter): bool
    {
        if (!$event->hasField($triggerParameter->getName())) {
            return false;
        }

        $eventField = $event->getField($triggerParameter->getName());
        if (!$triggerParameter->isConditionTrue($eventField->getValue())) {
            return false;
        }

        return true;
    }
}
