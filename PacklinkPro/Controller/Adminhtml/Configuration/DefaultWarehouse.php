<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Webapi\Exception;
use Magento\Shipping\Model\Config;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Http\DTO\User;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Http\DTO\Warehouse;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Http\Proxy;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Location\LocationService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;

/**
 * Class DefaultWarehouse
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Configuration
 */
class DefaultWarehouse extends Configuration
{
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
     * DefaultWarehouse constructor.
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Packlink\PacklinkPro\Bootstrap $bootstrap
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Context $context,
        Bootstrap $bootstrap,
        JsonFactory $jsonFactory,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context, $bootstrap, $jsonFactory);

        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;

        $this->allowedActions = [
            'getDefaultWarehouse',
            'setDefaultWarehouse',
            'searchPostalCodes',
        ];

        $this->fields = [
            'alias',
            'name',
            'surname',
            'country',
            'postal_code',
            'address',
            'phone',
            'email',
        ];
    }

    /**
     * Returns default warehouse data.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function getDefaultWarehouse()
    {
        $warehouse = $this->getConfigService()->getDefaultWarehouse();

        if (!$warehouse) {
            $userInfo = $this->getConfigService()->getUserInfo();

            if ($userInfo === null) {
                return $this->result;
            }

            $warehouse = $this->getStoreWarehouseInfo($userInfo);
        }

        return $this->result->setData($warehouse->toArray());
    }

    /**
     * Sets warehouse data.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function setDefaultWarehouse()
    {
        $data = $this->getPacklinkPostData();

        $validationResult = $this->validate($data);
        if (!empty($validationResult)) {
            $this->result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);

            return $this->result->setData($validationResult);
        }

        $data['default'] = true;
        $warehouse = Warehouse::fromArray($data);
        $this->getConfigService()->setDefaultWarehouse($warehouse);

        return $this->result->setData($data);
    }

    /**
     * Performs location search.
     *
     * @return array|\Magento\Framework\Controller\Result\Json
     */
    protected function searchPostalCodes()
    {
        $input = $this->getPacklinkPostData();

        if (empty($input['query'])) {
            return $this->result;
        }

        /** @var LocationService $locationService */
        $locationService = ServiceRegister::getService(LocationService::CLASS_NAME);

        $platformCountry = $this->getConfigService()->getUserInfo()->country;
        try {
            $locations = $locationService->searchLocations($platformCountry, $input['query']);
        } catch (\Exception $e) {
            return $this->result;
        }

        $resultLocations = [];
        foreach ($locations as $location) {
            $resultLocations[] = $location->toArray();
        }

        return $this->result->setData($resultLocations);
    }

    /**
     * Returns store warehouse information if country matches Packlink user platform country.
     * Otherwise returns only platform country for the user as default warehouse data.
     *
     * @param User $userInfo User information object.
     *
     * @return Warehouse Default warehouse information.
     */
    private function getStoreWarehouseInfo($userInfo)
    {
        $store = $this->storeManager->getStore();
        $originCountry = $this->getScopeConfigValue(Config::XML_PATH_ORIGIN_COUNTRY_ID, $store);

        if ($originCountry === $userInfo->country) {
            $originAddress = $this->getScopeConfigValue('shipping/origin/street_line1', $store);

            $secondaryStreetLine = $this->getScopeConfigValue('shipping/origin/street_line2', $store);

            if (!empty($secondaryStreetLine)) {
                $originAddress .= ' ' . $secondaryStreetLine;
            }

            $warehouse = Warehouse::fromArray(
                [
                    'country' => $originCountry,
                    'postal_code' => $this->getScopeConfigValue(Config::XML_PATH_ORIGIN_POSTCODE, $store),
                    'city' => $this->getScopeConfigValue(Config::XML_PATH_ORIGIN_CITY, $store),
                    'address' => $originAddress,
                ]
            );
        } else {
            /** @noinspection NullPointerExceptionInspection */
            $warehouse = Warehouse::fromArray(['country' => $userInfo->country]);
        }

        return $warehouse;
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

    /**
     * Validates warehouse data.
     *
     * @param array $data Warehouse data.
     *
     * @return array Validation result.
     */
    private function validate(array $data)
    {
        $result = [];

        foreach ($this->fields as $field) {
            if (empty($data[$field])) {
                $result[$field] = __('Field is required.');
            }
        }

        if (!empty($data['country']) && !empty($data['postal_code'])) {
            try {
                $proxy = ServiceRegister::getService(Proxy::CLASS_NAME);
                $postalCodes = $proxy->getPostalCodes($data['country'], $data['postal_code']);
                if (empty($postalCodes)) {
                    $result['postal_code'] = __('Postal code is not correct.');
                }
            } catch (\Exception $e) {
                $result['postal_code'] = __('Postal code is not correct.');
            }
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $result['email'] = __('Field must be valid email.');
        }

        if (!empty($data['phone'])) {
            $regex = '/^(\+|\/|\.|-|\(|\)|\d)+$/m';
            $phoneError = !preg_match($regex, $data['phone']);

            $digits = '/\d/m';
            $match = preg_match_all($digits, $data['phone']);
            $phoneError |= $match === false || $match < 3;

            if ($phoneError) {
                $result['phone'] = __('Field must be valid phone number.');
            }
        }

        return $result;
    }
}
