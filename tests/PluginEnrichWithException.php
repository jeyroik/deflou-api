<?php
namespace tests;

use deflou\interfaces\applications\activities\IActivity;
use deflou\interfaces\stages\IStageDeFlouTriggerEnrich;
use deflou\interfaces\triggers\ITrigger;
use extas\components\plugins\Plugin;

/**
 * Class PluginEnrichSample
 *
 * @package tests
 * @author jeyroik@gmail.com
 */
class PluginEnrichWithException extends Plugin implements IStageDeFlouTriggerEnrich
{
    public const EXCEPTION__MESSAGE = 'Worked enrich';

    /**
     * @param IActivity $action
     * @param IActivity $event
     * @param ITrigger $trigger
     * @throws \Exception
     */
    public function __invoke(IActivity $action, IActivity $event, ITrigger &$trigger): void
    {
        throw new \Exception(static::EXCEPTION__MESSAGE);
    }
}
