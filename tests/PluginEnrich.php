<?php
namespace tests;

use deflou\interfaces\applications\activities\IActivity;
use deflou\interfaces\stages\IStageDeFlouTriggerEnrich;
use deflou\interfaces\triggers\ITrigger;
use extas\components\plugins\Plugin;
use extas\components\samples\parameters\SampleParameter;

/**
 * Class PluginEnrich
 *
 * @package tests
 * @author jeyroik@gmail.com
 */
class PluginEnrich extends Plugin implements IStageDeFlouTriggerEnrich
{
    /**
     * @param IActivity $action
     * @param IActivity $event
     * @param ITrigger $trigger
     */
    public function __invoke(IActivity $action, IActivity $event, ITrigger &$trigger): void
    {
        $trigger->setActionParameters([
            new SampleParameter([
                SampleParameter::FIELD__NAME => 'test',
                SampleParameter::FIELD__VALUE => 'ok'
            ])
        ]);
    }
}
