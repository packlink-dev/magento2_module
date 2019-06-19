<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Test\Unit\Repository;

use Packlink\PacklinkPro\Repository\QueueItemRepository;

/**
 * Class TestQueueItemRepository
 *
 * @package Packlink\PacklinkPro\Test\Unit\Repository
 */
class TestQueueItemRepository extends QueueItemRepository
{
    /**
     * Fully qualified name of this class.
     */
    const THIS_CLASS_NAME = __CLASS__;
    /**
     * Name of the base entity table in database.
     */
    const TABLE_NAME = 'packlink_test';
}
