<?php
namespace deflou\components\applications\activities;

use deflou\interfaces\applications\activities\IActivity;
use deflou\interfaces\applications\activities\IHasActivity;

/**
 * Trait THasActivity
 *
 * @property array $config
 *
 * @package deflou\components\applications\activities
 * @author jeyroik <jeyroik@gmail.com>
 */
trait THasActivity
{
    /**
     * @return IActivity
     */
    public function getActivity(): IActivity
    {
        return $this->config[IHasActivity::FIELD__ACTIVITY];
    }
}
