<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
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
    }

    /**
     * Returns current setup status.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function start()
    {
        $controller = new AutoConfigurationController();

        return $this->result->setData(['success' => $controller->start(true)]);
    }
}
