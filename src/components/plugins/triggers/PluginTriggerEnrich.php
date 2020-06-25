<?php
namespace deflou\components\plugins\triggers;

use deflou\components\applications\activities\THasActivity;
use deflou\interfaces\stages\IStageTriggerEnrich;
use deflou\interfaces\triggers\ITrigger;
use extas\components\http\THasHttpIO;
use extas\components\jsonrpc\THasJsonRpcRequest;
use extas\components\jsonrpc\THasJsonRpcResponse;
use extas\components\plugins\Plugin;
use extas\interfaces\http\IHasPsrRequest;
use extas\interfaces\http\IHasPsrResponse;
use extas\interfaces\parsers\IParser;
use extas\interfaces\repositories\IRepository;

/**
 * Class PluginTriggerEnrich
 *
 * @method IRepository parserRepository()
 *
 * @package deflou\components\plugins\triggers
 * @author jeyroik <jeyroik@gmail.com>
 */
class PluginTriggerEnrich extends Plugin implements IStageTriggerEnrich
{
    public const FIELD__TRIGGER = 'trigger';
    public const FIELD__EVENT = 'event';
    public const FIELD__REQUEST = 'request';

    use THasHttpIO;
    use THasJsonRpcRequest;
    use THasJsonRpcResponse;
    use THasActivity;

    public function __invoke(ITrigger &$trigger): void
    {
        /**
         * @var $parsers IParser[]
         */
        $parsers = $this->parserRepository()->all([]);
        $parameters = $trigger->getActionParameters();
        $event = $this->getActivity();

        foreach ($parsers as $parser) {
            foreach ($parameters as &$parameter) {
                $parser[static::FIELD__TRIGGER] = $trigger;
                $parser[static::FIELD__EVENT] = $event;
                $parser[IHasPsrRequest::FIELD__PSR_REQUEST] = $this->getPsrRequest();
                $parser[IHasPsrResponse::FIELD__PSR_RESPONSE] = $this->getPsrResponse();

                if ($parser->canParse($parameter->getValue())) {
                    $value = $parser->parse($parameter->getValue());
                    $parameter->setValue($value);
                }
            }
        }
        $trigger->setActionParameters($parameters);
    }
}
