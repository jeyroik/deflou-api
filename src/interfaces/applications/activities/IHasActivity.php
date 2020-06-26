<?php
namespace deflou\interfaces\applications\activities;

/**
 * Interface IHasActivity
 *
 * @package deflou\interfaces\applications\activities
 * @author jeyroik <jeyroik@gmail.com>
 */
interface IHasActivity
{
    public const FIELD__ACTIVITY = 'activity';

    /**
     * @return IActivity
     */
    public function getActivity(): IActivity;
}
