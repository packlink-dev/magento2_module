<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\Content;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\View\Result\PageFactory;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\Helper\UrlHelper;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Configuration\Configuration;

/**
 * Class Dashboard
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Content
 */
class Dashboard extends Action
{
    /**
     * @var Http
     */
    private $request;
    /**
     * @var PageFactory
     */
    private $resultPageFactory;
    /**
     * @var Resolver
     */
    private $locale;
    /**
     * @var UrlHelper
     */
    private $urlHelper;
    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    private $resultJsonFactory;
    /**
     * @var \Magento\Framework\Controller\Result\Json
     */
    private $result;

    /**
     * Dashboard constructor.
     *
     * @param Context $context
     * @param Http $request
     * @param PageFactory $resultPageFactory
     * @param Bootstrap $bootstrap
     * @param Resolver $locale
     * @param UrlHelper $urlHelper
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        Http $request,
        PageFactory $resultPageFactory,
        Bootstrap $bootstrap,
        Resolver $locale,
        UrlHelper $urlHelper,
        JsonFactory $jsonFactory
    ) {
        parent::__construct($context);

        $this->request = $request;
        $this->resultPageFactory = $resultPageFactory;
        $this->locale = $locale;
        $this->urlHelper = $urlHelper;
        $this->resultJsonFactory = $jsonFactory;
        $this->result = $this->resultJsonFactory->create();

        $bootstrap->initInstance();
    }

    /**
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     */
    public function execute()
    {
        Configuration::setCurrentLanguage(substr($this->locale->getLocale(), 0, 2));

        $action = $this->request->getParam('action');

        if ($action === 'getTemplates') {
            return $this->result->setData($this->getTemplates());
        }

        if ($action === 'getTranslations') {
            return $this->result->setData($this->getTranslations());
        }

        if ($action === 'getUrls') {
            return $this->result->setData($this->getControllerUrls());
        }

        return $this->resultPageFactory->create();
    }

    /**
     * Returns Packlink module templates.
     *
     * @return array
     */
    public function getTemplates()
    {
        $baseDir = __DIR__ . '/../../../view/adminhtml/web/packlink/templates/';

        return [
            'pl-configuration-page' => [
                'pl-main-page-holder' => file_get_contents($baseDir . 'configuration.html'),
            ],
            'pl-countries-selection-modal' => file_get_contents($baseDir . 'countries-selection-modal.html'),
            'pl-default-parcel-page' => [
                'pl-main-page-holder' => file_get_contents($baseDir . 'default-parcel.html'),
            ],
            'pl-default-warehouse-page' => [
                'pl-main-page-holder' => file_get_contents($baseDir . 'default-warehouse.html'),
            ],
            'pl-disable-carriers-modal' => file_get_contents($baseDir . 'disable-carriers-modal.html'),
            'pl-edit-service-page' => [
                'pl-header-section' => '',
                'pl-main-page-holder' => file_get_contents($baseDir . 'edit-shipping-service.html'),
                'pl-pricing-policies' => file_get_contents($baseDir . 'pricing-policies-list.html'),
            ],
            'pl-login-page' => [
                'pl-main-page-holder' => file_get_contents($baseDir . 'login.html'),
            ],
            'pl-my-shipping-services-page' => [
                'pl-main-page-holder' => file_get_contents($baseDir . 'my-shipping-services.html'),
                'pl-header-section' => file_get_contents($baseDir . 'shipping-services-header.html'),
                'pl-shipping-services-table' => file_get_contents($baseDir . 'shipping-services-table.html'),
                'pl-shipping-services-list' => file_get_contents($baseDir . 'shipping-services-list.html'),
            ],
            'pl-onboarding-overview-page' => [
                'pl-main-page-holder' => file_get_contents($baseDir . 'onboarding-overview.html'),
            ],
            'pl-onboarding-welcome-page' => [
                'pl-main-page-holder' => file_get_contents($baseDir . 'onboarding-welcome.html'),
            ],
            'pl-order-status-mapping-page' => [
                'pl-main-page-holder' => file_get_contents($baseDir . 'order-status-mapping.html'),
            ],
            'pl-pick-service-page' => [
                'pl-header-section' => '',
                'pl-main-page-holder' => file_get_contents($baseDir . 'pick-shipping-services.html'),
                'pl-shipping-services-table' => file_get_contents($baseDir . 'shipping-services-table.html'),
                'pl-shipping-services-list' => file_get_contents($baseDir . 'shipping-services-list.html'),
            ],
            'pl-pricing-policy-modal' => file_get_contents($baseDir . 'pricing-policy-modal.html'),
            'pl-register-page' => [
                'pl-main-page-holder' => file_get_contents($baseDir . 'register.html'),
            ],
            'pl-register-modal' => file_get_contents($baseDir . 'register-modal.html'),
            'pl-system-info-modal' => file_get_contents($baseDir . 'system-info-modal.html'),
        ];
    }

