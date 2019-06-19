<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Model;

use Magento\Framework\Model\AbstractModel;
use Packlink\PacklinkPro\ResourceModel\PacklinkEntity as PacklinkResourceModel;

/**
 * Class PacklinkEntity
 *
 * @package Packlink\PacklinkPro\Model
 */
class PacklinkEntity extends AbstractModel
{
    /**
     * Model initialization.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(PacklinkResourceModel::class);
    }
}
