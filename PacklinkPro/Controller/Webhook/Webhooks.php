<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Webhook;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Webapi\Exception;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\WebHook\WebHookEventHandler;

/**
 * Class Webhooks
 *
 * @package Packlink\PacklinkPro\Controller\Webhook
 */
class Webhooks extends Action
{
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    public function __construct(Context $context, JsonFactory $jsonFactory, Bootstrap $bootstrap)
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
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $input = file_get_contents('php://input');

        $webhookHandler = WebHookEventHandler::getInstance();

        if (empty($input) || !$webhookHandler->handle($input)) {
            $result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);

            return $result->setData(
                [
                    'success' => false,
                    'message' => _('Invalid payload'),
                ]
            );
        }

        return $result->setData(['success' => true]);
    }
}
