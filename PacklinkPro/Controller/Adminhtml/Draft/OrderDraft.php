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
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShipmentDraft\ShipmentDraftService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;

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
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $request = json_decode(file_get_contents('php://input'));

        if (property_exists($request, 'orderId')) {
            $orderId = $request->orderId;

            /** @var ShipmentDraftService $shipmentDraftService */
            $shipmentDraftService = ServiceRegister::getService(ShipmentDraftService::CLASS_NAME);

            try {
                $shipmentDraftService->enqueueCreateShipmentDraftTask((string)$orderId);
            } catch (\Exception $e) {
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
