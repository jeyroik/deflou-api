<?php
namespace deflou\components\jsonrpc\operations;

use deflou\interfaces\applications\activities\IActivity;
use deflou\interfaces\applications\anchors\IAnchor;
use deflou\interfaces\applications\anchors\IAnchorRepository;
use deflou\interfaces\stages\IStageDeFlouTriggerEnrich;
use deflou\interfaces\stages\IStageDeflouTriggerLaunched;
use deflou\interfaces\triggers\ITrigger;
use deflou\interfaces\triggers\ITriggerAction;
use deflou\interfaces\triggers\ITriggerEvent;
use deflou\interfaces\triggers\ITriggerRepository;
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
    public const REQUEST__ANCHOR = 'anchor';

    /**
     * @param IRequest $request
     * @param IResponse $response
     */
    protected function dispatch(IRequest $request, IResponse &$response)
    {
        $data = $request->getData();
        $anchorId = $data[static::REQUEST__ANCHOR] ?? '';
        /**
         * @var $anchorRepo IAnchorRepository
         * @var $anchor IAnchor
         */
        $anchorRepo = SystemContainer::getItem(IAnchorRepository::class);
        $anchor = $anchorRepo->one([IAnchor::FIELD__ID => $anchorId]);

        if ($anchor) {
            $this->updateAnchor($anchor, $anchorRepo);
            try {
                $event = $this->getCurrentEvent($anchor, $data);
                $triggers = $this->getTriggersByAnchor($anchor);

                foreach ($triggers as $trigger) {
                    if ($this->isApplicableTrigger($trigger, $event)) {
                        $action = $trigger->getAction(true);
                        $this->enrichTrigger($action, $event, $trigger);
                        /**
                         * @var ITriggerAction $dispatcher
                         */
                        $dispatcher = $action->buildClassWithParameters();
                        $triggerResponse = $dispatcher($action, $event, $trigger, $anchor);
                        foreach ($this->getPluginsByStage(IStageDeflouTriggerLaunched::NAME) as $plugin) {
                            /**
                             * @var IStageDeflouTriggerLaunched $plugin
                             */
                            $plugin($event, $action, $trigger, $anchor, $triggerResponse);
                        }
                    } else {
                        $this->notApplicableTrigger($trigger, $event);
                    }
                }
                $response->success([]);
            } catch (\Exception $e) {
                $response->error($e->getMessage(), 400);
            }
        } else {
            $response->error('Unknown anchor "' . $anchorId . '"', 400);
        }
    }

    /**
     * Заготовка на будущее для логирования подобных кейсов.
     *
     * @param ITrigger $trigger
     * @param IActivity $event
     */
    protected function notApplicableTrigger(ITrigger $trigger, IActivity $event): void
    {

    }

    /**
     * @param IActivity $action
     * @param IActivity $event
     * @param ITrigger $trigger
     */
    protected function enrichTrigger(IActivity $action, IActivity $event, ITrigger &$trigger)
    {
        foreach ($this->getPluginsByStage(IStageDeFlouTriggerEnrich::NAME) as $plugin) {
            /**
             * @var IStageDeFlouTriggerEnrich $plugin
             */
            $plugin($action, $event, $trigger);
        }
    }

    /**
     * @param IAnchor $anchor
     * @param array $data
     *
     * @return IActivity|null
     * @throws \Exception
     */
    protected function getCurrentEvent(IAnchor $anchor, array $data)
    {
        /**
         * @var ITriggerEvent $eventDispatcher
         */
        $event = $anchor->getEvent(true);
        $event->addParametersByValues($data);

        $eventDispatcher = $event->buildClassWithParameters($data);

        return $eventDispatcher($event, $anchor);
    }

    /**
     * @param ITrigger $trigger
     * @param IActivity $event
     *
     * @return bool
     */
    protected function isApplicableTrigger(ITrigger $trigger, IActivity $event): bool
    {
        $triggerParameters = $trigger->getEventParameters();
        foreach ($triggerParameters as $triggerParameter) {
            if ($event->hasParameter($triggerParameter->getName())) {
                $currentEventParameter = $event->getParameter($triggerParameter->getName());
                if (!$triggerParameter->isConditionTrue($currentEventParameter->getValue())) {
                    return false;
                }
            } else {
                return false;
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
                    ITrigger::FIELD__PLAYER_NAME => $anchor->getPlayerName()
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
