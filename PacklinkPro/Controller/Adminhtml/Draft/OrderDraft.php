<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\Draft;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Webapi\Exception;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Interfaces\OrderRepository;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException;
use Packlink\PacklinkPro\Services\BusinessLogic\OrderRepositoryService;

/**
 * Class OrderDraft
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml
 */
class OrderDraft extends Action
{
    /**
     * Array of actions which can be processed without secret key validation
     *
     * @var array
     */
    protected $_publicActions = ['orderdraft'];
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
     * Executes action based on request and return result.
     *
     * Note: Request will be added as operation argument in future
     *
     * @return Json
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
            $orderId = $request->orderId;

            /** @var OrderRepositoryService $orderRepositoryService */
            $orderRepositoryService = ServiceRegister::getService(OrderRepository::CLASS_NAME);

            try {
                $orderRepositoryService->createOrderDraft($orderId);
            } catch (QueueStorageUnavailableException $e) {
                $result->setHttpResponseCode(Exception::HTTP_INTERNAL_ERROR);

                return $result->setData(
                    [
                        'success' => false,
                        'message' => __($e->getMessage()),
                    ]
                );
            }

            return $result->setData(['success' => true]);
        }

        $result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);

        return $result->setData(
            [
                'success' => false,
                'message' => __('Order details not found'),
            ]
        );
    }
}