    /**
     * Returns Packlink module controller URLs.
     *
     * @return array
     */
    public function getControllerUrls()
    {
        return [
            'login' => [
                'submit' => $this->getControllerUrl('Login', 'login'),
                'listOfCountriesUrl' => $this->getControllerUrl('RegistrationRegions', 'getRegions'),
                'logoPath' => '',
            ],
            'register' => [
                'getRegistrationData' => $this->getControllerUrl('Registration', 'getRegisterData'),
                'submit' => $this->getControllerUrl('Registration', 'register'),
            ],
            'onboarding-state' => [
                'getState' => $this->getControllerUrl('Onboarding', 'getCurrentState'),
            ],
            'onboarding-welcome' => [],
            'onboarding-overview' => [
                'defaultParcelGet' => $this->getControllerUrl('DefaultParcel', 'getDefaultParcel'),
                'defaultWarehouseGet' => $this->getControllerUrl('DefaultWarehouse', 'getDefaultWarehouse'),
            ],
            'default-parcel' => [
                'getUrl' => $this->getControllerUrl('DefaultParcel', 'getDefaultParcel'),
                'submitUrl' => $this->getControllerUrl('DefaultParcel', 'setDefaultParcel'),
            ],
            'default-warehouse' => [
                'getUrl' => $this->getControllerUrl('DefaultWarehouse', 'getDefaultWarehouse'),
                'getSupportedCountriesUrl' => $this->getControllerUrl(
                    'DefaultWarehouse',
                    'getSupportedCountries'
                ),
                'submitUrl' => $this->getControllerUrl('DefaultWarehouse', 'setDefaultWarehouse'),
                'searchPostalCodeUrl' => $this->getControllerUrl('DefaultWarehouse', 'searchPostalCodes'),
            ],
            'configuration' => [
                'getDataUrl' => $this->getControllerUrl('Configuration', 'getData'),
            ],
            'system-info' => [
                'getStatusUrl' => $this->getControllerUrl('Debug', 'getStatus'),
                'setStatusUrl' => $this->getControllerUrl('Debug', 'setStatus'),
            ],
            'order-status-mapping' => [
                'getMappingAndStatusesUrl' => $this->getControllerUrl(
                    'OrderStateMapping',
                    'getMappingsAndStatuses'
                ),
                'setUrl' => $this->getControllerUrl('OrderStateMapping', 'setMappings'),
            ],
            'my-shipping-services' => [
                'getServicesUrl' => $this->getControllerUrl('ShippingMethods', 'getActive'),
                'deleteServiceUrl' => $this->getControllerUrl('ShippingMethods', 'deactivate'),
            ],
            'pick-shipping-service' => [
                'getActiveServicesUrl' => $this->getControllerUrl('ShippingMethods', 'getActive'),
                'getServicesUrl' => $this->getControllerUrl('ShippingMethods', 'getInactive'),
                'getTaskStatusUrl' => $this->getControllerUrl('ShippingMethods', 'getTaskStatus'),
                'startAutoConfigureUrl' => $this->getControllerUrl('AutoConfigure', 'start'),
                'disableCarriersUrl' => '',
            ],
            'edit-service' => [
                'getServiceUrl' => $this->getControllerUrl('ShippingMethods', 'getShippingMethod'),
                'saveServiceUrl' => $this->getControllerUrl('ShippingMethods', 'save'),
                'getTaxClassesUrl' => '',
                'getCountriesListUrl' => '',
                'hasTaxConfiguration' => false,
                'hasCountryConfiguration' => true,
                'canDisplayCarrierLogos' => true,
            ],
        ];
    }

    /**
     * Returns Packlink module translations in the default and the current system language.
     *
     * @return array
     */
    public function getTranslations()
    {
        return [
            'default' => $this->getDefaultTranslations(),
            'current' => $this->getCurrentTranslations(),
        ];
    }

    /**
     * Returns JSON encoded module page translations in the default language and some module-specific translations.
     *
     * @return string
     */
    private function getDefaultTranslations()
    {
        $baseDir = __DIR__ . '/../../../view/adminhtml/web/packlink/lang/';

        return json_decode(file_get_contents($baseDir . 'en.json'), true);
    }

    /**
     * Returns JSON encoded module page translations in the current language and some module-specific translations.
     *
     * @return string
     */
    private function getCurrentTranslations()
    {
        $baseDir = __DIR__ . '/../../../view/adminhtml/web/packlink/lang/';
        $locale = Configuration::getCurrentLanguage();

        return json_decode(file_get_contents($baseDir . $locale . '.json'), true);
    }

    private function getControllerUrl($controllerName, $action)
    {
        return $this->urlHelper->getBackendUrl(
            'packlink/configuration/' . strtolower($controllerName),
            [
                'action' => $action,
                'ajax' => 1
            ]
        );
    }
}
