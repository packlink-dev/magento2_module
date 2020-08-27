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
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Controllers\AutoConfigurationController;

/**
 * Class AutoConfigure.
 *
 * @package \Packlink\PacklinkPro\Controller\Adminhtml\Configuration
 */
class AutoConfigure extends Configuration
{
    /**
     * @var AutoConfigurationController
     */
    private $baseController;

    /**
     * AutoConfigure constructor.
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

        $this->allowedActions = ['start'];

        $this->baseController = new AutoConfigurationController();
    }

    /**
     * Returns current setup status.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function start()
    {
        return $this->result->setData(['success' => $this->baseController->start(true)]);
    }
}
