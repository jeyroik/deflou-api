<?php
namespace deflou\components\plugins\triggers;

use deflou\components\applications\activities\events\EventTriggerLaunched;
use deflou\interfaces\applications\activities\IActivity;
use deflou\interfaces\applications\activities\IActivityRepository;
use deflou\interfaces\applications\anchors\IAnchor;
use deflou\interfaces\applications\anchors\IAnchorRepository;
use deflou\interfaces\applications\IApplication;
use deflou\interfaces\applications\IApplicationRepository;
use deflou\interfaces\stages\IStageDeflouTriggerLaunched;
use deflou\interfaces\triggers\ITrigger;
use deflou\interfaces\triggers\ITriggerResponse;
use extas\components\plugins\Plugin;
use extas\components\SystemContainer;
use extas\interfaces\repositories\IRepository;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Ramsey\Uuid\Uuid;

/**
 * Class PluginTriggerLaunched
 *
 * @package deflou\components\plugins\triggers
 * @author jeyroik@gmail.com
 */
class PluginTriggerLaunched extends Plugin implements IStageDeflouTriggerLaunched
{
    /**
     * @param IActivity $action
     * @param IActivity $event
     * @param ITrigger $trigger
     * @param IAnchor $anchor
     * @param ITriggerResponse $response
     * @throws \Exception
     */
    public function __invoke(
        IActivity $action,
        IActivity $event,
        ITrigger $trigger,
        IAnchor $anchor,
        ITriggerResponse $response
    ): void
    {
        $newEvent = $this->getCurrentInstanceEventName();
        $owner = $trigger->getPlayer();
        /**
         * @var IRepository $repo
         * @var IAnchor $currentEventAnchor
         */
        $repo = SystemContainer::getItem(IAnchorRepository::class);
        $currentEventAnchor = $repo->one([
            IAnchor::FIELD__EVENT_NAME => $newEvent->getName(),
            IAnchor::FIELD__PLAYER_NAME => $owner->getName(),
            IAnchor::FIELD__TRIGGER_NAME => $trigger->getName()
        ]);

        /**
         * This anchor also must be in a remote DeFlou instance
         */
        if (!$currentEventAnchor) {
            throw new \Exception('Missed anchor for a trigger.launched event');
        }

        /**
         * @var IRepository $appRepo
         * @var IApplication[] $deflouInstances
         */
        $appRepo = SystemContainer::getItem(IApplicationRepository::class);
        $deflouInstances = $appRepo->all([IApplication::FIELD__SAMPLE_NAME => 'deflou']);
        $client = $this->getClient();

        foreach ($deflouInstances as $instance) {
            $this->sendEvent($instance, $currentEventAnchor, $anchor, $trigger, $response, $client);
        }
    }

    /**
     * @param IApplication $instance
     * @param IAnchor $currentEventAnchor
     * @param IAnchor $anchor
     * @param ITrigger $trigger
     * @param ITriggerResponse $response
     * @param ClientInterface $client
     */
    protected function sendEvent(
        IApplication $instance,
        IAnchor $currentEventAnchor,
        IAnchor $anchor,
        ITrigger $trigger,
        ITriggerResponse $response,
        ClientInterface $client
    ): void
    {
        $host = $instance->getParameterValue('host', 'localhost');
        $port = $instance->getParameterValue('port', 80);
        $schema = $instance->getParameterValue('schema', 'https');
        $url = $schema . $host . ':' . $port . '/api/jsonrpc';
        try {
            $client->request('post', $url, [
                'json' => $this->getSendingData($trigger, $response, $anchor, $currentEventAnchor)
            ]);
        } catch (GuzzleException $e) {
            $this->failSendEvent($e, $instance);
        }
    }

    /**
     * @param $trigger
     * @param $response
     * @param $anchor
     * @param $currentEventAnchor
     * @return array
     */
    protected function getSendingData($trigger, $response, $anchor, $currentEventAnchor)
    {
        return [
            EventTriggerLaunched::FIELD__TRIGGER_NAME => $trigger->getName(),
            EventTriggerLaunched::FIELD__TRIGGER_RESPONSE => $response->__toArray(),
            EventTriggerLaunched::FIELD__ANCHOR => $anchor->__toArray(),
            'anchor' => $currentEventAnchor->getId(),
            'version' => '2.0',
            'df_version' => getenv('DF__VERSION'),
            'id' => Uuid::uuid6()->toString()
        ];
    }

    /**
     * @param $e
     * @param $instance
     */
    protected function failSendEvent($e, $instance): void
    {
        /**
         * log
         */
    }

    protected function getClient(): ClientInterface
    {
        return new Client();
    }

    /**
     * @return IActivity
     * @throws \Exception
     */
    protected function getCurrentInstanceEventName(): IActivity
    {
        $app = $this->getCurrentInstanceApplication();
        /**
         * @var IRepository $repo
         */
        $repo = SystemContainer::getItem(IActivityRepository::class);
        $event = $repo->one([
            IActivity::FIELD__SAMPLE_NAME => 'trigger.launched',
            IActivity::FIELD__APPLICATION_NAME => $app->getName(),
            IActivity::FIELD__TYPE => IActivity::TYPE__EVENT
        ]);

        if (!$event) {
            throw new \Exception('Missed event trigger.launched for the current instance');
        }

        return $event;
    }

    /**
     * @return IApplication
     * @throws \Exception
     */
    protected function getCurrentInstanceApplication(): IApplication
    {
        /**
         * @var IRepository $repo
         */
        $appName = getenv('DF__APP_NAME');
        $repo = SystemContainer::getItem(IApplicationRepository::class);
        $app = $repo->one([IApplication::FIELD__NAME => $appName]);

        if (!$app) {
            throw new \Exception('Missed current instance application (' . $appName . ')');
        }

        return $app;
    }
}
