<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\Content;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\View\Result\PageFactory;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Configuration;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\Interfaces\TaskRunnerWakeup;
use Packlink\PacklinkPro\Services\BusinessLogic\ConfigurationService;

/**
 * Class AutoTest.
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Content
 */
class AutoTest extends Action
{
    const DASHBOARD_STATE_CODE = 'dashboard';
    const LOGIN_STATE_CODE = 'login';
    /**
     * @var Http
     */
    private $request;
    /**
     * @var PageFactory
     */
    private $resultPageFactory;

    /**
     * Initialize Group Controller
     *
     * @param Context $context
     * @param Http $request
     * @param PageFactory $resultPageFactory
     * @param Bootstrap $bootstrap
     */
    public function __construct(
        Context $context,
        Http $request,
        PageFactory $resultPageFactory,
        Bootstrap $bootstrap
    ) {
        parent::__construct($context);

        $this->request = $request;
        $this->resultPageFactory = $resultPageFactory;

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
        $this->redirectHandler();

        return $this->resultPageFactory->create();
    }

    /**
     * Redirects to a proper action if needed.
     */
    protected function redirectHandler()
    {
        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        $apiToken = $configService->getAuthorizationToken();

        if ($apiToken) {
            /** @var TaskRunnerWakeup $wakeupService */
            $wakeupService = ServiceRegister::getService(TaskRunnerWakeup::CLASS_NAME);
            $wakeupService->wakeup();

            $this->redirectIfNecessary(self::DASHBOARD_STATE_CODE, 'packlink/content/dashboard');
        } else {
            $this->redirectIfNecessary(self::LOGIN_STATE_CODE, 'packlink/content/login');
        }
    }

    // /**
    //  * @inheritDoc
    //  */
    // public function _processUrlKeys()
    // {
    //     return $this->_auth->isLoggedIn();
    // }

    /**
     * @inheritDoc
     */
    protected function redirectIfNecessary($currentAction, $redirectUrl)
    {
        return null;
    }
}
