<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */
/** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace Packlink\PacklinkPro\Model;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Store\Model\StoreManagerInterface;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Configuration;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Http\DTO\Package;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Http\DTO\ParcelInfo;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Models\ShippingMethod;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\ShippingCostCalculator;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\ShippingMethodService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\Services\BusinessLogic\ConfigurationService;

/**
 * Class Carrier
 *
 * @package Packlink\PacklinkPro\Model
 */
class Carrier extends AbstractCarrier implements CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'packlink';
    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
     */
    private $rateMethodFactory;
    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory
     */
    private $rateResultFactory;
    /**
     * @var ShippingMethodService
     */
    private $shippingMethodsService;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $methodFactory,
        \Packlink\PacklinkPro\Bootstrap $bootstrap,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);

        $this->rateMethodFactory = $methodFactory;
        $this->rateResultFactory = $rateFactory;
        $this->storeManager = $storeManager;

        $bootstrap->initInstance();
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     * @api
     */
    public function getAllowedMethods()
    {
        $methods = [];
        $activeMethods = $this->getShippingMethodService()->getActiveMethods();
        if (!empty($activeMethods)) {
            $activeMethods[] = $this->createBackupMethod();
        }

        foreach ($activeMethods as $method) {
            $methods[] = $method->getTitle();
        }

        return $methods;
    }

    /**
     * Collect and get rates
     *
     * @param RateRequest $request
     *
     * @return \Magento\Shipping\Model\Rate\Result
     * @api
     */
    public function collectRates(RateRequest $request)
    {
        $result = $this->rateResultFactory->create();

        if (!$this->isActive()) {
            return $result;
        }

        $data = $request->getData();
        if (isset($data['dest_country_id'], $data['dest_postcode'], $data['package_value'], $data['all_items'])
            && $this->getShippingMethodService()->isAnyMethodActive()
        ) {
            $this->collectRatesForActiveMethods($data, $result);
        }

        return $result;
    }

    /**
     * Determine whether zip-code is required for the country of destination
     *
     * @param string|null $countryId
     *
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function isZipCodeRequired($countryId = null)
    {
        return true;
    }

    /**
     * Check if carrier has shipping tracking option available
     *
     * @return bool
     */
    public function isTrackingAvailable()
    {
        return true;
    }

    /**
     * Gets shipping costs for Packlink methods.
     *
     * @param array $data Request data.
     * @param \Magento\Shipping\Model\Rate\Result $result Response result.
     */
    private function collectRatesForActiveMethods($data, \Magento\Shipping\Model\Rate\Result $result)
    {
        $activeMethods = $this->getShippingMethodService()->getActiveMethods();
        $calculatedCosts = $this->calculateShippingCosts($activeMethods, $data);

        if (empty($calculatedCosts)) {
            $backupService = $this->getBackupService($data);
            if ($backupService) {
                $result->append($backupService);
            }

            return;
        }

        foreach ($activeMethods as $activeMethod) {
            if (!$activeMethod->isShipToAllCountries()
                && !in_array($data['dest_country_id'], $activeMethod->getShippingCountries(), true)
            ) {
                continue;
            }

            if (isset($calculatedCosts[$activeMethod->getId()])) {
                $result->append($this->getRateMethod($activeMethod, $calculatedCosts[$activeMethod->getId()]));
            }
        }
    }

    /**
     * Returns backup Packlink shipping service if any of the Packlink services
     * support delivery to the selected location and for the given parcels.
     *
     * @param array $data Request data to get destination and parcel from.
     *
     * @return \Magento\Quote\Model\Quote\Address\RateResult\Method Backup shipping service.
     */
    private function getBackupService($data)
    {
        $allCosts = $this->calculateShippingCosts($this->getShippingMethodService()->getAllMethods(), $data);

        if (!empty($allCosts)) {
            $backupMethod = $this->createBackupMethod();

            return $this->getRateMethod($backupMethod, min(array_values($allCosts)));
        }

        return null;
    }

    /**
     * Returns rate result method.
     *
     * @param ShippingMethod $activeMethod Active Packlink shipping method.
     * @param float $calculatedCost Calculated shipping cost.
     *
     * @return \Magento\Quote\Model\Quote\Address\RateResult\Method Rate result method.
     */
    private function getRateMethod(ShippingMethod $activeMethod, $calculatedCost)
    {
        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->rateMethodFactory->create();
        $method->setData('carrier', $this->_code)
            ->setData('carrier_title', $this->getConfigData('title'))
            ->setData('method', $activeMethod->getId())
            ->setData('method_title', $activeMethod->getTitle())
            ->setData('cost', $calculatedCost)
            ->setPrice($calculatedCost);

        return $method;
    }

    /**
     * Calculate shipping costs for all active Packlink shop shipping methods.
     *
     * @param ShippingMethod[] $methods Array of active shipping methods.
     * @param array $data Request data to get destination and parcel from.
     *
     * @return array Array of calculated shipping costs.
     */
    private function calculateShippingCosts($methods, $data)
    {
        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);

        $warehouse = $configService->getDefaultWarehouse();
        if ($warehouse === null) {
            return [];
        }

        $store = $this->storeManager->getStore();

        return ShippingCostCalculator::getShippingCosts(
            $methods,
            $warehouse->country,
            $warehouse->postalCode,
            $data['dest_country_id'],
            $data['dest_postcode'],
            $this->getPackages($data['package_weight'], $data['package_qty'] ?: 1),
            $data['package_physical_value'],
            ($store !== null) ? (string)$store->getId() : null
        );
    }

    /**
     * Returns prepared packages for shipping cost calculation.
     *
     * @param float $weight Total order weight.
     * @param float $quantity Number of packages.
     *
     * @return Package[] Array of Packlink package entities.
     */
    private function getPackages($weight, $quantity)
    {
        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);

        $parcel = $configService->getDefaultParcel() ?: ParcelInfo::defaultParcel();
        $packages = [];
        $weight = $weight ? $weight : $parcel->weight;

        for ($i = 0; $i < $quantity; $i++) {
            $packages[] = new Package(
                round($weight / $quantity, 2),
                $parcel->width,
                $parcel->height,
                $parcel->length
            );
        }

        return $packages;
    }

    /**
     * Creates backup shipping method.
     *
     * @return \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Models\ShippingMethod
     */
    private function createBackupMethod()
    {
        return ShippingMethod::fromArray(
            [
                'id' => 'backup',
                'title' => 'shipping cost',
            ]
        );
    }

    /**
     * Returns instance of shipping method service.
     *
     * @return ShippingMethodService
     */
    private function getShippingMethodService()
    {
        if ($this->shippingMethodsService === null) {
            $this->shippingMethodsService = ServiceRegister::getService(ShippingMethodService::CLASS_NAME);
        }

        return $this->shippingMethodsService;
    }
}
