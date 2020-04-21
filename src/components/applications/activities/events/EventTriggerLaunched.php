<?php
namespace deflou\components\applications\activities\events;

use deflou\interfaces\applications\activities\IActivity;
use deflou\interfaces\applications\anchors\IAnchor;
use deflou\interfaces\triggers\ITriggerEvent;
use extas\components\Item;

/**
 * Class EventTriggerLaunched
 *
 * @package deflou\components\applications\activities\events
 * @author jeyroik@gmail.com
 */
class EventTriggerLaunched extends Item implements ITriggerEvent
{
    public const FIELD__ANCHOR = 'by_anchor';
    public const FIELD__TRIGGER_NAME = 'trigger_name';
    public const FIELD__TRIGGER_RESPONSE = 'trigger_response';

    /**
     * @param IActivity $event
     * @param IAnchor $anchor
     * @return IActivity
     * @throws \Exception
     */
    public function __invoke(IActivity $event, IAnchor $anchor): IActivity
    {
        $needParameters = [
            static::FIELD__ANCHOR,
            static::FIELD__TRIGGER_NAME,
            static::FIELD__TRIGGER_RESPONSE
        ];

        foreach ($needParameters as $parameterName) {
            if (!$event->hasParameter($parameterName)) {
                throw new \Exception('Missed "' . $parameterName . '" parameter');
            }
        }

        return $event;
    }

    protected function getSubjectForExtension(): string
    {
        return 'deflou.event.trigger.launched';
    }
}
