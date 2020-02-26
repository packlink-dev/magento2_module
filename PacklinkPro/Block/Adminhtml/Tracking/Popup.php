<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2020 Packlink
 */

namespace Packlink\PacklinkPro\Block\Adminhtml\Tracking;

use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTimeFormatterInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\ShipmentTrackRepositoryInterface;
use Magento\Sales\Model\Order\Shipment\Track;
use Magento\Shipping\Block\Tracking\Popup as MagentoShippingPopup;
use Magento\Shipping\Model\Info;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\OrderShipmentDetails\OrderShipmentDetailsService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\Logger;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;

/**
 * Class Popup
 *
 * @package Packlink\PacklinkPro\Block\Adminhtml\Tracking
 */
class Popup extends MagentoShippingPopup
{
    /**
     * @var ShipmentTrackRepositoryInterface
     */
    private $trackRepository;

    /**
     * Popup constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param DateTimeFormatterInterface $dateTimeFormatter
     * @param Bootstrap $bootstrap
     * @param ShipmentTrackRepositoryInterface $trackRepository
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        DateTimeFormatterInterface $dateTimeFormatter,
        Bootstrap $bootstrap,
        ShipmentTrackRepositoryInterface $trackRepository,
        array $data = []
    ) {
        parent::__construct($context, $registry, $dateTimeFormatter, $data);

        $this->trackRepository = $trackRepository;
        $bootstrap->initInstance();
    }

    /**
     * Returns whether the order is shipped by Packlink or not.
     *
     * @return bool Returns TRUE if order is shipped by Packlink, otherwise returns FALSE.
     */
    public function orderShippedWithPacklink()
    {
        $track = $this->getTrack();
        if ($track === null) {
            return false;
        }

        return $track->getCarrierCode() === 'packlink';
    }

    /**
     * Returns URL for shipment tracking on the carrier website, if it exists.
     *
     * @return string Returns carrier tracking URL, or empty string if that URL doesn't exists within order details.
     */
    public function getCarrierTrackingUrl()
    {
        /** @var OrderShipmentDetailsService $orderShipmentDetailsService */
        $orderShipmentDetailsService = ServiceRegister::getService(OrderShipmentDetailsService::CLASS_NAME);
        try {
            $orderDetails = $orderShipmentDetailsService->getDetailsByOrderId((string)$this->getOrderId());

            if ($orderDetails !== null && $orderDetails->getCarrierTrackingUrl() !== null) {
                return $orderDetails->getCarrierTrackingUrl();
            }
        } catch (\Exception $e) {
            Logger::logError(__('Error getting carrier tracking URL.'));
        }

        return '';
    }

    /**
     * Returns current order ID.
     *
     * @return int
     */
    private function getOrderId()
    {
        $track = $this->getTrack();
        if ($track === null) {
            return 0;
        }

        return (int)$track->getOrderId();
    }

    /**
     * Returns Magento shipment track entity.
     *
     * @return Track
     */
    private function getTrack()
    {
        /** @var Info $shippingInfo */
        $shippingInfo = $this->_registry->registry('current_shipping_info');
        $trackId = $shippingInfo->getData('track_id');
        /** @var Track $track */
        $track = $this->trackRepository->get($trackId);

        return $track ?: null;
    }
}
