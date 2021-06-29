<?php

namespace Packlink\PacklinkPro\Services\BusinessLogic;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Store;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Http\DTO\SystemInfo;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\SystemInformation\SystemInfoService as SystemInfoInterface;

/**
 * Class SystemInfoService
 *
 * @package Packlink\PacklinkPro\Services\BusinessLogic
 */
class SystemInfoService implements SystemInfoInterface
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * SystemInfoService constructor.
     *
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(StoreManagerInterface $storeManager)
    {
        $this->storeManager = $storeManager;
    }

    /**
     * Returns system information.
     *
     * @return SystemInfo[]
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getSystemDetails()
    {
        /** @var Store[] $stores */
        $stores = $this->storeManager->getStores(true);
        $systemDetails = [];

        foreach ($stores as $store) {
            if ($store->getCode() !== 'admin') {
                $systemDetails[] = SystemInfo::fromArray([
                    'system_id' => $store->getId(),
                    'system_name' => $store->getName() . ' (' . $store->getWebsite()->getName() . ')',
                    'currencies' => [$store->getCurrentCurrencyCode()],
                ]);
            }
        }

        return $systemDetails;
    }

    /**
     * Returns system information for a particular system, identified by the system ID.
     *
     * @param string $systemId
     *
     * @return SystemInfo|null
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getSystemInfo($systemId)
    {
        $store = $this->storeManager->getStore($systemId);

        if ($store === null) {
            return null;
        }

        return SystemInfo::fromArray([
            'system_id' => $store->getId(),
            'system_name' => $store->getName() . ' (' . $store->getWebsite()->getName() . ')',
            'currencies' => [$store->getCurrentCurrencyCode()],
        ]);
    }
}
