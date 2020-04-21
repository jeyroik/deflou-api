<?php
namespace tests;

use deflou\interfaces\applications\activities\IActivity;
use deflou\interfaces\applications\anchors\IAnchor;
use deflou\interfaces\stages\IStageDeflouTriggerLaunched;
use deflou\interfaces\triggers\ITrigger;
use deflou\interfaces\triggers\ITriggerResponse;
use extas\components\plugins\Plugin;

/**
 * Class PluginLaunchedWithException
 *
 * @package tests
 * @author jeyroik@gmail.com
 */
class PluginLaunchedWithException extends Plugin implements IStageDeflouTriggerLaunched
{
    public const EXCEPTION__MESSAGE = 'Worked';

    /**
     * @param IActivity $action
     * @param IActivity $event
     * @param ITrigger $trigger
     * @param IAnchor $anchor
     * @param ITriggerResponse $response
     * @throws \Exception
     */
    public function __invoke(IActivity $action, IActivity $event, ITrigger $trigger, IAnchor $anchor, ITriggerResponse $response): void
    {
        throw new \Exception(static::EXCEPTION__MESSAGE);
    }
}
