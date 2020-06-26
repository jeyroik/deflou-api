<?php
namespace tests;

use deflou\components\triggers\TriggerAction;
use deflou\interfaces\applications\activities\IActivity;
use deflou\interfaces\applications\anchors\IAnchor;
use deflou\interfaces\triggers\ITrigger;
use deflou\interfaces\triggers\ITriggerResponse;

/**
 * Class ActionWithException
 *
 * @package tests
 * @author jeyroik@gmail.com
 */
class ActionWithException extends TriggerAction
{
    public const EXCEPTION__MESSAGE = 'Worked action';

    /**
     * @param IActivity $action
     * @param IActivity $event
     * @param ITrigger $trigger
     * @param IAnchor $anchor
     * @return ITriggerResponse
     * @throws \Exception
     */
    public function __invoke(IActivity $action, IActivity $event, ITrigger $trigger, IAnchor $anchor): ITriggerResponse
    {
        throw new \Exception(static::EXCEPTION__MESSAGE, 400);
    }
}
