<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2020 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Controllers\LoginController;

/**
 * Class Login
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Configuration
 */
class Login extends Configuration
{
    /**
     * @var LoginController
     */
    private $baseController;

    /**
     * Login constructor.
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Packlink\PacklinkPro\Bootstrap $bootstrap
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        Bootstrap $bootstrap,
        JsonFactory $jsonFactory
    ) {
        parent::__construct($context, $bootstrap, $jsonFactory);

        $this->allowedActions = [
            'login',
        ];

        $this->baseController = new LoginController();
    }

    /**
     * Attempts to log the user in with the provided Packlink API key.
     *
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     */
    protected function login()
    {
        $data = $this->getPacklinkPostData();

        $status = $this->baseController->login(!empty($data['apiKey']) ? $data['apiKey'] : '');

        $this->result->setData(['success' => $status]);
    }
}
