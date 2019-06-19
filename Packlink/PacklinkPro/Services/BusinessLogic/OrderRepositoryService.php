<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Services\BusinessLogic;

use Magento\Catalog\Helper\ImageFactory;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Sales\Model\Order\Shipment as MagentoShipment;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Sales\Model\Order\ShipmentRepository;
use Magento\Sales\Model\OrderRepository as MagentoOrderRepository;
use Magento\Shipping\Model\Order\Track;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManager;
use Packlink\PacklinkPro\Entity\QuoteCarrierDropOffMapping;
use Packlink\PacklinkPro\Entity\ShopOrderDetails;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Configuration;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Http\DTO\ParcelInfo;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Http\DTO\Shipment;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Http\DTO\Tracking;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Exceptions\OrderNotFound;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Interfaces\OrderRepository;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Objects\Address;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Objects\Item;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Objects\Order;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\ShippingMethodService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Utility\ShipmentStatus;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Tasks\SendDraftTask;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\Logger;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\QueryFilter\Operators;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\QueryFilter\QueryFilter;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\RepositoryRegistry;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\QueueService;
use Packlink\PacklinkPro\Repository\BaseRepository;

/**
 * Class OrderRepositoryService
 *
 * @package Packlink\PacklinkPro\Services\BusinessLogic
 */
class OrderRepositoryService implements OrderRepository
{
    /**
     * @var MagentoOrderRepository
     */
    protected $orderRepository;
    /**
     * @var ProductRepository
     */
    protected $productRepository;
    /**
     * @var CategoryRepository
     */
    protected $categoryRepository;
    /**
     * @var ShipmentRepository
     */
    protected $shipmentRepository;
    /**
     * @var TrackFactory
     */
    protected $trackFactory;
    /**
     * @var ImageFactory
     */
    protected $imageFactory;
    /**
     * @var Emulation
     */
    protected $appEmulation;
    /**
     * @var StoreManager
     */
    protected $storeManager;
    /**
     * Shop order details repository.
     *
     * @var BaseRepository
     */
    private $orderDetailsRepository;
    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * OrderRepositoryService constructor.
     *
     * @param MagentoOrderRepository $orderRepository
     * @param ProductRepository $productRepository
     * @param CategoryRepository $categoryRepository
     * @param ImageFactory $imageFactory
     * @param Emulation $appEmulation
     * @param StoreManager $storeManager
     * @param ShipmentRepository $shipmentRepository
     * @param TrackFactory $trackFactory
     * @param QuoteRepository $quoteRepository
     */
    public function __construct(
        MagentoOrderRepository $orderRepository,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository,
        ImageFactory $imageFactory,
        Emulation $appEmulation,
        StoreManager $storeManager,
        ShipmentRepository $shipmentRepository,
        TrackFactory $trackFactory,
        QuoteRepository $quoteRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->imageFactory = $imageFactory;
        $this->appEmulation = $appEmulation;
        $this->storeManager = $storeManager;
        $this->shipmentRepository = $shipmentRepository;
        $this->trackFactory = $trackFactory;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Returns shipment references of the orders that have not yet been completed.
     *
     * @return array Array of shipment references.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function getIncompleteOrderReferences()
    {
        $filter = new QueryFilter();
        $orderReferences = [];

        $filter->where('status', Operators::NOT_EQUALS, ShipmentStatus::STATUS_DELIVERED);
        /** @var ShopOrderDetails $orderDetails */
        /** @noinspection OneTimeUseVariablesInspection */
        $orders = $this->getOrderDetailsRepository()->select($filter);

        foreach ($orders as $orderDetails) {
            if ($orderDetails->getShipmentReference() !== null) {
                $orderReferences[] = $orderDetails->getShipmentReference();
            }
        }

        return $orderReferences;
    }

    /**
     * Fetches and returns system order by its unique identifier.
     *
     * @param string $orderId $orderId Unique order id.
     *
     * @return Order Order object.
     *
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Exceptions\OrderNotFound When order with
     *     provided id is not found.
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function getOrderAndShippingData($orderId)
    {
        /** @var MagentoOrder $sourceOrder */
        $sourceOrder = $this->orderRepository->get($orderId);
        $order = new Order();

        if ($sourceOrder === null) {
            throw new OrderNotFound(__('Source order not found'));
        }

        $order->setId((int)$orderId);
        $order->setCustomerId((int)$sourceOrder->getCustomerId());
        $order->setCurrency($sourceOrder->getOrderCurrencyCode());
        $order->setTotalPrice((float)$sourceOrder->getGrandTotal());
        $order->setBasePrice((float)$sourceOrder->getBaseGrandTotal());

        $order->setItems($this->getOrderItems($sourceOrder));

        if ($sourceOrder->getShippingMethod() !== null) {
            $this->setShippingMethod($sourceOrder, $order);
        }

        $order->setShippingAddress($this->getAddress($sourceOrder));

        return $order;
    }

