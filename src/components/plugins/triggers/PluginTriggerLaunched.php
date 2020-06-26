<?php
namespace deflou\components\plugins\triggers;

use deflou\interfaces\applications\activities\IActivity;
use deflou\interfaces\applications\anchors\IAnchor;
use deflou\interfaces\applications\IApplication;
use deflou\interfaces\stages\IStageTriggerLaunched;
use deflou\interfaces\triggers\ITrigger;
use deflou\interfaces\triggers\ITriggerResponse;
use deflou\components\applications\events\EventTriggerLaunched;
use deflou\components\applications\activities\THasActivity;
use deflou\components\triggers\THasTriggerObject;

use extas\interfaces\players\IPlayer;
use extas\interfaces\repositories\IRepository;
use extas\components\exceptions\MissedOrUnknown;
use extas\components\http\THasHttpIO;
use extas\components\jsonrpc\THasJsonRpcRequest;
use extas\components\jsonrpc\THasJsonRpcResponse;
use extas\components\plugins\Plugin;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Ramsey\Uuid\Uuid;

/**
 * Class PluginTriggerLaunched
 *
 * @method IRepository deflouApplicationRepository()
 * @method IRepository deflouActivityRepository()
 * @method IRepository deflouAnchorRepository()
 * @method notice($message, array $context)
 * @method warning($message, array $context)
 * @method ClientInterface httpClient()
 *
 * @package deflou\components\plugins\triggers
 * @author jeyroik@gmail.com
 */
class PluginTriggerLaunched extends Plugin implements IStageTriggerLaunched
{
    use THasHttpIO;
    use THasJsonRpcRequest;
    use THasJsonRpcResponse;
    use THasActivity;
    use THasTriggerObject;

    /**
     * @param ITriggerResponse $triggerResponse
     * @throws GuzzleException
     * @throws MissedOrUnknown
     */
    public function __invoke(ITriggerResponse $triggerResponse): void
    {
        try {
            /**
             * @var IAnchor $currentEventAnchor
             * @var IApplication[] $deflouInstances
             */
            $trigger = $this->getTrigger();
            $newEvent = $this->getCurrentInstanceEventName();
            $owner = $trigger->getPlayer();
            $currentEventAnchor = $this->getCurrentEventAnchor($newEvent, $owner, $trigger);
            $this->triggerAllDeflouInstances($currentEventAnchor, $triggerResponse);
        } catch (\Exception $e) {
            $this->warning($e->getMessage(), []);
        }
    }

    /**
     * @param IAnchor $anchor
     * @param ITriggerResponse $triggerResponse
     */
    protected function triggerAllDeflouInstances(IAnchor $anchor, ITriggerResponse $triggerResponse): void
    {
        $deflouInstances = $this->deflouApplicationRepository()->all([IApplication::FIELD__SAMPLE_NAME => 'deflou']);
        $client = $this->httpClient();

        foreach ($deflouInstances as $instance) {
            $this->sendEvent($instance, $anchor, $triggerResponse, $client);
        }
    }

    /**
     * @param IActivity $newEvent
     * @param IPlayer $owner
     * @param ITrigger $trigger
     * @return IAnchor
     * @throws MissedOrUnknown
     */
    protected function getCurrentEventAnchor(IActivity $newEvent, IPlayer $owner, ITrigger $trigger): IAnchor
    {
        $currentEventAnchor = $this->deflouAnchorRepository()->one([
            IAnchor::FIELD__EVENT_NAME => $newEvent->getName(),
            IAnchor::FIELD__PLAYER_NAME => $owner->getName(),
            IAnchor::FIELD__TRIGGER_NAME => $trigger->getName()
        ]);

        /**
         * This anchor also must be in a remote DeFlou instance
         */
        if (!$currentEventAnchor) {
            throw new MissedOrUnknown('anchor for a "trigger.launched" event');
        }

        return $currentEventAnchor;
    }

    /**
     * @param IApplication $instance
     * @param IAnchor $currentEventAnchor
     * @param ITriggerResponse $response
     * @param ClientInterface $client
     */
    protected function sendEvent(
        IApplication $instance,
        IAnchor $currentEventAnchor,
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
                'json' => $this->getSendingData($response, $currentEventAnchor)
            ]);
        } catch (\Exception $e) {
            $this->failSendEvent($e, $instance);
        }
    }

    /**
     * @param $response
     * @param $currentEventAnchor
     * @return array
     */
    protected function getSendingData($response, $currentEventAnchor)
    {
        return [
            EventTriggerLaunched::FIELD__TRIGGER_NAME => $this->getTrigger()->getName(),
            EventTriggerLaunched::FIELD__TRIGGER_RESPONSE => $response->__toArray(),
            EventTriggerLaunched::FIELD__ANCHOR => $this->getActivity()
                ->getParameterValue('anchor')
                ->__toArray(),
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
    protected function failSendEvent($e, IApplication $instance): void
    {
        $this->warning('Can not send "trigger.launched" event', $instance->__toArray());
    }

    /**
     * @return IActivity
     * @throws \Exception
     */
    protected function getCurrentInstanceEventName(): IActivity
    {
        $app = $this->getCurrentInstanceApplication();
        $event = $this->deflouActivityRepository()->one([
            IActivity::FIELD__SAMPLE_NAME => 'trigger.launched',
            IActivity::FIELD__APPLICATION_NAME => $app->getName(),
            IActivity::FIELD__TYPE => IActivity::TYPE__EVENT
        ]);

        if (!$event) {
            throw new MissedOrUnknown('event "trigger.launched" for the current instance');
        }

        return $event;
    }

    /**
     * @return IApplication
     * @throws MissedOrUnknown
     */
    protected function getCurrentInstanceApplication(): IApplication
    {
        $appName = getenv('DF__APP_NAME');
        $app = $this->deflouApplicationRepository()->one([IApplication::FIELD__NAME => $appName]);

        if (!$app) {
            throw new MissedOrUnknown('current instance application "' . $appName . '"');
        }

        return $app;
    }
}
