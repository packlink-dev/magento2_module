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
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
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

        if (property_exists($request, 'orderId') && property_exists($request, 'link')) {
            /** @var OrderRepositoryService $orderRepository */
            $orderRepository = ServiceRegister::getService(OrderRepository::CLASS_NAME);
            $orderRepository->setLabelPrinted($request->orderId, $request->link);

            return $result->setData(['success' => true]);
        }

        $result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);

        return $result->setData(
            [
                'success' => false,
                'message' => __('Order ID and/or link missing'),
            ]
        );
    }
}
