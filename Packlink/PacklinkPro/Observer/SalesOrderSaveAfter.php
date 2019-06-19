<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Interfaces\OrderRepository;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\Services\BusinessLogic\OrderRepositoryService;

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
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();

        /** @var OrderRepositoryService $orderRepositoryService */
        $orderRepositoryService = ServiceRegister::getService(OrderRepository::CLASS_NAME);

        if ($this->draftShouldBeCreated($order, $orderRepositoryService)) {
            $orderRepositoryService->createOrderDraft((int)$order->getId());
        }
    }

    /**
     * Returns whether draft for the provided order should be created.
     *
     * @param Order $order Magento order entity.
     * @param OrderRepositoryService $orderRepositoryService Order repository service.
     *
     * @return bool Returns TRUE if order draft should be created, otherwise returns FALSE.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    private function draftShouldBeCreated(Order $order, OrderRepositoryService $orderRepositoryService)
    {
        $orderDetails = $orderRepositoryService->getOrderDetailsById((int)$order->getId());

        return $orderDetails === null
            && in_array($order->getStatus(), [Order::STATE_PROCESSING, Order::STATE_COMPLETE], true);
    }
}
