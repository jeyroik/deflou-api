<?php
namespace deflou\components\jsonrpc\operations;

use deflou\interfaces\applications\activities\IActivity;
use deflou\interfaces\applications\anchors\IAnchor;
use deflou\interfaces\stages\IStageDeFlouTriggerEnrich;
use deflou\interfaces\stages\IStageDeflouTriggerLaunched;
use deflou\interfaces\triggers\ITrigger;
use deflou\interfaces\triggers\ITriggerAction;
use deflou\interfaces\triggers\ITriggerEvent;
use extas\components\jsonrpc\operations\OperationDispatcher;
use extas\interfaces\conditions\IConditionParameter;
use extas\interfaces\jsonrpc\operations\IOperationCreate;
use Psr\Http\Message\ResponseInterface;

/**
 * Class CreateEvent
 *
 * @method anchorRepository()
 * @method triggerRepository()
 *
 * @package deflou\components\jsonrpc\operations
 * @author jeyroik <jeyroik@gmail.com>
 */
class CreateTriggerEvent extends OperationDispatcher implements IOperationCreate
{
    public const REQUEST__ANCHOR = 'anchor';

    public function __invoke(): ResponseInterface
    {
        $request = $this->convertPsrToJsonRpcRequest();
        $data = $request->getData();
        $anchorId = $data[static::REQUEST__ANCHOR] ?? '';
        /**
         * @var $anchor IAnchor
         */
        $anchor = $this->anchorRepository()->one([IAnchor::FIELD__ID => $anchorId]);

        if (!$anchor) {
            return $this->errorResponse($request->getId(), 'Unknown anchor "' . $anchorId . '"', 400);
        }

        $this->updateAnchor($anchor);
        try {
            $event = $this->getCurrentEvent($anchor, $data);
            $triggers = $this->getTriggersByAnchor($anchor);

            foreach ($triggers as $trigger) {
                $this->isApplicableTrigger($trigger, $event)
                    ? $this->runTrigger($trigger, $event, $anchor)
                    : $this->notApplicableTrigger($trigger, $event);
            }
            return $this->successResponse($request->getId(), []);
        } catch (\Exception $e) {
            return $this->errorResponse($request->getId(), $e->getMessage(), 400);
        }
    }

    /**
     * @param ITrigger $trigger
     * @param IActivity $event
     * @param IAnchor $anchor
     * @throws \Exception
     */
    protected function runTrigger(ITrigger $trigger, IActivity $event, IAnchor $anchor): void
    {
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
            if (!$this->eventHasApplicableParameter($event, $triggerParameter)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param IActivity $event
     * @param IConditionParameter $triggerParameter
     * @return bool
     */
    protected function eventHasApplicableParameter(IActivity $event, IConditionParameter $triggerParameter): bool
    {
        if (!$event->hasParameter($triggerParameter->getName())) {
            return false;
        }

        $currentEventParameter = $event->getParameter($triggerParameter->getName());
        if (!$triggerParameter->isConditionTrue($currentEventParameter->getValue())) {
            return false;
        }

        return true;
    }

    /**
     * @param IAnchor $anchor
     */
    protected function updateAnchor(IAnchor $anchor)
    {
        $anchor->incCallsNumber();
        $anchor->setLastCallTime(time());
        $this->anchorRepository()->update($anchor);
    }

    /**
     * @param IAnchor $anchor
     * @return ITrigger[]
     */
    protected function getTriggersByAnchor($anchor)
    {
        $type2triggers = [
            IAnchor::TYPE__GENERAL => function (IAnchor $anchor) {
                return $this->triggerRepository()->all([ITrigger::FIELD__EVENT_NAME => $anchor->getEventName()]);
            },
            IAnchor::TYPE__PLAYER => function (IAnchor $anchor) {
                return $this->triggerRepository()->all([
                    ITrigger::FIELD__EVENT_NAME => $anchor->getEventName(),
                    ITrigger::FIELD__PLAYER_NAME => $anchor->getPlayerName()
                ]);
            },
            IAnchor::TYPE__TRIGGER => function (IAnchor $anchor) {
                return $this->triggerRepository()->all([ITrigger::FIELD__NAME => $anchor->getTriggerName()]);
            }
        ];

        $type = $anchor->getType();

        return isset($type2triggers[$type]) ? $type2triggers[$type]($anchor) : [];
    }

    /**
     * @return string
     */
    protected function getSubjectForExtension(): string
    {
        return 'deflou.event.create';
    }
}