    /**
     * Sets order packlink reference number.
     *
     * @param string $orderId Unique order id.
     * @param string $shipmentReference Packlink shipment reference.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Exceptions\OrderNotFound When order with
     *     provided id is not found.
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Exception
     */
    public function setReference($orderId, $shipmentReference)
    {
        /** @var MagentoOrder $order */
        $order = $this->orderRepository->get($orderId);
        if ($order === null) {
            throw new OrderNotFound(__('Source order not found'));
        }

        $orderDetails = $this->getOrderDetailsById($orderId);

        if ($orderDetails === null) {
            $orderDetails = new ShopOrderDetails();
            $orderDetails->setOrderId($orderId);
            $orderDetails->setShippingStatus(ShipmentStatus::STATUS_PENDING);
            $this->getOrderDetailsRepository()->save($orderDetails);
        }

        $orderDetails->setShipmentReference($shipmentReference);
        $this->getOrderDetailsRepository()->update($orderDetails);
    }

    /**
     * Returns order details entity with provided order ID.
     *
     * @param int $orderId ID of the order.
     *
     * @return ShopOrderDetails | null Shop order details entity or null if not found.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function getOrderDetailsById($orderId)
    {
        $filter = new QueryFilter();

        $filter->where('orderId', Operators::EQUALS, $orderId);
        /** @var ShopOrderDetails $orderDetails */
        /** @noinspection OneTimeUseVariablesInspection */
        $orderDetails = $this->getOrderDetailsRepository()->selectOne($filter);

