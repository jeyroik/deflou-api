<?php
namespace deflou\components\plugins\triggers;

use deflou\interfaces\applications\anchors\IAnchor;
use deflou\interfaces\stages\IStageTriggerTriggers;
use deflou\interfaces\triggers\ITrigger;
use deflou\components\applications\activities\THasActivity;

use extas\interfaces\repositories\IRepository;
use extas\components\exceptions\MissedOrUnknown;
use extas\components\http\THasHttpIO;
use extas\components\jsonrpc\THasJsonRpcRequest;
use extas\components\jsonrpc\THasJsonRpcResponse;
use extas\components\plugins\Plugin;

/**
 * Class PluginTriggerTriggers
 *
 * @method IRepository deflouTriggerRepository()
 * @method notice($message, array $context)
 *
 * @package deflou\components\plugins\triggers
 * @author jeyroik <jeyroik@gmail.com>
 */
class PluginTriggerTriggers extends Plugin implements IStageTriggerTriggers
{
    use THasHttpIO;
    use THasJsonRpcResponse;
    use THasJsonRpcRequest;
    use THasActivity;

    public const FIELD__ANCHOR = 'anchor';

    /**
     * @param array $triggers
     * @throws MissedOrUnknown
     */
    public function __invoke(array &$triggers): void
    {
        $event = $this->getActivity();

        if (!$event->hasParameter(static::FIELD__ANCHOR)) {
            $this->notice(
                (new MissedOrUnknown('anchor in the current trigger event parameters'))->getMessage(),
                $event->getParametersValues()
            );
        }

        $anchor = $event->getParameterValue(static::FIELD__ANCHOR, null);

        if (!$anchor) {
            $this->notice(
                (new MissedOrUnknown('anchor'))->getMessage(),
                $event->getParametersValues()
            );
        }

        $type2triggers = [
            IAnchor::TYPE__GENERAL => function (IAnchor $anchor) {
                return $this->deflouTriggerRepository()->all([ITrigger::FIELD__EVENT_NAME => $anchor->getEventName()]);
            },
            IAnchor::TYPE__PLAYER => function (IAnchor $anchor) {
                return $this->deflouTriggerRepository()->all([
                    ITrigger::FIELD__EVENT_NAME => $anchor->getEventName(),
                    ITrigger::FIELD__PLAYER_NAME => $anchor->getPlayerName()
                ]);
            },
            IAnchor::TYPE__TRIGGER => function (IAnchor $anchor) {
                return $this->deflouTriggerRepository()->all([ITrigger::FIELD__NAME => $anchor->getTriggerName()]);
            }
        ];

        $type = $anchor->getType();

        $triggers = isset($type2triggers[$type]) ? $type2triggers[$type]($anchor) : [];
    }
}
