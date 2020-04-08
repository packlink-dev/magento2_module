<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2020 Packlink
 */

namespace Packlink\PacklinkPro\Helper;

use Magento\Backend\Model\UrlInterface as MagentoBackendUrl;
use Magento\Framework\Url as MagentoUrl;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class UrlHelper
 *
 * @package Packlink\PacklinkPro\Helper
 */
class UrlHelper
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var MagentoUrl
     */
    private $urlHelper;
    /**
     * @var MagentoBackendUrl
     */
    private $backendUrlHelper;

    /**
     * UrlHelper constructor.
     *
     * @param StoreManagerInterface $storeManager
     * @param MagentoUrl $urlHelper
     * @param MagentoBackendUrl $backendUrlHelper
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        MagentoUrl $urlHelper,
        MagentoBackendUrl $backendUrlHelper
    ) {
        $this->storeManager = $storeManager;
        $this->urlHelper = $urlHelper;
        $this->backendUrlHelper = $backendUrlHelper;
    }

    /**
     * Returns front-end controller URL.
     *
     * @param string $routePath Path.
     * @param array $routeParams Parameters.
     *
     * @return string Publicly visible URL of the requested front-end controller.
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getFrontendUrl($routePath, $routeParams = null)
    {
        $storeView = $this->storeManager->getStore();

        $url = $this->urlHelper->setScope($storeView)->getUrl($routePath, $routeParams);

        if ($routeParams !== null) {
            return $url;
        }

        return explode('?', $url)[0];
    }

    /**
     * Returns back-end controller URL.
     *
     * @param string $routePath Path.
     * @param array $routeParams Parameters.
     *
     * @return string Publicly visible URL of the requested back-end controller.
     */
    public function getBackendUrl($routePath, $routeParams = null)
    {
        return $this->backendUrlHelper->getUrl($routePath, $routeParams);
    }
}
