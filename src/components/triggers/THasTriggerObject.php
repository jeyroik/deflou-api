<?php
namespace deflou\components\triggers;

use deflou\interfaces\triggers\IHasTriggerObject;
use deflou\interfaces\triggers\ITrigger;

/**
 * Trait THasTriggerObject
 *
 * @property array $config
 *
 * @package deflou\components\triggers
 * @author jeyroik <jeyroik@gmail.com>
 */
trait THasTriggerObject
{
    /**
     * @return ITrigger
     */
    public function getTrigger(): ITrigger
    {
        return $this->config[IHasTriggerObject::FIELD__TRIGGER];
    }
}
