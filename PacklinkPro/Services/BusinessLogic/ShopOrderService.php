<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2020 Packlink
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
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Configuration;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Http\DTO\ParcelInfo;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Exceptions\OrderNotFound;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Interfaces\ShopOrderService as ShopOrderServiceInterface;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Objects\Address;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Objects\Item;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Objects\Order;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\ShippingMethodService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\Logger;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\QueryFilter\QueryFilter;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\RepositoryRegistry;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\Repository\BaseRepository;

/**
 * Class OrderRepositoryService
 *
 * @package Packlink\PacklinkPro\Services\BusinessLogic
 */
class ShopOrderService implements ShopOrderServiceInterface
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
     * @inheritDoc
     */
    public function updateTrackingInfo($orderId, array $trackings)
    {
        /** @var MagentoOrder $order */
        $order = $this->orderRepository->get($orderId);
        $title = $this->getShippingMethodTitle($orderId);
        $tracks = [];
        foreach ($trackings as $trackingCode) {
            /** @var Track $track */
            $track = $this->trackFactory->create();

            $track->setCarrierCode('packlink');
            $track->setTitle($title);
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
     * @inheritDoc
     */
    public function updateShipmentStatus($orderId, $shippingStatus)
    {
        /** @var MagentoOrder $order */
        $order = $this->orderRepository->get($orderId);

        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        $stateMappings = $configService->getOrderStatusMappings();

        if (!array_key_exists($shippingStatus, $stateMappings)) {
            Logger::logWarning(__('Order state mapping not found.'), 'Integration');

            return;
        }

        if (!empty($stateMappings[$shippingStatus]) && $order->getState() !== $stateMappings[$shippingStatus]) {
            $order->setState($stateMappings[$shippingStatus]);
            $this->orderRepository->save($order);
        }
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
    public function setOrderShippingAddress(MagentoOrder $sourceOrder, $dropOff)
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
}
