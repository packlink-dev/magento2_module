<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\ShipmentLabels;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Webapi\Exception;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Interfaces\OrderRepository;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\OrderService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Http\DTO\ShipmentLabel;
use Packlink\PacklinkPro\Services\BusinessLogic\OrderRepositoryService;

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
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $request = json_decode(file_get_contents('php://input'));

        if (property_exists($request, 'orderId')) {
            /** @var OrderRepositoryService $orderRepository */
            $orderRepository = ServiceRegister::getService(OrderRepository::CLASS_NAME);
            $orderDetails = $orderRepository->getOrderDetailsById((int) $request->orderId);

            if ($orderDetails) {
                $labels = $orderDetails->getShipmentLabels();
                if (empty($labels)) {
                    /** @var OrderService $orderService */
                    $orderService = ServiceRegister::getService(OrderService::CLASS_NAME);
                    $labels = $orderService->getShipmentLabels($orderDetails->getShipmentReference());
                    $labels = array_map(function (ShipmentLabel $label) {
                        $label->setPrinted(true);

                        return $label;
                    }, $labels);
                    $orderDetails->setShipmentLabels($labels);
                }

                if (!empty($labels)) {
                    $orderRepository->saveOrderDetails($orderDetails);
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
