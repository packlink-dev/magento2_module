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
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\User\UserAccountService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\Interfaces\TaskRunnerWakeup;
use Packlink\PacklinkPro\Services\BusinessLogic\ConfigurationService;

/**
 * Class Routing
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Content
 */
class Routing extends Action
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
     *
     * @throws QueueStorageUnavailableException
     */
    public function execute()
    {
        $this->redirectHandler();

        return $this->resultPageFactory->create();
    }

    /**
     * @throws QueueStorageUnavailableException
     */
    private function redirectHandler()
    {
        /** @var Http $request */
        $request = $this->getRequest();

        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        $apiToken = $configService->getAuthorizationToken();

        if ($apiToken) {
            /** @var TaskRunnerWakeup $wakeupService */
            $wakeupService = ServiceRegister::getService(TaskRunnerWakeup::CLASS_NAME);
            $wakeupService->wakeup();

            return $this->redirectIfNecessary(self::DASHBOARD_STATE_CODE, 'packlink/content/dashboard');
        }

        if ($request->has('api_key')) {
            if ($this->login($request)) {
                return $this->redirectIfNecessary(self::DASHBOARD_STATE_CODE, 'packlink/content/dashboard');
            }

            $this->messageManager->addErrorMessage(__('API key was incorrect.'));
        }

        return $this->redirectIfNecessary(self::LOGIN_STATE_CODE, 'packlink/content/login');
    }

    /**
     * @param Http $request Magento HTTP request.
     *
     * @return bool Returns TRUE if login finished successfully. Otherwise, returns FALSE.
     *
     * @throws QueueStorageUnavailableException
     */
    private function login($request)
    {
        $apiKey = $request->get('api_key');
        $result = false;
        try {
            /** @var UserAccountService $userAccountService */
            $userAccountService = ServiceRegister::getService(UserAccountService::CLASS_NAME);
            $result = $userAccountService->login($apiKey);
        } catch (\RuntimeException $e) {
            /** @var ConfigurationService $configService */
            $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
            if ($configService->getAuthorizationToken() !== null) {
                $configService->setAuthorizationToken(null);
            }
        }

        return $result;
    }

    /**
     * @param $currentAction
     * @param $redirectUrl
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    private function redirectIfNecessary($currentAction, $redirectUrl)
    {
        $actionName = $this->request->getActionName();
        if ($actionName !== $currentAction) {
            return $this->_redirect($redirectUrl);
        }

        return null;
    }
}
