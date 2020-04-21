<?php
namespace deflou\components\plugins\triggers;

use deflou\interfaces\applications\activities\IActivity;
use deflou\interfaces\enrichments\IEnrichment;
use deflou\interfaces\enrichments\IEnrichmentRepository;
use deflou\interfaces\triggers\ITrigger;
use extas\components\plugins\Plugin;
use extas\components\SystemContainer;

/**
 * Class PluginEnrichTrigger
 *
 * @package deflou\components\plugins\triggers
 * @author jeyroik <jeyroik@gmail.com>
 */
class PluginEnrichTrigger extends Plugin
{
    /**
     * @param ITrigger $trigger
     * @param IActivity $event
     */
    public function __invoke(ITrigger $trigger, IActivity $event)
    {
        /**
         * @var $repo IEnrichmentRepository
         * @var $enrichments IEnrichment[]
         */
        $repo = SystemContainer::getItem(IEnrichmentRepository::class);
        $enrichments = $repo->all([]);
        $parameters = $trigger->getActionParameters();

        foreach ($enrichments as $enrichment) {
            foreach ($parameters as $parameter) {
                $value = $enrichment->enrich(
                    $parameter->getValue(),
                    $trigger->getPlayer(),
                    $event->getParametersValues()
                );
                $trigger->setParameterValue($parameter->getName(), $value);
            }
        }
    }
}
