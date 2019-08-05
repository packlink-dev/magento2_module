<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\ResourceModel\PacklinkEntity;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Packlink\PacklinkPro\Model\PacklinkEntity;
use Packlink\PacklinkPro\ResourceModel\PacklinkEntity as PacklinkResourceModel;

/**
 * Class Collection
 *
 * @package Packlink\PacklinkPro\ResourceModel\PacklinkEntity
 */
class Collection extends AbstractCollection
{
    /**
     * Collection initialization.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(PacklinkEntity::class, PacklinkResourceModel::class);
    }
}
