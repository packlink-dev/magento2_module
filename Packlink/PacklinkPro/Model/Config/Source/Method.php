<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Model\Config\Source;

use Packlink\PacklinkPro\Model\Carrier;

/**
 * Class Method
 *
 * @package Packlink\PacklinkPro\Model\Config\Source
 */
class Method extends Generic
{
    /**
     * @var Carrier
     */
    private $carrier;

    /**
     * Method constructor.
     *
     * @param Carrier $carrier
     */
    public function __construct(Carrier $carrier)
    {
        $this->carrier = $carrier;
    }

    /**
     * Returns available shipping methods.
     *
     * @return array
     */
    protected function getShippingMethods()
    {
        return $this->carrier->getAllowedMethods();
    }
}
