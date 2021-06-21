<?php

namespace Packlink\PacklinkPro\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Controllers\SystemInfoController;

/**
 * Class SystemInfo
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Configuration
 */
class SystemInfo extends Configuration
{
    /**
     * @var SystemInfoController
     */
    private $baseController;

    /**
     * SystemInfo constructor.
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

        $this->baseController = new SystemInfoController();

        $this->allowedActions = ['get'];
    }

    /**
     * Returns list of system info details.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function get()
    {
        $systemDetails = $this->baseController->get();

        return $this->formatDtoEntitiesResponse($systemDetails);
    }
}
