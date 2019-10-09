<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Controller\AsyncProcess;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Webapi\Exception;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\AutoTest\AutoTestService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\Logger;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\AsyncProcessStarterService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\Interfaces\AsyncProcessService;

/**
 * Class AsyncProcess
 *
 * @package Packlink\PacklinkPro\Controller\AsyncProcess
 */
class AsyncProcess extends Action
{
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * AsyncProcess constructor.
     *
     * @param Context $context
     * @param Bootstrap $bootstrap
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(Context $context, Bootstrap $bootstrap, JsonFactory $resultJsonFactory)
    {
        parent::__construct($context);

        $this->resultJsonFactory = $resultJsonFactory;

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
        $guid = $this->getRequest()->getParam('guid');
        $autoTest = $this->getRequest()->getParam('auto-test');

        if ($autoTest) {
            $autoTestService = new AutoTestService();
            $autoTestService->setAutoTestMode();
            Logger::logInfo('Received auto-test async process request.', 'Integration', ['guid' => $guid]);
        } else {
            Logger::logDebug('Received async process request.', 'Integration', ['guid' => $guid]);
        }

        if (!$guid) {
            $result = $this->resultJsonFactory->create();
            $result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);
            $result->setData(
                [
                    'success' => false,
                    'message' => _('guid is missing'),
                ]
            );

            return $result;
        }

        if ($guid !== 'auto-configure') {
            /** @var AsyncProcessStarterService $asyncProcessService */
            $asyncProcessService = ServiceRegister::getService(AsyncProcessService::CLASS_NAME);
            $asyncProcessService->runProcess($guid);
        }

        return $this->resultJsonFactory->create()->setData(['success' => true]);
    }
}
