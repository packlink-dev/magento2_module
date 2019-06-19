<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Services\BusinessLogic;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Store\Model\StoreManagerInterface;
use Packlink\PacklinkPro\Helper\UrlHelper;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Configuration;

/**
 * Class ConfigurationService
 *
 * @package Packlink\PacklinkPro\Services\BusinessLogic
 */
class ConfigurationService extends Configuration
{
    /**
     * Singleton instance of this class.
     *
     * @var static
     */
    protected static $instance;
    /**
     * Magento store manager interface.
     *
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * UrlHelper helper class.
     *
     * @var UrlHelper
     */
    private $urlHelper;
    /**
     * @var \Magento\Framework\App\ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * ConfigurationService constructor.
     *
     * @param \Magento\Framework\App\ProductMetadataInterface $productMetadata
     * @param StoreManagerInterface $storeManager Magento store manager interface.
     * @param UrlHelper $urlHelper Url helper.
     */
    public function __construct(
        ProductMetadataInterface $productMetadata,
        StoreManagerInterface $storeManager,
        UrlHelper $urlHelper
    ) {
        parent::__construct();

        $this->productMetadata = $productMetadata;
        $this->storeManager = $storeManager;
        $this->urlHelper = $urlHelper;

        static::$instance = $this;
    }

    /**
     * Retrieves integration name.
     *
     * @return string Integration name.
     */
    public function getIntegrationName()
    {
        return 'Magento';
    }

    /**
     * Returns current system identifier.
     *
     * @return string Current system identifier.
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCurrentSystemId()
    {
        return $this->storeManager->getStore()->getId();
    }

    /**
     * Returns async process starter url, always in http.
     *
     * @param string $guid Process identifier.
     *
     * @return string Formatted URL of async process starter endpoint.
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getAsyncProcessUrl($guid)
    {
        $params = [
            'guid' => $guid,
            'ajax' => 1,
        ];

        return $this->urlHelper->getFrontendUrl('packlink/asyncprocess/asyncprocess', $params);
    }

    /**
     * Returns web-hook callback URL for current system.
     *
     * @return string Web-hook callback URL.
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getWebHookUrl()
    {
        return $this->urlHelper->getFrontendUrl('packlink/webhook/webhooks');
    }

    /**
     * Returns order draft source.
     *
     * @return string Order draft source.
     */
    public function getDraftSource()
    {
        return 'module_magento';
    }

    /**
     * Gets the current version of the module/integration.
     *
     * @return string The version number.
     */
    public function getModuleVersion()
    {
        return json_decode(file_get_contents(__DIR__ . '/../../composer.json'), true)['version'];
    }

    /**
     * Gets the name of the integrated e-commerce system.
     * This name is related to Packlink API which can be different from the official system name.
     *
     * @return string The e-commerce name.
     */
    public function getECommerceName()
    {
        return 'magento';
    }

    /**
     * Gets the current version of the integrated e-commerce system.
     *
     * @return string The version number.
     */
    public function getECommerceVersion()
    {
        return $this->productMetadata->getVersion();
    }
}
