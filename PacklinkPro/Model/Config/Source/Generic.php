<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Model\Config\Source;

use Magento\Shipping\Model\Carrier\Source\GenericInterface;

/**
 * Class Generic
 *
 * @package Packlink\PacklinkPro\Model\Config\Source
 */
abstract class Generic implements GenericInterface
{
    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        $shippingMethods = $this->getShippingMethods();

        $arr = [];
        foreach ($shippingMethods as $code => $title) {
            $arr[] = ['value' => $code, 'label' => __($title)];
        }

        return $arr;
    }

    /**
     * Returns available shipping methods.
     *
     * @return array
     */
    abstract protected function getShippingMethods();
}
