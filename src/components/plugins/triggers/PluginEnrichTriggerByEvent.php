<?php
namespace deflou\components\plugins\triggers;

use deflou\interfaces\applications\activities\IActivity;
use deflou\interfaces\triggers\ITrigger;
use extas\components\plugins\Plugin;
use extas\components\Replace;

/**
 * Class PluginEnrichTriggerByEvent
 *
 * @stage deflou.trigger.enrich
 * @package deflou\components\plugins\triggers
 * @author jeyroik <jeyroik@gmail.com>
 */
class PluginEnrichTriggerByEvent extends Plugin
{
    /**
     * @param ITrigger $trigger
     * @param IActivity $event
     */
    public function __invoke(ITrigger &$trigger, IActivity $event)
    {
        $eventParameters = $event->getParametersValues();
        $triggerParameters = $trigger->getActionParameters();

        foreach ($triggerParameters as $triggerParameter) {
            $newValue = Replace::please()->apply(['event' => $eventParameters])->to($triggerParameter->getValue());
            $trigger->setParameterValue($triggerParameter->getName(), $newValue);
        }
    }
}
