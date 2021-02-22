<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2021 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Controllers\RegistrationController;

/**
 * Class Registration
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Configuration
 */
class Registration extends Configuration
{
    /**
     * Array that identifies e-commerce.
     *
     * @var string[]
     */
    protected static $ecommerceIdentifiers = ['Magento'];

    /**
     * @var RegistrationController
     */
    private $baseController;

    /**
     * Registration constructor.
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
            'getRegisterData',
            'register'
        ];

        $this->baseController = new RegistrationController();
    }

    /**
     * Returns registration data.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function getRegisterData()
    {
        $request = $this->getRequest();

        if (!$request->getParam('country')) {
            return $this->formatNotFoundResponse();
        }

        return $this->result->setData($this->baseController->getRegisterData($request->getParam('country')));
    }

    /**
     * Attemps to register the user on Packlink PRO.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function register()
    {
        $payload = $this->getPacklinkPostData();

        $payload['ecommerces'] = static::$ecommerceIdentifiers;

        try {
            $status = $this->baseController->register($payload);
            return $this->result->setData(['success' => $status]);
        } catch (\Exception $e) {
            return $this->result->setData(
                [
                    'success' => false,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }
}
