<?php

/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2021 Packlink
 */

namespace Packlink\PacklinkPro\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Sales\Model\Order;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Interfaces\ShopOrderService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\ShippingMethodService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;

/**
 * Class SalesOrderPlaceBefore
 *
 * @package Packlink\PacklinkPro\Observer
 */
class SalesOrderPlaceBefore implements ObserverInterface
{
    /**
     * SalesOrderPlaceBefore constructor.
     *
     * @param \Packlink\PacklinkPro\Bootstrap $bootstrap
     */
    public function __construct(Bootstrap $bootstrap)
    {
        $bootstrap->initInstance();
    }

    /**
     * @inheritDoc
     *
     * @throws \Magento\Payment\Gateway\Command\CommandException
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        /** @noinspection PhpUndefinedMethodInspection */
        $order = $observer->getEvent()->getOrder();

        if (($shippingMethod = $order->getShippingMethod(true)) === null) {
            return;
        }

        $methodId = (int)$shippingMethod->getDataByKey('method');
        $method = $this->getShippingMethod($methodId);

        if ($method && $method->isDestinationDropOff() && !$this->isDropOffSelected($order, $methodId)) {
            throw new CommandException(__('Please choose a drop-off location for selected shipping method.'));
        }
    }

    /**
     * Retrieves carrier service;
     *
     * @return ShippingMethodService
     */
    protected function getCarrierService()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return ServiceRegister::getService(ShippingMethodService::CLASS_NAME);
    }

    /**
     * Retrieves shipping method.
     *
     * @param int $methodId
     *
     * @return \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Models\ShippingMethod|null
     */
    protected function getShippingMethod($methodId)
    {
        return $this->getCarrierService()->getShippingMethod($methodId);
    }

    /**
     * Checks if drop-off is selected.
     *
     * @param \Magento\Sales\Model\Order $order
     * @param int $methodId
     *
     * @return bool
     */
    protected function isDropOffSelected(Order $order, $methodId)
    {
        /** @var ShopOrderService $shopOrderService */
        $shopOrderService = ServiceRegister::getService(ShopOrderService::CLASS_NAME);
        $dropOff = $shopOrderService->getDropOff($order, $methodId);

        return !empty($dropOff);
    }
}
