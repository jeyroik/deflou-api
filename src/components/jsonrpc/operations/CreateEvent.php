<?php
namespace deflou\components\jsonrpc\operations;

use deflou\interfaces\applications\activities\IActivity;
use deflou\interfaces\applications\anchors\IAnchor;
use deflou\interfaces\applications\anchors\IAnchorRepository;
use deflou\interfaces\triggers\ITrigger;
use deflou\interfaces\triggers\ITriggerRepository;
use deflou\interfaces\triggers\ITriggerResponse;
use extas\components\jsonrpc\operations\OperationDispatcher;
use extas\components\SystemContainer;
use extas\interfaces\jsonrpc\IRequest;
use extas\interfaces\jsonrpc\IResponse;
use extas\interfaces\jsonrpc\operations\IOperationCreate;

/**
 * Class CreateEvent
 *
 * @package deflou\components\jsonrpc\operations
 * @author jeyroik <jeyroik@gmail.com>
 */
class CreateEvent extends OperationDispatcher implements IOperationCreate
{
    /**
     * @param IRequest $request
     * @param IResponse $response
     */
    protected function dispatch(IRequest $request, IResponse &$response)
    {
        $data = $request->getData();
        $anchorId = $data['app_anchor'];
        /**
         * @var $anchorRepo IAnchorRepository
         * @var $anchor IAnchor
         */
        $anchorRepo = SystemContainer::getItem(IAnchorRepository::class);
        $anchor = $anchorRepo->one([IAnchor::FIELD__ID => $anchorId]);

        if ($anchor) {
            $this->updateAnchor($anchor, $anchorRepo);
            $event = $this->getCurrentEvent($anchor, $data);
            $triggers = $this->getTriggersByAnchor($anchor);

            try {
                $responseData = [];
                foreach ($triggers as $trigger) {
                    if ($this->isValidTrigger($trigger, $event)) {
                        $this->enrichTrigger($trigger, $event);
                        $action = $trigger->getAction();
                        $dispatcher = $action->buildClassWithParameters();
                        /**
                         * @var $response ITriggerResponse
                         */
                        $response = $dispatcher($trigger, $event);
                        $responseData[$trigger->getName()] = $response->__toArray();
                        $this->commitTrigger($anchor, $trigger, $response);
                    }
                }
                $response->success($responseData);
            } catch (\Exception $e) {
                $response->error($e->getMessage(), 500);
            }
        } else {
            $response->error('Unknown anchor "' . $anchorId . '"', 400);
        }
    }

    /**
     * @param IAnchor $anchor
     * @param ITrigger $trigger
     * @param ITriggerResponse $response
     */
    protected function commitTrigger(IAnchor $anchor, ITrigger $trigger, ITriggerResponse $response)
    {
        foreach ($this->getPluginsByStage('deflou.trigger.commit') as $plugin) {
            $plugin($trigger, $response);
        }
    }

    /**
     * @param ITrigger $trigger
     * @param IActivity $event
     */
    protected function enrichTrigger(ITrigger &$trigger, IActivity $event)
    {
        foreach ($this->getPluginsByStage('deflou.trigger.enrich') as $plugin) {
            $plugin($trigger, $event);
        }
    }

    /**
     * @param IAnchor $anchor
     * @param array $data
     *
     * @return IActivity|null
     */
    protected function getCurrentEvent(IAnchor $anchor, array $data)
    {
        $event = $anchor->getEvent();
        $event->setParametersValues($data);
        $eventDispatcher = $event->buildClassWithParameters();
        $eventDispatcher($event);

        return $event;
    }

    /**
     * @param ITrigger $trigger
     * @param IActivity $event
     *
     * @return bool
     */
    protected function isValidTrigger(ITrigger $trigger, IActivity $event): bool
    {
        $triggerParameters = $trigger->getEventParameters();
        foreach ($triggerParameters as $triggerParameter) {
            if ($event->hasParameter($triggerParameter->getName())) {
                $currentEventParameter = $event->getParameter($triggerParameter->getName());
                if (!$triggerParameter->isConditionTrue($currentEventParameter->getValue())) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param IAnchor $anchor
     * @param IAnchorRepository $anchorRepo
     */
    protected function updateAnchor(IAnchor $anchor, IAnchorRepository $anchorRepo)
    {
        $anchor->incCallsNumber();
        $anchor->setLastCallTime(time());
        $anchorRepo->update($anchor);
    }

    /**
     * @param IAnchor $anchor
     * @return ITrigger[]
     */
    protected function getTriggersByAnchor($anchor)
    {
        /**
         * @var $triggerRepo ITriggerRepository
         */
        $triggerRepo = SystemContainer::getItem(ITriggerRepository::class);

        $type2triggers = [
            IAnchor::TYPE__GENERAL => function (IAnchor $anchor) use ($triggerRepo) {
                return $triggerRepo->all([ITrigger::FIELD__EVENT_NAME => $anchor->getEventName()]);
            },
            IAnchor::TYPE__PLAYER => function (IAnchor $anchor) use ($triggerRepo) {
                return $triggerRepo->all([
                    ITrigger::FIELD__EVENT_NAME => $anchor->getEventName(),
                    ITrigger::FIELD__OWNER => $anchor->getPlayerName()
                ]);
            },
            IAnchor::TYPE__TRIGGER => function (IAnchor $anchor) use ($triggerRepo) {
                return $triggerRepo->all([ITrigger::FIELD__NAME => $anchor->getTriggerName()]);
            }
        ];

        $type = $anchor->getType();

        return isset($type2triggers[$type])
            ? $type2triggers[$type]($anchor)
            : [];
    }

    /**
     * @return string
     */
    protected function getSubjectForExtension(): string
    {
        return 'deflou.event.create';
    }
}
