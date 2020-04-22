<?php
namespace deflou\components\plugins\triggers;

use deflou\interfaces\applications\activities\IActivity;
use deflou\interfaces\stages\IStageDeFlouTriggerEnrich;
use deflou\interfaces\triggers\ITrigger;
use extas\components\plugins\Plugin;
use extas\components\SystemContainer;
use extas\interfaces\parsers\IParser;
use extas\interfaces\parsers\IParserRepository;
use extas\interfaces\repositories\IRepository;

/**
 * Class PluginEnrichTrigger
 *
 * @package deflou\components\plugins\triggers
 * @author jeyroik <jeyroik@gmail.com>
 */
class PluginEnrichTrigger extends Plugin implements IStageDeFlouTriggerEnrich
{
    public const FIELD__TRIGGER = 'trigger';
    public const FIELD__EVENT = 'event';

    /**
     * @param IActivity $action
     * @param IActivity $event
     * @param ITrigger $trigger
     */
    public function __invoke(IActivity $action, IActivity $event, ITrigger &$trigger): void
    {
        /**
         * @var $repo IRepository
         * @var $parsers IParser[]
         */
        $repo = SystemContainer::getItem(IParserRepository::class);
        $parsers = $repo->all([]);
        $parameters = $trigger->getActionParameters();

        foreach ($parsers as $parser) {
            foreach ($parameters as &$parameter) {
                $parser[static::FIELD__TRIGGER] = $trigger;
                $parser[static::FIELD__EVENT] = $event->getParametersValues();

                if ($parser->canParse($parameter->getValue())) {
                    $value = $parser->parse($parameter->getValue());
                    $parameter->setValue($value);
                }
            }
        }
        $trigger->setActionParameters($parameters);
    }
}
