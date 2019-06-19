<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Model;

use Packlink\PacklinkPro\ResourceModel\QueueItemEntity as QueueItemResourceModel;

/**
 * Class QueueItemEntity
 *
 * @package Packlink\PacklinkPro\Model
 */
class QueueItemEntity extends PacklinkEntity
{
    /**
     * Model initialization.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(QueueItemResourceModel::class);
    }
}
