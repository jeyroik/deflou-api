<?php
namespace deflou\components\applications\activities\events;

use deflou\interfaces\applications\activities\IActivity;
use deflou\interfaces\applications\anchors\IAnchor;
use deflou\interfaces\triggers\ITriggerEvent;
use extas\components\Item;

class EventTriggerLaunched extends Item implements ITriggerEvent
{
    public function __invoke(IActivity $event, IAnchor $anchor): IActivity
    {

    }

    protected function getSubjectForExtension(): string
    {
        return 'deflou.event.trigger.launched';
    }
}
