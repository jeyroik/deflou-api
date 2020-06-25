<?php
namespace deflou\components\jsonrpc\operations;

use deflou\components\triggers\TriggerResponse;
use deflou\interfaces\applications\activities\IActivity;
use deflou\interfaces\stages\IStageTriggerEnrich;
use deflou\interfaces\stages\IStageTriggerApplicable;
use deflou\interfaces\stages\IStageTriggerApplicableNot;
use deflou\interfaces\stages\IStageTriggerEvent;
use deflou\interfaces\stages\IStageTriggerLaunched;
use deflou\interfaces\stages\IStageTriggerRun;
use deflou\interfaces\stages\IStageTriggerTriggers;
use deflou\interfaces\triggers\ITrigger;
use deflou\components\applications\activities\Activity;

use extas\interfaces\jsonrpc\operations\IOperationCreate;
use extas\interfaces\repositories\IRepository;
use extas\components\jsonrpc\operations\OperationDispatcher;

use Psr\Http\Message\ResponseInterface;

/**
 * Class CreateEvent
 *
 * @method IRepository anchorRepository()
 * @method IRepository triggerRepository()
 *
 * @package deflou\components\jsonrpc\operations
 * @author jeyroik <jeyroik@gmail.com>
 */
class CreateTriggerEvent extends OperationDispatcher implements IOperationCreate
{
    /**
     * @return ResponseInterface
     */
    public function __invoke(): ResponseInterface
    {
        $request = $this->getJsonRpcRequest();

        try {
            $event = $this->getCurrentEvent();
            $triggers = $this->getTriggers($event);

            foreach ($triggers as $trigger) {
                $this->dispatchTrigger($trigger, $event);
            }

            return $this->successResponse($request->getId(), []);
        } catch (\Exception $e) {
            return $this->errorResponse($request->getId(), $e->getMessage(), $e->getCode());
        }
    }

    /**
     * @return IActivity
     */
    protected function getCurrentEvent(): IActivity
    {
        $event = new Activity();
        $pluginConfig = $this->getHttpIO();

        foreach ($this->getPluginsByStage(IStageTriggerEvent::NAME, $pluginConfig) as $plugin) {
            /**
             * @var IStageTriggerEvent $plugin
             */
            $plugin($event);
        }

        return $event;
    }

    /**
     * @param IActivity $event
     * @return array
     */
    protected function getTriggers(IActivity $event): array
    {
        $triggers = [];
        $pluginConfig = $this->getHttpIO([IStageTriggerTriggers::FIELD__ACTIVITY => $event]);

        foreach ($this->getPluginsByStage(IStageTriggerTriggers::NAME, $pluginConfig) as $plugin) {
            /**
             * @var IStageTriggerTriggers $plugin
             */
            $plugin($triggers);
        }

        $stage = IStageTriggerTriggers::NAME . '.' . $event->getApplication()->getSampleName();
        foreach ($this->getPluginsByStage($stage, $pluginConfig) as $plugin) {
            /**
             * @var IStageTriggerTriggers $plugin
             */
            $plugin($triggers);
        }

        $stage .= '.' . $event->getSampleName();
        foreach ($this->getPluginsByStage($stage, $pluginConfig) as $plugin) {
            /**
             * @var IStageTriggerTriggers $plugin
             */
            $plugin($triggers);
        }

        return $triggers;
    }

    /**
     * @param ITrigger $trigger
     * @param IActivity $event
     * @return bool
     * @throws \Exception
     */
    protected function dispatchTrigger(ITrigger $trigger, IActivity $event): bool
    {
        if (!$this->isApplicableTrigger($trigger, $event)) {
            $this->notApplicableTrigger($trigger, $event);
            return false;
        }

        $this->enrichTrigger($trigger, $event);
        $this->runTrigger($trigger, $event);

        return true;
    }

