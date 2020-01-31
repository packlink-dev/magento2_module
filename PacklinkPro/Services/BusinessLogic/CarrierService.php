<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2020 Packlink
 */

namespace Packlink\PacklinkPro\Services\BusinessLogic;

use Magento\Framework\View\Asset\Repository;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Configuration;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Models\ShippingMethod;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;

/**
 * Class CarrierService
 *
 * @package Packlink\PacklinkPro\Services\BusinessLogic
 */
class CarrierService implements ShopShippingMethodService
{
    /**
     * @var Repository
     */
    protected $assetRepo;

    /**
     * CarrierService constructor.
     *
     * @param Repository $assetRepo
     */
    public function __construct(Repository $assetRepo)
    {
        $this->assetRepo = $assetRepo;
    }

    /**
     * Returns carrier logo file path of shipping method with provided ID.
     * If logo doesn't exist returns default carrier logo.
     *
     * @param string $carrierName Name of the carrier.
     *
     * @return string Logo file path.
     */
    public function getCarrierLogoFilePath($carrierName)
    {
        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        $userInfo = $configService->getUserInfo();

        if ($userInfo === null) {
            return $this->getDefaultCarrierLogoPath();
        }

        $carrierLogoFile = strtolower(str_replace(' ', '-', $carrierName)) . '.png';

        $file = $this->assetRepo->createAsset(
            'Packlink_PacklinkPro::images/carriers/' . strtolower($userInfo->country) . '/' . $carrierLogoFile
        );

        if (file_exists($file->getPath())) {
            return $file->getUrl();
        }

        return $this->getDefaultCarrierLogoPath();
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

    /**
     * Returns path to default carrier logo.
     *
     * @return string
     */
    private function getDefaultCarrierLogoPath()
    {
        return $this->assetRepo->getUrl('Packlink_PacklinkPro::images/carriers/carrier.jpg');
    }
}
