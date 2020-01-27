<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Services\BusinessLogic;

use Packlink\PacklinkPro\Helper\CarrierLogoHelper;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Models\ShippingMethod;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\ShippingMethodService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;

/**
 * Class CarrierService
 *
 * @package Packlink\PacklinkPro\Services\BusinessLogic
 */
class CarrierService implements ShopShippingMethodService
{
    /**
     * @var CarrierLogoHelper
     */
    private $carrierLogoHelper;

    /**
     * CarrierService constructor.
     *
     * @param \Packlink\PacklinkPro\Helper\CarrierLogoHelper $carrierLogoHelper
     */
    public function __construct(CarrierLogoHelper $carrierLogoHelper)
    {
        $this->carrierLogoHelper = $carrierLogoHelper;
    }

    /**
     * Returns carrier logo file path for shipping method with a given ID.
     *
     * @param int $id Shipping method ID.
     *
     * @return string
     */
    public function getCarrierLogoById($id)
    {
        /** @var ShippingMethodService $shippingMethodService */
        $shippingMethodService = ServiceRegister::getService(ShippingMethodService::CLASS_NAME);
        /** @var ShippingMethod $shippingMethod */
        $shippingMethod = $shippingMethodService->getShippingMethod($id);

        return $this->getCarrierLogoFilePath($shippingMethod->getCarrierName());
    }

    /**
     * @inheritDoc
     */
    public function getCarrierLogoFilePath($carrierName)
    {
        return $this->carrierLogoHelper->getCarrierLogoFilePath($carrierName);
    }

    /**
     * Adds / Activates shipping method in shop integration.
     *
     * @param ShippingMethod $shippingMethod Shipping method.
     *
     * @return bool TRUE if activation succeeded; otherwise, FALSE.
     */
    public function add(ShippingMethod $shippingMethod)
    {
        return true;
    }

    /**
     * Updates shipping method in shop integration.
     *
     * @param ShippingMethod $shippingMethod Shipping method.
     */
    public function update(ShippingMethod $shippingMethod)
    {
    }

    /**
     * Deletes shipping method in shop integration.
     *
     * @param ShippingMethod $shippingMethod Shipping method.
     *
     * @return bool TRUE if deletion succeeded; otherwise, FALSE.
     */
    public function delete(ShippingMethod $shippingMethod)
    {
        return true;
    }

    /**
     * Adds backup shipping method based on provided shipping method.
     *
     * @param ShippingMethod $shippingMethod
     *
     * @return bool TRUE if backup shipping method is added; otherwise, FALSE.
     */
    public function addBackupShippingMethod(ShippingMethod $shippingMethod)
    {
        return true;
    }

    /**
     * Deletes backup shipping method.
     *
     * @return bool TRUE if backup shipping method is deleted; otherwise, FALSE.
     */
    public function deleteBackupShippingMethod()
    {
        return true;
    }
}