    /**
     * @param ITrigger $trigger
     * @param IActivity $event
     * @return bool
     */
    protected function isApplicableTrigger(ITrigger $trigger, IActivity $event): bool
    {
        $pluginConfig = $this->getHttpIO([
            IStageTriggerApplicable::FIELD__TRIGGER => $trigger,
            IStageTriggerApplicable::FIELD__ACTIVITY => $event
        ]);

        $stage = IStageTriggerApplicable::NAME . '.' . $event->getApplication()->getSampleName();
        foreach ($this->getPluginsByStage($stage, $pluginConfig) as $plugin) {
            if (!$plugin()) {
                return false;
            }
        }

        $stage .= $event->getSampleName();
        foreach ($this->getPluginsByStage($stage, $pluginConfig) as $plugin) {
            if (!$plugin()) {
                return false;
            }
        }

        foreach ($this->getPluginsByStage(IStageTriggerApplicable::NAME, $pluginConfig) as $plugin) {
            if (!$plugin()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param ITrigger $trigger
     * @param IActivity $event
     */
    protected function notApplicableTrigger(ITrigger $trigger, IActivity $event): void
    {
        $pluginConfig = $this->getHttpIO([
            IStageTriggerApplicableNot::FIELD__TRIGGER => $trigger,
            IStageTriggerApplicableNot::FIELD__ACTIVITY => $event
        ]);

        foreach ($this->getPluginsByStage(IStageTriggerApplicableNot::NAME, $pluginConfig) as $plugin) {
            $plugin();
        }
    }

    /**
     * @param ITrigger $trigger
     * @param IActivity $event
     * @throws \Exception
     */
    protected function enrichTrigger(ITrigger &$trigger, IActivity $event)
    {
        $pluginConfig = $this->getHttpIO([IStageTriggerEnrich::FIELD__ACTIVITY => $event]);

        $this->runEnrichStage(IStageTriggerEnrich::NAME, $pluginConfig, $trigger);

        $stage = IStageTriggerEnrich::NAME . '.' . $event->getApplication()->getSampleName();
        $this->runEnrichStage($stage, $pluginConfig, $trigger);

        $stage .= '.' . $event->getSampleName();
        $this->runEnrichStage($stage, $pluginConfig, $trigger);

        $stage = IStageTriggerEnrich::NAME . '.' . $trigger->getAction()->getApplication()->getSampleName();
        $this->runEnrichStage($stage, $pluginConfig, $trigger);

        $stage .= '.' . $trigger->getAction()->getSampleName();
        $this->runEnrichStage($stage, $pluginConfig, $trigger);
    }

    /**
     * @param string $stage
     * @param array $pluginConfig
     * @param ITrigger $trigger
     */
    protected function runEnrichStage(string $stage, array $pluginConfig, ITrigger &$trigger): void
    {
        foreach ($this->getPluginsByStage($stage, $pluginConfig) as $plugin) {
            /**
             * @var IStageTriggerEnrich $plugin
             */
            $plugin($trigger);
        }
    }

    /**
     * @param ITrigger $trigger
     * @param IActivity $event
     * @throws \Exception
     */
    protected function runTrigger(ITrigger $trigger, IActivity $event): void
    {
        $pluginConfig = $this->getHttpIO([
            IStageTriggerRun::FIELD__TRIGGER => $trigger,
            IStageTriggerRun::FIELD__ACTIVITY => $event
        ]);

        $triggerResponse = new TriggerResponse();
        foreach ($this->getPluginsByStage(IStageTriggerRun::NAME, $pluginConfig) as $plugin) {
            /**
             * @var IStageTriggerRun $plugin
             */
            $plugin($triggerResponse);
        }

        foreach ($this->getPluginsByStage(IStageTriggerLaunched::NAME, $pluginConfig) as $plugin) {
            /**
             * @var IStageTriggerLaunched $plugin
             */
            $plugin($triggerResponse);
        }
    }

    /**
     * @return string
     */
    protected function getSubjectForExtension(): string
    {
        return 'deflou.event.create';
    }
}
