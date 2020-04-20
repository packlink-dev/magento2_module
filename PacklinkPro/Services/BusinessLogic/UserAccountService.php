<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2020 Packlink
 */

namespace Packlink\PacklinkPro\Services\BusinessLogic;

use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Shipping\Model\Config;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Information;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Country\CountryService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\DTO\Exceptions\FrontDtoValidationException;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\User\UserAccountService as BaseUserAccountService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Warehouse\Warehouse;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;

/**
 * Class UserAccountService
 *
 * @package Packlink\PacklinkPro\Services\BusinessLogic
 */
class UserAccountService extends BaseUserAccountService
{
    /**
     * Singleton instance of this class.
     *
     * @var static
     */
    protected static $instance;
    /**
     * Core store config
     *
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var \Magento\Backend\Model\Auth\Session
     */
    private $authSession;
    /**
     * @var \Magento\Store\Model\Information
     */
    private $storeInfo;

    /**
     * UserAccountService constructor.
     *
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Backend\Model\Auth\Session $authSession
     * @param \Magento\Store\Model\Information $storeInfo
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        Session $authSession,
        Information $storeInfo
    ) {
        parent::__construct();

        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->authSession = $authSession;
        $this->storeInfo = $storeInfo;
    }

    /**
     * Internal method for setting warehouse info in integrations.
     * If integration set it, Core will not fetch the info from Packlink API.
     *
     * @return bool
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function setWarehouseInfoInternal()
    {
        /** @var \Magento\Store\Model\Store $store */
        $store = $this->storeManager->getStore();
        $originCountry = $this->getScopeConfigValue(Config::XML_PATH_ORIGIN_COUNTRY_ID, $store);
        $user = $this->authSession->getUser();
        $storeInfo = $this->storeInfo->getStoreInformationObject($store);
        /** @var CountryService $countryService */
        $countryService = ServiceRegister::getService(CountryService::CLASS_NAME);

        if (!$countryService->isCountrySupported($originCountry)) {
            return false;
        }

        $userInfo = $this->getConfigService()->getUserInfo();
        $originAddress = $this->getScopeConfigValue('shipping/origin/street_line1', $store);
        $secondaryStreetLine = $this->getScopeConfigValue('shipping/origin/street_line2', $store);

        if (!empty($secondaryStreetLine)) {
            $originAddress .= ' ' . $secondaryStreetLine;
        }

        try {
            $warehouse = Warehouse::fromArray(
                [
                    'alias' => $storeInfo->getData('name'),
                    'name' => $user && $user->getFirstName() ? $user->getFirstName() : $userInfo->firstName,
                    'surname' => $user && $user->getLastName() ? $user->getLastName() : $userInfo->lastName,
                    'country' => $originCountry,
                    'postal_code' => $this->getScopeConfigValue(Config::XML_PATH_ORIGIN_POSTCODE, $store),
                    'city' => $this->getScopeConfigValue(Config::XML_PATH_ORIGIN_CITY, $store),
                    'address' => $originAddress,
                    'phone' => $storeInfo->getData('phone'),
                    'email' => $user && $user->getEmail() ? $user->getEmail() : $userInfo->email,
                ]
            );
        } catch (FrontDtoValidationException $e) {
            return false;
        }

        $this->getConfigService()->setDefaultWarehouse($warehouse);

        return true;
    }

    /**
     * Returns scope configuration value for the provided path.
     *
     * @param string $path Scope config value path.
     * @param StoreInterface $store Store.
     *
     * @return mixed
     */
    private function getScopeConfigValue($path, $store)
    {
        $scopeConfigValue = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $store);

        return $scopeConfigValue ?: '';
    }
}
