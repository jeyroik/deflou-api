<?php
namespace deflou\interfaces\triggers;

/**
 * Interface IHasTriggerObject
 *
 * @package deflou\interfaces\triggers
 * @author jeyroik <jeyroik@gmail.com>
 */
interface IHasTriggerObject
{
    public const FIELD__TRIGGER = 'trigger';

    /**
     * @return ITrigger
     */
    public function getTrigger(): ITrigger;
}