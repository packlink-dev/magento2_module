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
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Controllers\OnboardingController;

/**
 * Class Onboarding
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Configuration
 */
class Onboarding extends Configuration
{
    /**
     * @var OnboardingController
     */
    private $baseController;

    /**
     * Onboarding constructor.
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
            'getCurrentState',
        ];

        $this->baseController = new OnboardingController();
    }

    /**
     * Returns the current state of the on-boarding page.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function getCurrentState()
    {
        return $this->result->setData($this->baseController->getCurrentState()->toArray());
    }
}
