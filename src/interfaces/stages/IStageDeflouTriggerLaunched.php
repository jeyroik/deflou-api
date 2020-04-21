<?php
namespace deflou\interfaces\stages;

use deflou\interfaces\applications\activities\IActivity;
use deflou\interfaces\applications\anchors\IAnchor;
use deflou\interfaces\triggers\ITrigger;
use deflou\interfaces\triggers\ITriggerResponse;

/**
 * Interface IStageDeflouTriggerLaunched
 *
 * Rising after trigger is launched.
 * See deflou\components\jsonrpc\operations\CreateEvent::dispatch() for details.
 *
 * @package deflou\interfaces\stages
 * @author jeyroik@gmail.com
 */
interface IStageDeflouTriggerLaunched
{
    public const NAME = 'deflou.trigger.launched';

    /**
     * @param IActivity $action
     * @param IActivity $event
     * @param ITrigger $trigger
     * @param IAnchor $anchor
     * @param ITriggerResponse $response
     */
    public function __invoke(
        IActivity $action,
        IActivity $event,
        ITrigger $trigger,
        IAnchor $anchor,
        ITriggerResponse $response
    ): void;
}
