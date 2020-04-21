<?php
namespace deflou\interfaces\stages;

use deflou\interfaces\applications\activities\IActivity;
use deflou\interfaces\triggers\ITrigger;

/**
 * Interface IStageDeFlouTriggerEnrich
 *
 * Rising to enrich trigger on application event creating.
 * See deflou\components\jsonrpc\operations\CreateEvent::enrichTrigger() for details.
 *
 * @package deflou\interfaces\stages
 * @author jeyroik@gmail.com
 */
interface IStageDeFlouTriggerEnrich
{
    public const NAME = 'deflou.trigger.enrich';

    /**
     * @param IActivity $action
     * @param IActivity $event
     * @param ITrigger $trigger
     */
    public function __invoke(IActivity $action, IActivity $event, ITrigger &$trigger): void;
}
