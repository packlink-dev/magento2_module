<?php

namespace Packlink\PacklinkPro\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote\Address;
use Magento\Store\Model\ScopeInterface;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\CashOnDelivery\Interfaces\CashOnDeliveryServiceInterface;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Models\ShippingMethod;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Warehouse\WarehouseService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\QueryFilter\Operators;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\QueryFilter\QueryFilter;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\RepositoryRegistry;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;

class FilterPaymentsByShipping implements ObserverInterface
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Bootstrap $bootstrap
     */
    public function __construct(ScopeConfigInterface $scopeConfig, Bootstrap $bootstrap)
    {
        $bootstrap->initInstance();
        $this->scopeConfig = $scopeConfig;
    }

    public function execute(Observer $observer)
    {
        /** @var MethodInterface $method */
        $method = $observer->getEvent()->getMethodInstance();
        /** @var DataObject $result */
        $result = $observer->getEvent()->getResult();
        /** @var CartInterface|null $quote */
        $quote  = $observer->getEvent()->getQuote();

        if (!$quote) {
            return; // leave core decision untouched
        }

        $isOffline = $this->isOffline($method);
        if (!$isOffline) {
            return;
        }

        // Selected shipping method code
        $packlinkMethod = $this->getPacklinkShippingMethod((string)$quote->getShippingAddress()->getShippingMethod());
        if (!$packlinkMethod) {
            return;
        }


        /** @var CashOnDeliveryServiceInterface $cashOnDeliveryService */
        $cashOnDeliveryService = ServiceRegister::getService(CashOnDeliveryServiceInterface::CLASS_NAME);
        $config = $cashOnDeliveryService->getCashOnDeliveryConfig();
        if (!$config || !$config->isActive()) {
            return;
        }

        $selectedOfflineMethod = $config->getAccount()->getOfflinePaymentMethod();

        $paymentCode = $method->getCode();

        if ($selectedOfflineMethod === $paymentCode && !$this->isCashOnDeliveryMethod($packlinkMethod, $quote->getShippingAddress())) {
            $result->setData('is_available', false);
        }
    }

    /**
     * @param ShippingMethod $method
     * @param Address $shippingAddress
     *
     * @return bool
     */
    private function isCashOnDeliveryMethod(ShippingMethod $method, $shippingAddress)
    {
        /** @var WarehouseService $warehouseService */
        $warehouseService = ServiceRegister::getService(WarehouseService::CLASS_NAME);
        $warehouse = $warehouseService->getWarehouse();
        $warehouseCountry = $warehouse->country;

        foreach ($method->getShippingServices() as $service) {
            if ($service->destinationCountry === $shippingAddress->getCountry() && $service->departureCountry === $warehouseCountry) {
                return $service->cashOnDeliveryConfig && $service->cashOnDeliveryConfig->offered;
            }
        }

        return false;
    }

    /**
     * @param string $method
     *
     * @return ShippingMethod|null
     *
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    private function getPacklinkShippingMethod(string $method)
    {
        $id = $this->extractPacklinkId($method);
        if (!$id) {
            return null;
        }

        $repository = RepositoryRegistry::getRepository(ShippingMethod::getClassName());
        $filter = (new QueryFilter())->where('id', Operators::EQUALS, $id);

        return $repository->selectOne($filter);
    }

    /**
     * @param string $method
     *
     * @return int|null
     */
    private function extractPacklinkId(string $method)
    {
        if (preg_match('/^packlink_(\d+)$/', $method, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function isOffline(MethodInterface $method)
    {
        // Prefer official flag if present
        if (method_exists($method, 'isOffline')) {
            return (bool) $method->isOffline();
        }
        // Fallback: non-gateway methods are treated as offline
        $isGateway = method_exists($method, 'isGateway') ? (bool) $method->isGateway() : false;
        return !$isGateway;
    }

    private function matchesAny(string $value, array $patterns)
    {
        foreach ($patterns as $pattern) {
            // Simple wildcard match: '*' -> any chars
            $regex = '/^' . str_replace(['*', '/'], ['.*', '\/'], $pattern) . '$/i';
            if (preg_match($regex, $value) === 1) {
                return true;
            }
        }
        return false;
    }
}
