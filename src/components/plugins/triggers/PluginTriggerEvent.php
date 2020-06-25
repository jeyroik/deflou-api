<?php
namespace deflou\components\plugins\triggers;

use deflou\interfaces\applications\activities\IActivity;
use deflou\interfaces\applications\anchors\IAnchor;
use deflou\interfaces\stages\IStageTriggerEvent;

use extas\interfaces\repositories\IRepository;
use extas\components\exceptions\MissedOrUnknown;
use extas\components\http\THasHttpIO;
use extas\components\jsonrpc\THasJsonRpcRequest;
use extas\components\jsonrpc\THasJsonRpcResponse;
use extas\components\plugins\Plugin;

/**
 * Class PluginTriggerEvent
 *
 * @method IRepository anchorRepository()
 *
 * @package deflou\components\plugins\triggers
 * @author jeyroik <jeyroik@gmail.com>
 */
class PluginTriggerEvent extends Plugin implements IStageTriggerEvent
{
    use THasHttpIO;
    use THasJsonRpcRequest;
    use THasJsonRpcResponse;

    public const REQUEST__ANCHOR = 'anchor';

    /**
     * @param IActivity $event
     * @throws MissedOrUnknown
     */
    public function __invoke(IActivity &$event): void
    {
        /**
         * @var $anchor IAnchor
         */
        $data = $this->getJsonRpcRequest()->getData();
        $anchorId = $data[static::REQUEST__ANCHOR] ?? '';
        $anchor = $this->anchorRepository()->one([IAnchor::FIELD__ID => $anchorId]);

        if (!$anchor) {
            throw new MissedOrUnknown('anchor "' . $anchorId . '"', 400);
        }

        $this->updateAnchor($anchor);

        $anchorEvent = $anchor->getEvent();
        $event = $anchorEvent ?: $event;
        $event->addParameterByValue(static::REQUEST__ANCHOR, $anchor);
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
}
