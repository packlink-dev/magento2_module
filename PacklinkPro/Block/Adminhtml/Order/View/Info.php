<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Block\Adminhtml\Order\View;

use Magento\Backend\Block\Template\Context;
use Magento\Customer\Api\CustomerMetadataInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Customer\Model\Metadata\ElementFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Sales\Helper\Admin;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address\Renderer;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\Helper\UrlHelper;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Configuration;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\OrderService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\OrderShipmentDetails\Models\OrderShipmentDetails;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\OrderShipmentDetails\OrderShipmentDetailsService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShipmentDraft\Objects\ShipmentDraftStatus;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShipmentDraft\ShipmentDraftService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\Logger;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Utility\TimeProvider;
use Packlink\PacklinkPro\Services\BusinessLogic\ConfigurationService;
use Packlink\PacklinkPro\Services\BusinessLogic\ShopOrderService;

/**
 * Class Info
 *
 * @package Packlink\PacklinkPro\Block\Adminhtml\Order\View
 */
class Info extends \Magento\Sales\Block\Adminhtml\Order\View\Info
{
    /**
     * @var UrlHelper
     */
    private $urlHelper;
    /**
     * @var OrderShipmentDetailsService
     */
    private $orderShipmentDetailsService;
    /**
     * @var ShopOrderService
     */
    private $shopOrderService;
    /**
     * @var ConfigurationService
     */
    private $configService;
    /**
     * @var OrderShipmentDetails
     */
    private $orderDetails;

    /**
     * Info constructor.
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Sales\Helper\Admin $adminHelper
     * @param \Magento\Customer\Api\GroupRepositoryInterface $groupRepository
     * @param \Magento\Customer\Api\CustomerMetadataInterface $metadata
     * @param \Magento\Customer\Model\Metadata\ElementFactory $elementFactory
     * @param \Magento\Sales\Model\Order\Address\Renderer $addressRenderer
     * @param \Packlink\PacklinkPro\Helper\UrlHelper $urlHelper
     * @param \Packlink\PacklinkPro\Bootstrap $bootstrap
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        Admin $adminHelper,
        GroupRepositoryInterface $groupRepository,
        CustomerMetadataInterface $metadata,
        ElementFactory $elementFactory,
        Renderer $addressRenderer,
        UrlHelper $urlHelper,
        Bootstrap $bootstrap,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $adminHelper,
            $groupRepository,
            $metadata,
            $elementFactory,
            $addressRenderer,
            $data
        );

        $this->urlHelper = $urlHelper;

        $bootstrap->initInstance();
    }

    /**
     * Returns URL of the order draft controller.
     *
     * @return string URL of the order draft controller.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getDraftControllerUrl()
    {
        return $this->urlHelper->getBackendUrl(
            'packlink/draft/orderdraft',
            [
                'ajax' => 1,
                'form_key' => $this->formKey->getFormKey(),
            ]
        );
    }

    /**
     * Returns URL to order draft on Packlink PRO.
     *
     * @return string URL to order draft on Packlink PRO.
     */
    public function getDraftUrl()
    {
        $orderDetails = $this->getOrderDetails();

        if ($orderDetails === null) {
            return '';
        }

        return $this->urlHelper->getOrderDraftUrl(
            $this->getConfigService()->getUserInfo()->country,
            $orderDetails->getReference()
        );
    }

    /**
     * Checks whether order draft is already created for the order.
     *
     * @return bool Returns TRUE if order draft exists for provided order, otherwise returns FALSE.
     */
    public function isDraftCreated()
    {
        $order = $this->getCurrentOrder();
        /** @var ShipmentDraftService $shipmentDraftService */
        $shipmentDraftService = ServiceRegister::getService(ShipmentDraftService::CLASS_NAME);
        $status = $shipmentDraftService->getDraftStatus($order->getId());

        return $status->status !== ShipmentDraftStatus::NOT_QUEUED;
    }

    /**
     * Checks whether the user has logged in with his/her API key.
     *
     * @return bool Returns TRUE if user has logged in, otherwise returns FALSE.
     */
    public function isUserLoggedIn()
    {
        return $this->getConfigService()->getAuthorizationToken() !== null;
    }

    /**
     * Returns link to the backend controller for printing shipment label.
     *
     * @return string Link to controller.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getLabelControllerUrl()
    {
        return $this->urlHelper->getBackendUrl(
            'packlink/shipmentlabels/shipmentlabels',
            [
                'ajax' => 1,
                'form_key' => $this->formKey->getFormKey(),
            ]
        );
    }

    /**
     * Returns whether order has shipment label associated with it.
     *
     * @return bool Returns TRUE if label exists, otherwise returns FALSE.
     */
    public function labelExists()
    {
        $orderDetails = $this->getOrderDetails();

        if ($orderDetails === null) {
            return false;
        }

        /** @var \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\OrderService $orderService */
        $orderService = ServiceRegister::getService(OrderService::CLASS_NAME);

        return $orderService->isReadyToFetchShipmentLabels($orderDetails->getShippingStatus());
    }

