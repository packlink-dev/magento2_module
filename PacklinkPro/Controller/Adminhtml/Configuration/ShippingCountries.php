<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2021 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Directory\Model\AllowedCountries;
use Magento\Directory\Model\ResourceModel\Country\Collection;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\ScopeInterface;
use Packlink\PacklinkPro\Bootstrap;

/**
 * Class ShippingCountries
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Configuration
 */
class ShippingCountries extends Configuration
{
    /**
     * @var \Magento\Directory\Model\AllowedCountries
     */
    private $allowedCountriesManager;
    /**
     * @var \Magento\Directory\Model\ResourceModel\Country\Collection
     */
    private $countryCollection;

    /**
     * ShippingCountries constructor.
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Packlink\PacklinkPro\Bootstrap $bootstrap
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param \Magento\Directory\Model\AllowedCountries $allowedCountriesManager
     * @param \Magento\Directory\Model\ResourceModel\Country\Collection $countryCollection
     */
    public function __construct(
        Context $context,
        Bootstrap $bootstrap,
        JsonFactory $jsonFactory,
        AllowedCountries $allowedCountriesManager,
        Collection $countryCollection
    ) {
        parent::__construct($context, $bootstrap, $jsonFactory);

        $this->allowedActions = [
            'getAll',
        ];

        $this->allowedCountriesManager = $allowedCountriesManager;
        $this->countryCollection = $countryCollection;
    }

    /**
     * Retrieves list of available countries.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function getAll()
    {
        $codes = $this->allowedCountriesManager->getAllowedCountries(ScopeInterface::SCOPE_STORES);
        $this->countryCollection->addCountryIdFilter($codes);
        $countries = $this->countryCollection->toOptionArray();

        return $this->result->setData($this->formatCountries($countries));
    }

    /**
     * Filters countries for response.
     *
     * @param array $countries
     *
     * @return array
     */
    protected function formatCountries(array $countries)
    {
        $filteredCountries = array_filter(
            $countries,
            function ($item) {
                return !empty($item['value']) && !empty($item['label']);
            }
        );

        $formattedCountries = array_map(
            function ($item) {

                return [
                    'value' => $item['value'],
                    'label' => $item['label'],
                ];
            },
            $filteredCountries
        );

        return array_values($formattedCountries);
    }
}
