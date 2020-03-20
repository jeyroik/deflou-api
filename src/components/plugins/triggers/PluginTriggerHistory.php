<?php
namespace deflou\components\plugins\triggers;

use deflou\components\triggers\TriggerExecutionHistory;
use deflou\interfaces\applications\anchors\IAnchor;
use deflou\interfaces\IStageTriggerCommit;
use deflou\interfaces\triggers\ITrigger;
use deflou\interfaces\triggers\ITriggerExecutionHistory;
use deflou\interfaces\triggers\ITriggerExecutionHistoryRepository;
use deflou\interfaces\triggers\ITriggerResponse;
use extas\components\plugins\Plugin;
use extas\components\SystemContainer;

/**
 * Class PluginTriggerHistory
 *
 * @stage deflou.trigger.commit
 * @package deflou\components\plugins\triggers
 * @author jeyroik <jeyroik@gmail.com>
 */
class PluginTriggerHistory extends Plugin implements IStageTriggerCommit
{
    /**
     * @param IAnchor $anchor
     * @param ITrigger $trigger
     * @param ITriggerResponse $response
     */
    public function __invoke(IAnchor $anchor, ITrigger $trigger, ITriggerResponse $response): void
    {
        /**
         * @var $repo ITriggerExecutionHistoryRepository
         */
        $repo = SystemContainer::getItem(ITriggerExecutionHistoryRepository::class);
        $history = new TriggerExecutionHistory([
            ITriggerExecutionHistory::FIELD__ANCHOR_ID => $anchor->getId(),
            ITriggerExecutionHistory::FIELD__EVENT_NAME => $trigger->getEventName(),
            ITriggerExecutionHistory::FIELD__ACTION_NAME => $trigger->getActionName(),
            ITriggerExecutionHistory::FIELD__TRIGGER_NAME => $trigger->getName(),
            ITriggerExecutionHistory::FIELD__STATUS => $response->getStatus(),
            ITriggerExecutionHistory::FIELD__BODY => $response->getBody(),
            ITriggerExecutionHistory::FIELD__CREATED_AT => time()
        ]);
        $repo->create($history);
    }
}
