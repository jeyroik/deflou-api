<?php
namespace deflou\components\applications\activities\actions;

use deflou\components\triggers\TriggerAction;
use deflou\interfaces\applications\activities\IActivity;
use deflou\interfaces\applications\anchors\IAnchor;
use deflou\interfaces\triggers\ITrigger;
use deflou\interfaces\triggers\ITriggerAction;
use deflou\interfaces\triggers\ITriggerResponse;

/**
 * Class ActionNothing
 *
 * @package deflou\components\applications\activities\actions
 * @author jeyroik@gmail.com
 */
class ActionNothing extends TriggerAction implements ITriggerAction
{
    /**
     * @param IActivity $action
     * @param IActivity $event
     * @param ITrigger $trigger
     * @param IAnchor $anchor
     * @return ITriggerResponse
     */
    public function __invoke(IActivity $action, IActivity $event, ITrigger $trigger, IAnchor $anchor): ITriggerResponse
    {
        return $this->getTriggerResponse(
            $action,
            $event,
            $trigger,
            $anchor,
            'Nothing is done',
            200
        );
    }
}
