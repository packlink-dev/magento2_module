<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2020 Packlink
 */

namespace Packlink\PacklinkPro\Block\Adminhtml\Content;

use Magento\Backend\Block\Template;
use Magento\Framework\Module\ModuleListInterface;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\Helper\UrlHelper;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Configuration;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Country\CountryService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\Services\BusinessLogic\ConfigurationService;

/**
 * Class Dashboard
 *
 * @package Packlink\PacklinkPro\Block\Adminhtml\Content
 */
class Dashboard extends Content
{
    /**
     * List of help URLs for different country codes.
     *
     * @var array
     */
    private static $helpUrls = [
        'EN' => 'https://support-pro.packlink.com/hc/en-gb/articles/360011683700-Install-your-Magento-module',
        'ES' => 'https://support-pro.packlink.com/hc/es-es/articles/360011683700-Instala-tu-m%C3%B3dulo-Magento',
        'DE' => 'https://support-pro.packlink.com/hc/de',
        'FR' => 'https://support-pro.packlink.com/hc/fr-fr',
        'IT' => 'https://support-pro.packlink.com/hc/it',
    ];
    /**
     * List of terms and conditions URLs for different country codes.
     *
     * @var array
     */
    private static $termsAndConditionsUrls = [
        'EN' => 'https://support-pro.packlink.com/hc/en-gb/articles/360010011480',
        'ES' => 'https://pro.packlink.es/terminos-y-condiciones/',
        'DE' => 'https://pro.packlink.de/agb/',
        'FR' => 'https://pro.packlink.fr/conditions-generales/',
        'IT' => 'https://pro.packlink.it/termini-condizioni/',
    ];
    /**
     * @var ModuleListInterface
     */
    protected $moduleList;

    /**
     * Dashboard constructor.
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Packlink\PacklinkPro\Bootstrap $bootstrap
     * @param \Packlink\PacklinkPro\Helper\UrlHelper $urlHelper
     * @param \Magento\Framework\Module\ModuleListInterface $moduleList
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Bootstrap $bootstrap,
        UrlHelper $urlHelper,
        ModuleListInterface $moduleList,
        array $data = []
    ) {
        parent::__construct($context, $bootstrap, $urlHelper, $data);

        $this->moduleList = $moduleList;
    }

    /**
     * Returns URL for help documentation in user's preferred language.
     *
     * @return string
     */
    public function getHelpUrl()
    {
        $locale = $this->getUrlLocaleKey();

        return self::$helpUrls[$locale];
    }

    /**
     * Returns URL for Packlink terms and conditions in user's preferred language.
     *
     * @return string
     */
    public function getTermsAndConditionsUrl()
    {
        $locale = $this->getUrlLocaleKey();

        return self::$termsAndConditionsUrls[$locale];
    }

    /**
     * Returns current plugin version.
     *
     * @return string
     */
    public function getPluginVersion()
    {
        return $this->moduleList->getOne('Packlink_PacklinkPro')['setup_version'];
    }

    /**
     * Returns locale for support URLs.
     *
     * @return string
     */
    private function getUrlLocaleKey()
    {
        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        /** @var \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Country\CountryService $countryService */
        $countryService = ServiceRegister::getService(CountryService::CLASS_NAME);

        $userInfo = $configService->getUserInfo();
        $locale = 'EN';
        if ($userInfo !== null && $countryService->isBaseCountry($userInfo->country)) {
            $locale = $userInfo->country;
        }

        return $locale;
    }
}
