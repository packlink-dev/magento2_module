<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\ResourceModel\QueueItemEntity;

use Packlink\PacklinkPro\Model\QueueItemEntity;
use Packlink\PacklinkPro\ResourceModel\PacklinkEntity\Collection as PacklinkEntityCollection;
use Packlink\PacklinkPro\ResourceModel\QueueItemEntity as QueueItemResourceModel;

/**
 * Class Collection
 *
 * @package Packlink\PacklinkPro\ResourceModel\QueueItemEntity
 */
class Collection extends PacklinkEntityCollection
{
    /**
     * Collection initialization.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(QueueItemEntity::class, QueueItemResourceModel::class);
    }
}
