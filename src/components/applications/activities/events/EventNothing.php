<?php
namespace deflou\components\applications\activities\events;

use deflou\interfaces\applications\activities\IActivity;
use deflou\interfaces\applications\anchors\IAnchor;
use deflou\interfaces\triggers\ITriggerEvent;
use extas\components\Item;
use extas\components\samples\parameters\SampleParameter;
use extas\interfaces\samples\parameters\ISampleParameter;

/**
 * Class EventNothing
 *
 * @package deflou\components\applications\activities\events
 * @author jeyroik@gmail.com
 */
class EventNothing extends Item implements ITriggerEvent
{
    /**
     * @param IActivity $event
     * @param IAnchor $anchor
     * @return IActivity
     */
    public function __invoke(IActivity $event, IAnchor $anchor): IActivity
    {
        $event->addParameters([
            new SampleParameter([
                ISampleParameter::FIELD__NAME => 'current_date',
                ISampleParameter::FIELD__VALUE => time()
            ])
        ]);

        return $event;
    }

    protected function getSubjectForExtension(): string
    {
        return 'deflou.event.nothing';
    }
}
