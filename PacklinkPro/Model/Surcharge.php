<?php

namespace Packlink\PacklinkPro\Model;

use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Total as QuoteTotal;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\CashOnDelivery\Interfaces\CashOnDeliveryServiceInterface;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Models\ShippingMethod;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Warehouse\WarehouseService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\QueryFilter\Operators;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\QueryFilter\QueryFilter;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\RepositoryRegistry;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;

class Surcharge extends AbstractTotal
{
    const CODE = 'packlink_surcharge';

    public function __construct(Bootstrap $bootstrap)
    {
        $bootstrap->initInstance();
        $this->setCode(self::CODE);
    }

    /**
     * Collector: calculates and adds surcharge to totals (and grand total).
     */
    public function collect(Quote $quote, ShippingAssignmentInterface $shippingAssignment, QuoteTotal $total)
    {
        parent::collect($quote, $shippingAssignment, $total);

        // Always reset our buckets on each pass
        $total->setTotalAmount(self::CODE, 0.0);
        $total->setBaseTotalAmount(self::CODE, 0.0);

        // If there are no items for this address, skip
        if (!$shippingAssignment->getItems()) {
            return $this;
        }

        // Compute amounts based on current quote state
        $amounts = $this->computeSurchargeForRequest($quote, $total);
        if ($amounts === null) {
            return $this;
        }

        // Buckets (these drive total_segments)
        $total->addBaseTotalAmount(self::CODE, $amounts['base']);
        $total->addTotalAmount(self::CODE, $amounts['quote']);

        // Include in math
        $total->setBaseGrandTotal($total->getBaseGrandTotal() + $amounts['base']);
        $total->setGrandTotal($total->getGrandTotal() + $amounts['quote']);

        // Expose on Total/Address (optional but handy)
        $total->setData('base_' . self::CODE . '_amount', $amounts['base']);
        $total->setData(self::CODE . '_amount', $amounts['quote']);

        $addr = $quote->getIsVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
        if ($addr) {
            $addr->setData('base_surcharge_amount', $amounts['base']);
            $addr->setData('surcharge_amount', $amounts['quote']);
        }

        return $this;
    }

    /**
     * Fetcher: returns the segment. If the bucket is empty (new request),
     * it recomputes from the current quote state so it never depends on previous requests.
     */
    public function fetch(Quote $quote, QuoteTotal $total)
    {
        // Try the bucket first (works when same Total instance flows through)
        $value = (float)$total->getTotalAmount(self::CODE);

        // If empty (fresh Total instance), recompute cheaply
        if ($value <= 0.0001) {
            $amounts = $this->computeSurchargeForRequest($quote, $total);
            if ($amounts === null || $amounts['quote'] <= 0) {
                return null;
            }
            $value = $amounts['quote'];
        }

        return [
            'code'  => self::CODE,
            'title' => __('Payment Surcharge'),
            'value' => $value,
        ];
    }

    public function getLabel()
    {
        return __('Payment Surcharge');
    }

    /**
     * Single source of truth for surcharge computation (used by both collect() and fetch()).
     * Returns ['base' => float, 'quote' => float] or null if not applicable.
     */
    private function computeSurchargeForRequest(Quote $quote, QuoteTotal $total)
    {
        if ($quote->getIsVirtual()) {
            return null;
        }

        /** @var AddressInterface|Address|null $shippingAddress */
        $shippingAddress = $quote->getShippingAddress();
        if (!$shippingAddress) {
            return null;
        }

        $shippingMethod = (string)$shippingAddress->getShippingMethod();
        if ($shippingMethod === '') {
            return null;
        }

        // COD settings & payment method must match
        $codSettings = $this->getPacklinkCodSettings();
        if (!$codSettings) {
            return null;
        }
        $payment = $quote->getPayment();
        $paymentMethod = $payment ? (string)$payment->getMethod() : '';
        if ($paymentMethod !== $codSettings->getAccount()->getOfflinePaymentMethod()) {
            return null;
        }

        // Packlink shipping method config
        $plMethod = $this->getPacklinkShippingMethod($shippingMethod);
        if (!$plMethod) {
            return null;
        }

        // Basis for percentage: subtotal + shipping
        // Prefer buckets (when prior collectors ran); fall back to quote/address values.
        $subtotal = (float)$total->getTotalAmount('subtotal');
        if ($subtotal <= 0) {
            $subtotal = (float)$quote->getSubtotal();
        }
        $shipping = (float)$total->getTotalAmount('shipping');
        if ($shipping <= 0) {
            $shipping = (float)$shippingAddress->getShippingAmount();
        }
        $totalPrice = $subtotal + $shipping;
        if ($totalPrice <= 0) {
            return null;
        }

        // Base-currency surcharge via Packlink config

        $defaultSurchage = $codSettings->getAccount()->getCashOnDeliveryFee();
        $base = !empty($defaultSurchage) ? $defaultSurchage : $this->calculateSurcharge($plMethod, $shippingAddress, $codSettings, $totalPrice);
        if ($base === null || $base <= 0) {
            return null;
        }

        // Convert to quote currency
        $rate   = (float)($quote->getBaseToQuoteRate() ?: 1.0);
        $quoteAmount = round((float)$base * $rate, 2);

        return ['base' => (float)$base, 'quote' => $quoteAmount];
    }

    /**
     * Your original Packlink-based calculation (base currency).
     */
    private function calculateSurcharge(
        ShippingMethod $method,
        AddressInterface $shippingAddress,
        $cashOnDelivery,
        float $totalPrice
    ) {
        /** @var WarehouseService $warehouseService */
        $warehouseService = ServiceRegister::getService(WarehouseService::CLASS_NAME);
        $warehouse = $warehouseService->getWarehouse();
        $warehouseCountry = $warehouse->country;

        foreach ($method->getShippingServices() as $service) {
            $matchesRoute =
                $service->destinationCountry === $shippingAddress->getCountry()
                && $service->departureCountry === $warehouseCountry;

            if ($matchesRoute && $service->cashOnDeliveryConfig->offered) {
                $percentage = (float)$service->cashOnDeliveryConfig->applyPercentageCashOnDelivery;
                $calc = ($totalPrice * $percentage) / 100.0;

                // Adjust if your config actually means "min" vs "max" (naming varies).
                return max(
                    (float)$service->cashOnDeliveryConfig->maxCashOnDelivery,
                    (float)$calc
                );
            }
        }

        return null;
    }

    /**
     * Active Packlink COD settings or null.
     */
    private function getPacklinkCodSettings()
    {
        /** @var CashOnDeliveryServiceInterface $srv */
        $srv = ServiceRegister::getService(CashOnDeliveryServiceInterface::CLASS_NAME);
        $cfg = $srv->getCashOnDeliveryConfig();

        return ($cfg && $cfg->isActive()) ? $cfg : null;
    }

    /**
     * @param string $method
     *
     * @return \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Entity|null
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    private function getPacklinkShippingMethod(string $method)
    {
        $id = $this->extractPacklinkId($method);
        if (!$id) {
            return null;
        }

        $repo   = RepositoryRegistry::getRepository(ShippingMethod::getClassName());
        $filter = (new QueryFilter())->where('id', Operators::EQUALS, $id);

        return $repo->selectOne($filter);
    }

    private function extractPacklinkId(string $method)
    {
        return preg_match('/^packlink_(\d+)$/', $method, $m) ? (int)$m[1] : null;
    }
}