        return $orderDetails;
    }

    /**
     * Sets order packlink shipment labels to an order by shipment reference.
     *
     * @param string $shipmentReference Packlink shipment reference.
     * @param string[] $labels Packlink shipment labels.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Exceptions\OrderNotFound When order with
     *     provided reference is not found.
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    public function setLabelsByReference($shipmentReference, array $labels)
    {
        $orderDetails = $this->getOrderDetailsByReference($shipmentReference);

        $orderDetails->setShipmentLabels($labels);

        $this->getOrderDetailsRepository()->update($orderDetails);
    }

    /**
     * Sets order packlink shipment tracking history to an order by shipment reference.
     *
     * @param Shipment $shipment Packlink shipment details.
     * @param Tracking[] $trackingHistory Shipment tracking history.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Exceptions\OrderNotFound When order with
     *     provided reference is not found.
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function updateTrackingInfo(Shipment $shipment, array $trackingHistory)
    {
        $orderDetails = $this->getOrderDetailsByReference($shipment->reference);

        if (!empty($trackingHistory)) {
            $trackingHistory = $this->sortTrackingRecords($trackingHistory);
            $latestTrackingRecord = $trackingHistory[0];
            $orderDetails->setShippingStatus($latestTrackingRecord->description, $latestTrackingRecord->timestamp);
        }

        if ($shipment !== null) {
            $this->setShipmentDetails($orderDetails, $shipment);
        }

        $this->getOrderDetailsRepository()->update($orderDetails);
    }

    /**
     * Returns shipping method title for the order with provided ID.
     *
     * @param int $orderId ID of the order.
     *
     * @return string Shipping method title.
     *
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\InputException
     */
    public function getShippingMethodTitle($orderId)
    {
        /** @var ShippingMethodService $shippingMethodService */
        $shippingMethodService = ServiceRegister::getService(ShippingMethodService::CLASS_NAME);

        /** @var MagentoOrder $sourceOrder */
        $sourceOrder = $this->orderRepository->get($orderId);
        $sourceShippingMethod = $sourceOrder->getShippingMethod(true);
        if ($sourceShippingMethod === null) {
            return '';
        }

        $id = (int)$sourceShippingMethod->getDataByKey('method');
        $shippingMethod = $shippingMethodService->getShippingMethod($id);

        return $shippingMethod ? $shippingMethod->getTitle() : '';
    }

    /**
     * Sets order packlink shipping status to an order by shipment reference.
     *
     * @param string $shipmentReference Packlink shipment reference.
     * @param string $shippingStatus Packlink shipping status.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Exceptions\OrderNotFound When order with
     *     provided reference is not found.
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function setShippingStatusByReference($shipmentReference, $shippingStatus)
    {
        $orderDetails = $this->getOrderDetailsByReference($shipmentReference);

        $this->setSourceOrderState($orderDetails->getOrderId(), $shippingStatus);

        $orderDetails->setShippingStatus($shippingStatus);
        $this->getOrderDetailsRepository()->update($orderDetails);
    }

    /**
     * Sets shipping price to an order by shipment reference.
     *
     * @param string $shipmentReference Packlink shipment reference.
     * @param float $price Shipment price.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Exceptions\OrderNotFound When order with
     *     provided reference is not found.
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function setShippingPriceByReference($shipmentReference, $price)
    {
        $orderDetails = $this->getOrderDetailsByReference($shipmentReference);
        $orderDetails->setPacklinkShippingPrice($price);
        $this->getOrderDetailsRepository()->update($orderDetails);
    }

    /**
     * Returns whether shipment identified by provided reference has Packlink shipment label set.
     *
     * @param string $shipmentReference Packlink shipment reference.
     *
     * @return bool Returns TRUE if label is set; otherwise, FALSE.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Exceptions\OrderNotFound
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function isLabelSet($shipmentReference)
    {
        $details = $this->getOrderDetailsByReference($shipmentReference);

        return $details !== null && count($details->getShipmentLabels()) > 0;
    }

    /**
     * Enqueues SendDraftTask for creating order draft on Packlink and storing shipment reference.
     *
     * @param int $orderId ID of the order.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     */
    public function createOrderDraft($orderId)
    {
        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        /** @var QueueService $queue */
        $queue = ServiceRegister::getService(QueueService::CLASS_NAME);

        $orderDetails = $this->getOrderDetailsById($orderId);
        if ($orderDetails === null) {
            $orderDetails = new ShopOrderDetails();
            $orderDetails->setOrderId($orderId);
            $orderDetails->setShippingStatus(ShipmentStatus::STATUS_PENDING);
            $this->saveOrderDetails($orderDetails);
        }

        $draftTask = new SendDraftTask($orderId);
        $queue->enqueue($configService->getDefaultQueueName(), $draftTask);

        if ($draftTask->getExecutionId() !== null) {
            // Retrieve record from database again in case task has already finished
            // so that the reference value is not overwritten on save.
            $orderDetails = $this->getOrderDetailsById($orderId);
            $orderDetails->setTaskId($draftTask->getExecutionId());
            $this->saveOrderDetails($orderDetails);
        }
    }

    /**
     * Saves order details entity using order details repository.
     *
     * @param ShopOrderDetails $orderDetails Shop order details entity.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Exception
     */
    public function saveOrderDetails(ShopOrderDetails $orderDetails)
    {
        if ($orderDetails->getId() === null) {
            $this->getOrderDetailsRepository()->save($orderDetails);
        } else {
            $this->getOrderDetailsRepository()->update($orderDetails);
        }
    }

    /**
     * Sets label identified by order ID and link to PDF to have been printed.
     *
     * @param int $orderId ID of the order that the shipment label belongs to.
     * @param string $link Link to PDF.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function setLabelPrinted($orderId, $link)
    {
        $orderDetails = $this->getOrderDetailsById($orderId);

        if ($orderDetails === null) {
            Logger::logWarning(__('Order details not found'), 'Integration');

            return;
        }

        $labels = $orderDetails->getShipmentLabels();

        foreach ($labels as $label) {
            if ($label->getLink() === $link) {
                $label->setPrinted(true);
            }
        }

        $this->getOrderDetailsRepository()->update($orderDetails);
    }

    /**
     * Marks shipment identified by provided reference as deleted on Packlink.
     *
     * @param string $shipmentReference Packlink shipment reference.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Exceptions\OrderNotFound
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function markShipmentDeleted($shipmentReference)
    {
        $orderDetails = $this->getOrderDetailsByReference($shipmentReference);

        $orderDetails->setDeleted(true);

        $this->getOrderDetailsRepository()->update($orderDetails);
    }

    /**
     * Returns whether shipment identified by provided reference is deleted on Packlink or not.
     *
     * @param string $shipmentReference Packlink shipment reference.
     *
     * @return bool Returns TRUE if shipment has been deleted; otherwise returns FALSE.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Exceptions\OrderNotFound
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function isShipmentDeleted($shipmentReference)
    {
        $orderDetails = $this->getOrderDetailsByReference($shipmentReference);

        return $orderDetails->isDeleted();
    }

    /**
     * Sets Packlink shipping method details associated with the Magento order, if any,
     *
     * @param \Magento\Sales\Model\Order $sourceOrder
     * @param \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Objects\Order $order
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function setShippingMethod(MagentoOrder $sourceOrder, Order $order)
    {
        $methodId = (int)$sourceOrder->getShippingMethod(true)->getDataByKey('method');
        $order->setShippingMethodId($methodId);
        $dropOff = $this->getDropOff($sourceOrder, $methodId);
        if (!empty($dropOff)) {
            $order->setShippingDropOffId($dropOff['id']);
        }
    }

    /**
     * Sets shipping address on source Magento order.
     *
     * @param MagentoOrder $sourceOrder Source Magento order.
     * @param array $dropOff Drop-off information.
     */
    public function setSourceOrderShippingAddress(MagentoOrder $sourceOrder, $dropOff)
    {
        $sourceOrder->setShippingAddress(
            $this->getAddressFromDropOff($sourceOrder->getShippingAddress(), $dropOff)
        );
        $this->orderRepository->save($sourceOrder);
    }

    /**
     * Returns drop-off information for the given order and shipping carrier ID.
     *
     * @param MagentoOrder $order Source Magento order.
     * @param int $carrierId ID of the carrier.
     *
     * @return array Drop-off information.
     *
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getDropOff($order, $carrierId)
    {
        $quoteId = $order->getQuoteId();

        /** @var BaseRepository $mappingRepository */
        $mappingRepository = RepositoryRegistry::getRepository(QuoteCarrierDropOffMapping::CLASS_NAME);
        $query = new QueryFilter();
        $query->where('quoteId', '=', (int)$quoteId)
            ->where('carrierId', '=', (int)$carrierId);

        /** @var QuoteCarrierDropOffMapping[] $mappings */
        $mappings = $mappingRepository->select($query);

        if (empty($mappings)) {
            return [];
        }

        if (count($mappings) === 1) {
            $mapping = $mappings[0];

            return $mapping->getDropOff() ?: [];
        }

        return $this->getMultiAddressDropOff($order, $quoteId, $mappings);
    }

    /**
     * @param MagentoOrder $shopOrder
     *
     * @return array
     */
    private function getOrderItems(MagentoOrder $shopOrder)
    {
        /** @var \Magento\Sales\Model\Order\Item[] $sourceOrderItems */
        $sourceOrderItems = $shopOrder->getAllVisibleItems();
        $orderItems = [];

        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        $defaultParcel = $configService->getDefaultParcel();

        if ($defaultParcel === null) {
            Logger::logError(__('Default parcel is not set'), 'Integration');
        }

        foreach ($sourceOrderItems as $sourceOrderItem) {
            if (!$sourceOrderItem->getIsVirtual()) {
                $orderItems[] = $this->getOrderItem($sourceOrderItem, $defaultParcel);
            }
        }

        return $orderItems;
    }

    /**
     * Returns a single order item for the provided source order.
     *
     * @param OrderItemInterface $sourceOrderItem
     * @param ParcelInfo $defaultParcel Default parcel.
     *
     * @return Item
     */
    private function getOrderItem(OrderItemInterface $sourceOrderItem, ParcelInfo $defaultParcel)
    {
        $orderItem = new Item();

        $orderItem->setQuantity((int)$sourceOrderItem->getQtyOrdered());
        $orderItem->setTitle($sourceOrderItem->getName());
        $orderItem->setPrice((float)$sourceOrderItem->getBasePrice());
        $orderItem->setTotalPrice((float)$sourceOrderItem->getPrice());

        try {
            /** @var Product $product */
            $product = $this->productRepository->getById($sourceOrderItem->getProductId());

            $weight = 0;
            if ($product) {
                $orderItem->setPictureUrl($this->getProductImageUrl($product));
                $weight = $product->getWeight() ? round((float)$product->getWeight(), 2) : 0;
                $categoryIds = $product->getCategoryIds();

                if (!empty($categoryIds)) {
                    $category = $this->categoryRepository->get((int)$categoryIds[0]);
                    $orderItem->setCategoryName($category->getName());
                }
            }

            if ($defaultParcel !== null) {
                if ($weight === 0) {
                    $weight = $defaultParcel->weight;
                }

                $orderItem->setHeight($defaultParcel->height);
                $orderItem->setWidth($defaultParcel->width);
                $orderItem->setLength($defaultParcel->length);
            }

            $orderItem->setWeight($weight);
        } catch (NoSuchEntityException $exception) {
            Logger::logError(__('Order item product not found.'), 'Integration');
        }

        return $orderItem;
    }

    /**
     * Returns product image URL.
     *
     * @param Product $product Magento product.
     *
     * @return string Public URL to product image.
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getProductImageUrl(Product $product)
    {
        $imageUrl = '';
        $store = $this->storeManager->getStore();

        if ($store !== null) {
            $storeId = $store->getId();
            $this->appEmulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
            $imageUrl = $this->imageFactory->create()
                ->init($product, 'product_base_image')
                ->getUrl();
            $this->appEmulation->stopEnvironmentEmulation();
        }

        return $imageUrl;
    }

    /**
     * Returns drop-off information for shipping to multiple addresses.
     *
     * @param MagentoOrder $order Source Magento order.
     * @param int $quoteId ID of the quote.
     * @param QuoteCarrierDropOffMapping[] $mappings Array of quote carrier drop-off mappings.
     *
     * @return array Drop-off information.
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getMultiAddressDropOff($order, $quoteId, $mappings)
    {
        /** @var Quote $quote */
        $quote = $this->quoteRepository->get($quoteId);
        /** @var MagentoOrder\Address $orderAddress */
        $orderAddress = $order->getShippingAddress();
        $quoteAddresses = $quote->getAllShippingAddresses();
        $addressId = null;

        /** @var Quote\Address $quoteAddress */
        foreach ($quoteAddresses as $quoteAddress) {
            if ($this->addressesAreEqual($orderAddress, $quoteAddress)) {
                $addressId = (int)$quoteAddress->getId();
            }
        }

        if ($addressId !== null) {
            foreach ($mappings as $mapping) {
                if ($mapping->getAddressId() === $addressId) {
                    return $mapping->getDropOff() ?: [];
                }
            }
        }

        return [];
    }

    /**
     * Checks whether two address entities are equal.
     *
     * @param MagentoOrder\Address $orderAddress
     * @param Quote\Address $quoteAddress
     *
     * @return bool Returns TRUE if addresses are equal, otherwise returns FALSE.
     */
    private function addressesAreEqual($orderAddress, $quoteAddress)
    {
        return $orderAddress->getCountryId() === $quoteAddress->getCountryId()
            && $orderAddress->getPostcode() === $quoteAddress->getPostcode()
            && $orderAddress->getName() === $quoteAddress->getName()
            && $orderAddress->getStreet() === $quoteAddress->getStreet()
            && $orderAddress->getCity() === $quoteAddress->getCity()
            && $orderAddress->getRegion() === $quoteAddress->getRegion()
            && $orderAddress->getTelephone() === $quoteAddress->getTelephone();
    }

    /**
     * Returns order shipping address updated with drop-off location data.
     *
     * @param \Magento\Sales\Model\Order\Address $address Order shipping address.
     * @param array $dropOff Drop-off location data.
     *
     * @return \Magento\Sales\Model\Order\Address
     */
    private function getAddressFromDropOff($address, $dropOff)
    {
        $address->setCompany($dropOff['name']);
        $address->setStreet($dropOff['address']);
        $address->setCity($dropOff['city']);
        $address->setPostcode($dropOff['zip']);

        if (!empty($dropOff['countryCode'])) {
            $address->setCountryId($dropOff['countryCode']);
        }

        if (!empty($dropOff['state'])) {
            $address->setRegion($dropOff['state']);
        }

        return $address;
    }

    /**
     * Returns Packlink address from shop address.
     *
     * @param MagentoOrder $shopOrder Magento order entity.
     *
     * @return Address Packlink address.
     */
    private function getAddress(MagentoOrder $shopOrder)
    {
        $shippingAddress = new Address();
        $sourceAddress = $shopOrder->getShippingAddress();

        if ($sourceAddress === null) {
            return $shippingAddress;
        }

        $addressLine1 = '';
        $addressLine2 = '';
        $sourceStreet = $sourceAddress->getStreet();
        if (is_array($sourceStreet)) {
            $addressLine1 = $sourceStreet[0];
            $addressLine2 = count($sourceStreet) > 1 ? $sourceStreet[1] : '';
        }

        $shippingAddress->setZipCode($sourceAddress->getPostcode());
        $shippingAddress->setCity($sourceAddress->getCity());
        $shippingAddress->setCountry($sourceAddress->getCountryId());
        $shippingAddress->setCompany($sourceAddress->getCompany());
        $shippingAddress->setPhone($sourceAddress->getTelephone());
        $shippingAddress->setStreet1($addressLine1);
        $shippingAddress->setStreet2($addressLine2);
        $shippingAddress->setEmail($sourceAddress->getEmail());
        $shippingAddress->setName($sourceAddress->getFirstname());
        $shippingAddress->setSurname($sourceAddress->getLastname());

        return $shippingAddress;
    }

    /**
     * Returns order details entity with provided shipment reference, or throws an exception if it doesn't exist.
     *
     * @param string $shipmentReference Packlink order shipment reference.
     *
     * @return ShopOrderDetails Order details.
     *
     * @throws OrderNotFound
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    private function getOrderDetailsByReference($shipmentReference)
    {
        $filter = new QueryFilter();

        $filter->where('shipmentReference', Operators::EQUALS, $shipmentReference);
        /** @var ShopOrderDetails $orderDetails */
        $orderDetails = $this->getOrderDetailsRepository()->selectOne($filter);

        if ($orderDetails === null) {
            throw new OrderNotFound(__("Order with shipment reference $shipmentReference doesn't exist in the shop"));
        }

        return $orderDetails;
    }

    /**
     * Sort tracking history records by timestamps in descending order.
     *
     * @param Tracking[] $trackingRecords Array of tracking history records.
     *
     * @return array Sorted array of tracking history records.
     */
    private function sortTrackingRecords(array $trackingRecords)
    {
        usort(
            $trackingRecords,
            function ($first, $second) {
                /** @var Tracking $first */
                /** @var Tracking $second */
                if ($first->timestamp === $second->timestamp) {
                    return 0;
                }

                return ($first->timestamp < $second->timestamp) ? 1 : -1;
            }
        );

        return $trackingRecords;
    }

    /**
     * Sets shipment details on shop order details entity.
     *
     * @param ShopOrderDetails $orderDetails Order details entity.
     * @param Shipment $shipmentDetails Packlink shipment details.
     *
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function setShipmentDetails(ShopOrderDetails $orderDetails, Shipment $shipmentDetails)
    {
        $orderDetails->setPacklinkShippingPrice($shipmentDetails->price);
        $orderDetails->setCarrierTrackingUrl($shipmentDetails->carrierTrackingUrl);
        if (!empty($shipmentDetails->trackingCodes)) {
            $this->setSourceOrderTrackingNumbers($orderDetails->getOrderId(), $shipmentDetails->trackingCodes);
            $orderDetails->setCarrierTrackingNumbers($shipmentDetails->trackingCodes);
        }
    }

    /**
     * @param int $orderId ID of the order.
     * @param string[] $trackingCodes Array of Packlink tracking codes.
     *
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     */
    private function setSourceOrderTrackingNumbers($orderId, array $trackingCodes)
    {
        /** @var MagentoOrder $order */
        $order = $this->orderRepository->get($orderId);
        $tracks = [];
        foreach ($trackingCodes as $trackingCode) {
            /** @var Track $track */
            $track = $this->trackFactory->create();

            $track->setCarrierCode('packlink');
            $track->setTitle($this->getShippingMethodTitle($orderId));
            $track->setTrackNumber($trackingCode);
            $track->setOrderId($orderId);

            $tracks[] = $track;
        }

        $shipmentsCollection = $order->getShipmentsCollection();
        foreach ($shipmentsCollection as $shipment) {
            $shipmentId = $shipment->getId();
            /** @var MagentoShipment $shipment */
            $shipment = $this->shipmentRepository->get($shipmentId);
            $shipment->setTracks($tracks);
            $this->shipmentRepository->save($shipment);
        }
    }

    /**
     * Sets order state on source order on Magento.
     *
     * @param int $orderId ID of the order.
     * @param string $shippingStatus Shipping status from Packlink.
     *
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\InputException
     */
    private function setSourceOrderState($orderId, $shippingStatus)
    {
        /** @var MagentoOrder $order */
        $order = $this->orderRepository->get($orderId);

        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        $stateMappings = $configService->getOrderStatusMappings();

        if (!array_key_exists($shippingStatus, $stateMappings)) {
            Logger::logWarning(
                __('Order state mapping not found.'),
                'Integration'
            );

            return;
        }

        if ($order->getState() !== $stateMappings[$shippingStatus]) {
            $order->setState($stateMappings[$shippingStatus]);
            $this->orderRepository->save($order);
        }
    }

    /**
     * Returns shop order details repository.
     *
     * @return BaseRepository
     *
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    private function getOrderDetailsRepository()
    {
        if ($this->orderDetailsRepository === null) {
            $this->orderDetailsRepository = RepositoryRegistry::getRepository(ShopOrderDetails::getClassName());
        }

        return $this->orderDetailsRepository;
    }
}
