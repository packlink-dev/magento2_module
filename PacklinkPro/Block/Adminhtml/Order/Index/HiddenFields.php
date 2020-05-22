<?php

namespace Packlink\PacklinkPro\Block\Adminhtml\Order\Index;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget;
use Magento\Framework\View\Asset\Repository;
use Packlink\PacklinkPro\Helper\UrlHelper;

/**
 * Class HiddenFields
 *
 * @package Packlink\PacklinkPro\Block\Adminhtml\Order\Index
 */
class HiddenFields extends Widget
{
    /**
     * @var Repository
     */
    protected $assetRepo;
    /**
     * @var UrlHelper
     */
    private $urlHelper;

    public function __construct(
        Repository $assetRepository,
        UrlHelper $urlHelper,
        Context $context,
        array $data = []
    ) {
        $this->assetRepo = $assetRepository;
        $this->urlHelper = $urlHelper;

        parent::__construct($context, $data);
    }

    /**
     * Returns Packlink logo path.
     *
     * @return string
     */
    public function getLogoPath()
    {
        return $this->assetRepo->getUrl('Packlink_PacklinkPro::images/logo.png');
    }

    /**
     * Returns URL of the order draft controller.
     *
     * @return string URL of the order draft controller.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getDraftControllerUrl()
    {
        return $this->getBackendControllerUrl('packlink/draft/orderdraft');
    }

    /**
     * Returns URL of the order draft status controller.
     *
     * @return string URL of the order draft status controller.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getDraftStatusUrl()
    {
        return $this->getBackendControllerUrl('packlink/draft/draftstatus');
    }

    /**
     * Returns backend controller URL for the provided controller path.
     *
     * @param string $path
     *
     * @return string
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getBackendControllerUrl($path)
    {
        return $this->urlHelper->getBackendUrl(
            $path,
            [
                'ajax' => 1,
                'form_key' => $this->formKey->getFormKey(),
            ]
        );
    }
}
