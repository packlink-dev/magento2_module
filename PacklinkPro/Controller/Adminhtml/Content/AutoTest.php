<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\Content;

/**
 * Class AutoTest.
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Content
 */
class AutoTest extends Routing
{
    // /**
    //  * @inheritDoc
    //  */
    // public function _processUrlKeys()
    // {
    //     return $this->_auth->isLoggedIn();
    // }

    /**
     * @inheritDoc
     */
    protected function redirectIfNecessary($currentAction, $redirectUrl)
    {
        return null;
    }
}
