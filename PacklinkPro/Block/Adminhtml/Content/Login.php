<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Block\Adminhtml\Content;

use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Magento\Backend\Block\Template;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Country\CountryService;

/**
 * Class Login
 *
 * @package Packlink\PacklinkPro\Block\Adminhtml\Content
 */
class Login extends Template
{
    /**
     * Login constructor.
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Packlink\PacklinkPro\Bootstrap $bootstrap
     * @param array $data
     */
    public function __construct(Template\Context $context, Bootstrap $bootstrap, array $data = [])
    {
        parent::__construct($context, $data);

        $bootstrap->initInstance();
    }

    /**
     * Returns countries supported by Packlink.
     *
     * @return \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Country\Country[]
     */
    public function getSupportedCountries()
    {
        /** @var CountryService $countryService */
        $countryService = ServiceRegister::getService(CountryService::CLASS_NAME);

        $supportedCountries = $countryService->getSupportedCountries();

        foreach ($supportedCountries as $country) {
            $country->name = __($country->name);
        }

        return $supportedCountries;
    }
}