    /**
     * Returns whether shipment label of the order has already been printed.
     *
     * @return bool Returns TRUE if label has already been printed, otherwise returns FALSE.
     */
    public function labelPrinted()
    {
        $details = $this->getOrderDetails();

        if ($details === null) {
            return false;
        }

        $labels = $details->getShipmentLabels();

        return !empty($labels) && $labels[0]->isPrinted();
    }

    /**
     * Returns rendered HTML output of order carrier tracking numbers.
     *
     * @return string Rendered HTML output.
     */
    public function getCarrierTrackingNumbers()
    {
        $output = '';
        $orderDetails = $this->getOrderDetails();
        if ($orderDetails === null || empty($orderDetails->getCarrierTrackingNumbers())) {
            return $output;
        }

        $trackingNumbers = $orderDetails->getCarrierTrackingNumbers();
        foreach ($trackingNumbers as $index => $trackingNumber) {
            $output .= '<div>' . $trackingNumber;

            if ($index !== count($trackingNumbers) - 1) {
                $output .= ',';
            }

            $output .= '</div>';
        }

        return $output;
    }

    /**
     * Returns title of the order shipping method.
     *
     * @return string Shipping method title.
     *
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getShippingMethodTitle()
    {
        $order = $this->getCurrentOrder();

        if ($order === null) {
            return '';
        }

        return $this->getShopOrderService()->getShippingMethodTitle($order->getId());
    }

    /**
     * Returns carrier logo file path for the order.
     *
     * @return string Path to carrier logo.
     */
    public function getCarrierLogo()
    {
        $order = $this->getCurrentOrder();

        $id = 0;
        if ($order !== null) {
            $sourceShippingMethod = $order->getShippingMethod(true);
            $id = $sourceShippingMethod ? (int)$sourceShippingMethod->getDataByKey('method') : 0;
        }

        /** @var \Packlink\PacklinkPro\Services\BusinessLogic\CarrierService $carrierService */
        $carrierService = ServiceRegister::getService(ShopShippingMethodService::CLASS_NAME);

        return $carrierService->getCarrierLogoById($id);
    }

    /**
     * Returns order last status update time.
     *
     * @return string Last status update time in human readable format.
     */
    public function getLastStatusUpdateTime()
    {
        /** @var TimeProvider $timeProvider */
        $timeProvider = ServiceRegister::getService(TimeProvider::CLASS_NAME);
        $orderDetails = $this->getOrderDetails();

        if ($orderDetails === null || $orderDetails->getLastStatusUpdateTime() === null) {
            return '';
        }

        return $timeProvider->serializeDate($orderDetails->getLastStatusUpdateTime(), 'd.m.Y H:i:s');
    }

    /**
     * Returns details for the order.
     *
     * @return OrderShipmentDetails Order details entity.
     */
    public function getOrderDetails()
    {
        if ($this->orderDetails === null) {
            try {
                $order = $this->getCurrentOrder();
                if ($order) {
                    $this->orderDetails = $this->getOrderShipmentDetailsService()->getDetailsByOrderId((string)$order->getId());
                }
            } catch (\Exception $e) {
                Logger::logWarning(__('Order details not found'), 'Integration');

                return null;
            }
        }

        return $this->orderDetails;
    }

    /**
     * Returns current Magento order.
     *
     * @return Order|null
     */
    public function getCurrentOrder()
    {
        $order = null;

        try {
            $order = $this->getOrder();
        } catch (LocalizedException $e) {
            Logger::logError(__('Order details not found'), 'Integration');
        }

        return $order;
    }

    /**
     * Returns instance of order shipment details service.
     *
     * @return \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\OrderShipmentDetails\OrderShipmentDetailsService
     */
    private function getOrderShipmentDetailsService()
    {
        if ($this->orderShipmentDetailsService === null) {
            $this->orderShipmentDetailsService = ServiceRegister::getService(OrderShipmentDetailsService::CLASS_NAME);
        }

        return $this->orderShipmentDetailsService;
    }

    /**
     * Returns an instance of shop order service.
     *
     * @return \Packlink\PacklinkPro\Services\BusinessLogic\ShopOrderService
     */
    private function getShopOrderService()
    {
        if ($this->shopOrderService === null) {
            $this->shopOrderService = ServiceRegister::getService(
                \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Interfaces\ShopOrderService::CLASS_NAME
            );
        }

        return $this->shopOrderService;
    }

    /**
     * Returns instance of configuration service.
     *
     * @return ConfigurationService
     */
    private function getConfigService()
    {
        if ($this->configService === null) {
            $this->configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        }

        return $this->configService;
    }
}
