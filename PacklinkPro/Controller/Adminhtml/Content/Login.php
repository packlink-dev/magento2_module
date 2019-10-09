<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\Content;

use Magento\Framework\App\Request\Http;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Configuration;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\User\UserAccountService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\Services\BusinessLogic\ConfigurationService;

/**
 * Class Login
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Content
 */
class Login extends Routing
{
    /**
     * Redirects to a proper action if needed.
     *
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     */
    protected function redirectHandler()
    {
        parent::redirectHandler();

        /** @var Http $request */
        $request = $this->getRequest();
        if ($request->has('api_key')) {
            if ($this->login($request)) {
                $this->_redirect('packlink/content/dashboard');
            }

            $this->messageManager->addErrorMessage(__('API key was incorrect.'));
        }
    }

    /**
     * @param Http $request Magento HTTP request.
     *
     * @return bool Returns TRUE if login finished successfully. Otherwise, returns FALSE.
     *
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
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
}
