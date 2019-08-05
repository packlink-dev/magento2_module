<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Entity;

use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Configuration\EntityConfiguration;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Configuration\IndexMap;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Entity;

/**
 * Class QuoteCarrierDropOffMapping
 *
 * @package Packlink\PacklinkPro\Entity
 */
class QuoteCarrierDropOffMapping extends Entity
{
    /**
     * Fully qualified name of this class.
     */
    const CLASS_NAME = __CLASS__;
    /**
     * Type of the entity.
     */
    const TYPE = 'QuoteCarrierDropOffMapping';
    /**
     * @var int
     */
    protected $quoteId;
    /**
     * @var int
     */
    protected $carrierId;
    /**
     * @var array
     */
    protected $dropOff;
    /**
     * @var int
     */
    protected $addressId;
    /**
     * List of entity fields.
     *
     * @var array
     */
    protected $fields = ['id', 'quoteId', 'carrierId', 'dropOff', 'addressId'];

    /**
     * Returns entity configuration object.
     *
     * @return EntityConfiguration Configuration object.
     */
    public function getConfig()
    {
        $map = new IndexMap();
        $map->addIntegerIndex('quoteId');
        $map->addIntegerIndex('carrierId');
        $map->addIntegerIndex('addressId');

        return new EntityConfiguration($map, self::TYPE);
    }

    /**
     * Returns quote ID.
     *
     * @return int
     */
    public function getQuoteId()
    {
        return $this->quoteId;
    }

    /**
     * Sets quote ID.
     *
     * @param int $quoteId
     */
    public function setQuoteId($quoteId)
    {
        $this->quoteId = $quoteId;
    }

    /**
     * Returns carrier ID.
     *
     * @return int
     */
    public function getCarrierId()
    {
        return $this->carrierId;
    }

    /**
     * Sets carrier ID.
     *
     * @param int $carrierId
     */
    public function setCarrierId($carrierId)
    {
        $this->carrierId = $carrierId;
    }

    /**
     * Returns drop-off information.
     *
     * @return array
     */
    public function getDropOff()
    {
        return $this->dropOff;
    }

    /**
     * Sets drop-off information.
     *
     * @param array $dropOff
     */
    public function setDropOff($dropOff)
    {
        $this->dropOff = $dropOff;
    }

    /**
     * Returns address ID.
     *
     * @return int
     */
    public function getAddressId()
    {
        return $this->addressId;
    }

    /**
     * Sets address ID.
     *
     * @param int $addressId
     */
    public function setAddressId($addressId)
    {
        $this->addressId = $addressId;
    }
}
