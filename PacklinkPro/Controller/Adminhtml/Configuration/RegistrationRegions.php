<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2021 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Controller\Result\JsonFactory;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Configuration as ConfigService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Controllers\RegistrationRegionsController;

/**
 * Class RegistrationRegions
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Configuration
 */
class RegistrationRegions extends Configuration
{
    /**
     * @var RegistrationRegionsController
     */
    private $baseController;
    /**
     * @var Session
     */
    private $authSession;

    /**
     * RegistrationRegions constructor.
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Packlink\PacklinkPro\Bootstrap $bootstrap
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param \Magento\Backend\Model\Auth\Session $session
     */
    public function __construct(
        Context $context,
        Bootstrap $bootstrap,
        JsonFactory $jsonFactory,
        Session $session
    ) {
        parent::__construct($context, $bootstrap, $jsonFactory);

        $this->allowedActions = [
            'getRegions',
        ];

        $this->authSession = $session;
        $this->baseController = new RegistrationRegionsController();
    }

    /**
     * Returns regions available for Packlink account registration.
     */
    protected function getRegions()
    {
        $user = $this->authSession->getUser();

        if ($user) {
            $locale = substr($user->getInterfaceLocale(), 0, 2);
            ConfigService::setCurrentLanguage(
                in_array($locale, ['en', 'de', 'es', 'fr', 'it']) ? $locale : 'en'
            );
        }

        return $this->formatDtoEntitiesResponse($this->baseController->getRegions());
    }
}
