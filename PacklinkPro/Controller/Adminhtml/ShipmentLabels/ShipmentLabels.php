<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2021 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\ShipmentLabels;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Webapi\Exception;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\OrderService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\OrderShipmentDetails\OrderShipmentDetailsService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;

class ShipmentLabels extends Action
{
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    public function __construct(Action\Context $context, Bootstrap $bootstrap, JsonFactory $jsonFactory)
    {
        parent::__construct($context);

        $this->resultJsonFactory = $jsonFactory;

        $bootstrap->initInstance();
    }

    /**
     * Execute action based on request and return result
     *
     * Note: Request will be added as operation argument in future
     *
     * @return \Magento\Framework\Controller\Result\Json
     *
     * @throws \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\OrderShipmentDetails\Exceptions\OrderShipmentDetailsNotFound
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $request = json_decode(file_get_contents('php://input'));

        if (property_exists($request, 'orderId')) {
            /** @var OrderShipmentDetailsService $orderShipmentDetailsService */
            $orderShipmentDetailsService = ServiceRegister::getService(OrderShipmentDetailsService::CLASS_NAME);
            $orderDetails = $orderShipmentDetailsService->getDetailsByOrderId((string)$request->orderId);

            if ($orderDetails) {
                $labels = $orderDetails->getShipmentLabels();
                if (empty($labels)) {
                    /** @var OrderService $orderService */
                    $orderService = ServiceRegister::getService(OrderService::CLASS_NAME);
                    /** @var OrderShipmentDetailsService $orderShipmentDetailsService */
                    $orderShipmentDetailsService = ServiceRegister::getService(OrderShipmentDetailsService::CLASS_NAME);
                    $labels = $orderService->getShipmentLabels($orderDetails->getReference());
                    $orderShipmentDetailsService->setLabelsByReference($orderDetails->getReference(), $labels);
                }

                if (!empty($labels)) {
                    $orderShipmentDetailsService->markLabelPrinted($orderDetails->getReference(), $labels[0]);

                    return $result->setData(['labelLink' => $labels[0]->getLink()]);
                }
            }
        }

        $result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);

        return $result->setData(
            [
                'success' => false,
                'message' => __('Order ID missing.'),
            ]
        );
    }
}
