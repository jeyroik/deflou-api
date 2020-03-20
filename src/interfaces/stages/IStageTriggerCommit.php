<?php
namespace deflou\interfaces;

use deflou\interfaces\applications\anchors\IAnchor;
use deflou\interfaces\triggers\ITrigger;
use deflou\interfaces\triggers\ITriggerResponse;

/**
 * Interface IStageTriggerCommit
 *
 * @package deflou\interfaces
 * @author jeyroik <jeyroik@gmail.com>
 */
interface IStageTriggerCommit
{
    /**
     * @param IAnchor $anchor
     * @param ITrigger $trigger
     * @param ITriggerResponse $response
     * @return void
     */
    public function __invoke(IAnchor $anchor, ITrigger $trigger, ITriggerResponse $response): void;
}
