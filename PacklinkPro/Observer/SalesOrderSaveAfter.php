<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2020 Packlink
 */

namespace Packlink\PacklinkPro\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\OrderShipmentDetails\OrderShipmentDetailsService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShipmentDraft\ShipmentDraftService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;

/**
 * Class SalesOrderSaveAfter
 *
 * @package Packlink\PacklinkPro\Observer
 */
class SalesOrderSaveAfter implements ObserverInterface
{
    /**
     * SalesOrderPlaceAfter constructor.
     *
     * @param Bootstrap $bootstrap
     */
    public function __construct(Bootstrap $bootstrap)
    {
        $bootstrap->initInstance();
    }

    /**
     * Handles event that is triggered after order has been saved.
     *
     * @param Observer $observer Magento observer.
     *
     * @throws \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShipmentDraft\Exceptions\DraftTaskMapExists
     * @throws \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShipmentDraft\Exceptions\DraftTaskMapNotFound
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();

        if ($this->draftShouldBeCreated($order)) {
            /** @var ShipmentDraftService $shipmentDraftService */
            $shipmentDraftService = ServiceRegister::getService(ShipmentDraftService::CLASS_NAME);
            $shipmentDraftService->enqueueCreateShipmentDraftTask((string)$order->getId());
        }
    }

    /**
     * Returns whether draft for the provided order should be created.
     *
     * @param Order $order Magento order entity.
     *
     * @return bool Returns TRUE if order draft should be created, otherwise returns FALSE.
     */
    private function draftShouldBeCreated(Order $order)
    {
        /** @var OrderShipmentDetailsService $orderShipmentDetailsService */
        $orderShipmentDetailsService = ServiceRegister::getService(OrderShipmentDetailsService::CLASS_NAME);
        $orderDetails = $orderShipmentDetailsService->getDetailsByOrderId((string)$order->getId());

        return $orderDetails === null
            && in_array($order->getStatus(), [Order::STATE_PROCESSING, Order::STATE_COMPLETE], true);
    }
}
