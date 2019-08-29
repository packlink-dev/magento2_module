<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Block\Adminhtml\Content;

use Magento\Backend\Block\Template;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\Helper\UrlHelper;

/**
 * Class AutoTest.
 *
 * @package Packlink\PacklinkPro\Block\Adminhtml\Content
 */
class AutoTest extends Template
{
    /**
     * @var UrlHelper
     */
    public $urlHelper;

    /**
     * AutoTest constructor.
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Packlink\PacklinkPro\Bootstrap $bootstrap
     * @param \Packlink\PacklinkPro\Helper\UrlHelper $urlHelper
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Bootstrap $bootstrap,
        UrlHelper $urlHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->urlHelper = $urlHelper;

        $bootstrap->initInstance();
    }

    /**
     * Returns URL to backend controller that provides data for the configuration page.
     *
     * @param string $controllerName Name of the configuration controller.
     * @param string $action Controller action.
     *
     * @return string URL to backend configuration controller.
     */
    public function getControllerUrl($controllerName, $action)
    {
        return $this->urlHelper->getBackendUrl(
            'packlink/configuration/' . strtolower($controllerName),
            [
                'action' => $action,
                'ajax' => 1,
                'form_key' => $this->formKey->getFormKey(),
            ]
        );
    }
}
