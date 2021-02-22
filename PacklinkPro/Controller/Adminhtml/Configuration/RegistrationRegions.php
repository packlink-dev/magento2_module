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
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Controllers\RegistrationRegionsController;

/**
 * Class RegistrationRegions
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Configuration
 */
class RegistrationRegions extends Configuration
{
    /**
     * @var RegistrationRegionsController
     */
    private $baseController;

    /**
     * RegistrationRegions constructor.
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
            'getRegions',
        ];

        $this->baseController = new RegistrationRegionsController();
    }

    /**
     * Returns regions available for Packlink account registration.
     */
    protected function getRegions()
    {
        return $this->formatDtoEntitiesResponse($this->baseController->getRegions());
    }
}
