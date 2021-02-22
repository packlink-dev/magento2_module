<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2021 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\Content;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\View\Result\PageFactory;
use Packlink\PacklinkPro\Bootstrap;

/**
 * Class AutoTest.
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Content
 */
class AutoTest extends Action
{
    /**
     * @var PageFactory
     */
    private $resultPageFactory;

    /**
     * Initialize Group Controller
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param Bootstrap $bootstrap
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Bootstrap $bootstrap
    ) {
        parent::__construct($context);

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
        return $this->resultPageFactory->create();
    }
}
