<?php
namespace deflou\components\parsers;

use deflou\components\plugins\triggers\PluginEnrichTrigger;
use deflou\interfaces\applications\activities\IActivity;
use deflou\interfaces\triggers\ITrigger;

/**
 * Trait TEnrichTriggerParser
 *
 * @property array $config
 *
 * @package deflou\components\parsers
 * @author jeyroik@gmail.com
 */
trait TEnrichTriggerParser
{
    /**
     * @return ITrigger
     */
    public function getTrigger(): ITrigger
    {
        return $this->config[PluginEnrichTrigger::FIELD__TRIGGER];
    }

    /**
     * @return IActivity
     */
    public function getTriggerEvent(): IActivity
    {
        return $this->config[PluginEnrichTrigger::FIELD__EVENT];
    }
}
