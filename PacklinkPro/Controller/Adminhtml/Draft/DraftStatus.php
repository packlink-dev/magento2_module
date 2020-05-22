<?php

namespace Packlink\PacklinkPro\Controller\Adminhtml\Draft;

use http\Exception\InvalidArgumentException;
use Magento\Backend\App\Action;

use Packlink\PacklinkPro\Bootstrap;
use Magento\Framework\Controller\Result\JsonFactory;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\OrderShipmentDetails\Exceptions\OrderShipmentDetailsNotFound;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\OrderShipmentDetails\OrderShipmentDetailsService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShipmentDraft\ShipmentDraftService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\QueueItem;

/**
 * Class DraftStatus
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Draft
 */
class DraftStatus extends Action
{
    /**
     * Array of actions which can be processed without secret key validation
     *
     * @var array
     */
    protected $_publicActions = ['draftstatus'];
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
     * @inheritDoc
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     *
     * @throws \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\OrderShipmentDetails\Exceptions\OrderShipmentDetailsNotFound
     */
    public function execute()
    {
        $orderId = $this->getRequest()->getParam('orderId');

        if (!$orderId) {
            throw new InvalidArgumentException('Order ID missing.');
        }

        $result = $this->resultJsonFactory->create();
        /** @var ShipmentDraftService $draftService */
        $draftService = ServiceRegister::getService(ShipmentDraftService::CLASS_NAME);
        $draftStatus = $draftService->getDraftStatus($orderId);

        if ($draftStatus->status === QueueItem::COMPLETED) {
            /** @var OrderShipmentDetailsService $orderShipmentDetailsService */
            $orderShipmentDetailsService = ServiceRegister::getService(OrderShipmentDetailsService::CLASS_NAME);
            $shipmentDetails = $orderShipmentDetailsService->getDetailsByOrderId($orderId);

            if ($shipmentDetails === null) {
                throw new OrderShipmentDetailsNotFound('Order details not found.');
            }

            return $result->setData(
                [
                    'status' => 'created',
                    'shipment_url' => $shipmentDetails->getShipmentUrl(),
                ]
            );
        }

        $response = [
            'status' => $draftStatus->status,
            'shipment_url' => '',
        ];

        if ($draftStatus->status === QueueItem::ABORTED) {
            $response['message'] = $draftStatus->message;
        }

        return $result->setData($response);
    }
}
