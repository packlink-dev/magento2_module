<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2022 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\Content;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\Result\PageFactory;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\CountryLabels\Interfaces\CountryService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Configuration\Configuration;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\Interfaces\TaskRunnerWakeup;

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
     * @var \Magento\Framework\Controller\Result\Json
     */
    private $result;
    /**
     * @var Session
     */
    private $authSession;

    /**
     * Dashboard constructor.
     *
     * @param Context $context
     * @param Http $request
     * @param PageFactory $resultPageFactory
     * @param Bootstrap $bootstrap
     * @param JsonFactory $jsonFactory
     * @param Session $session
     */
    public function __construct(
        Context $context,
        Http $request,
        PageFactory $resultPageFactory,
        Bootstrap $bootstrap,
        JsonFactory $jsonFactory,
        Session $session
    ) {
        parent::__construct($context);

        $this->request = $request;
        $this->resultPageFactory = $resultPageFactory;
        $this->result = $jsonFactory->create();
        $this->authSession = $session;

        $bootstrap->initInstance();
    }

    /**
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     */
    public function execute()
    {
        $this->wakeUp();

        $user = $this->authSession->getUser();

        if ($user) {
            $locale = substr($user->getInterfaceLocale(), 0, 2);
            Configuration::setUICountryCode(
                in_array($locale, ['en', 'de', 'es', 'fr', 'it']) ? $locale : 'en'
            );
        }

        $action = $this->request->getParam('action');

        if ($action === 'getTemplates') {
            return $this->result->setData($this->getTemplates());
        }

        if ($action === 'getTranslations') {
            return $this->result->setData($this->getTranslations());
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
        $baseDirOverride =  __DIR__ . '/../../../view/adminhtml/web/templates/override/';

        return [
            'pl-configuration-page' => [
                'pl-main-page-holder' => file_get_contents($baseDirOverride . 'configuration.html'),
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
     * Returns Packlink module translations in the default and the current system language.
     *
     * @return array
     */
    public function getTranslations()
    {
        $locale = Configuration::getUICountryCode();

        /** @var \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\CountryLabels\CountryService $countryService */
        $countryService = ServiceRegister::getService(CountryService::CLASS_NAME);
        $default = $countryService->getAllLabels('en');
        $current = $countryService->getAllLabels($locale);

        return [
            'default' => $default['en'],
            'current' => $current[$locale],
        ];
    }

    /**
     * Task runner wakeup.
     */
    private function wakeUp()
    {
        /** @var TaskRunnerWakeup $taskRunnerWakeupService */
        $taskRunnerWakeupService = ServiceRegister::getService(TaskRunnerWakeup::CLASS_NAME);
        $taskRunnerWakeupService->wakeup();
    }
}
