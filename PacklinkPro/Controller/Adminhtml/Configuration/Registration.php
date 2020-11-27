<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2020 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Country\CountryService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;

/**
 * Class Registration
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Configuration
 */
class Registration extends Configuration
{
    /**
     * Registration constructor.
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

        $this->allowedActions = [
            'getSupportedCountries',
        ];
    }

    protected function getSupportedCountries()
    {
        /** @var CountryService $countryService */
        $countryService = ServiceRegister::getService(CountryService::CLASS_NAME);
        $supportedCountries = $countryService->getSupportedCountries();

        foreach ($supportedCountries as $country) {
            $country->registrationLink = str_replace('magento', 'pro', $country->registrationLink);
            $country->name = __($country->name);
        }

        return $this->formatDtoEntitiesResponse($supportedCountries);
    }
}
