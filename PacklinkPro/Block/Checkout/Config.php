<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Block\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\View\Element\Template;
use Magento\Quote\Model\Quote\Address;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\Helper\CarrierLogoHelper;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Http\DTO\ParcelInfo;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Models\ShippingMethod;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\Logger;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\RepositoryRegistry;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\Services\BusinessLogic\ConfigurationService;

/**
 * Class Config.
 *
 * @package Packlink\PacklinkPro\Block\Checkout
 */
class Config extends Template
{
    /**
     * @var Session
     */
    private $checkoutSession;
    /**
     * @var CarrierLogoHelper
     */
    private $carrierLogoHelper;
    /**
     * @var \Magento\Framework\Locale\Resolver
     */
    private $locale;

    /**
     * Config constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Checkout\Model\Session $session
     * @param \Magento\Framework\Locale\Resolver $locale
     * @param \Packlink\PacklinkPro\Bootstrap $bootstrap
     * @param \Packlink\PacklinkPro\Helper\CarrierLogoHelper $logoHelper
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Session $session,
        Resolver $locale,
        Bootstrap $bootstrap,
        CarrierLogoHelper $logoHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->checkoutSession = $session;
        $this->locale = $locale;
        $this->carrierLogoHelper = $logoHelper;

        $bootstrap->initInstance();
    }

    /**
     * Returns ID of the current quote.
     *
     * @return int Quote ID.
     */
    public function getQuoteId()
    {
        return $this->checkoutSession->getQuoteId();
    }

    /**
     * Returns the current quote.
     *
     * @return \Magento\Quote\Model\Quote Quote.
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getQuote()
    {
        return $this->checkoutSession->getQuote();
    }

    /**
     * Returns the current quote.
     *
     * @return array
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getQuoteAddresses()
    {
        $result = [];
        /** @var Address $address */
        foreach ($this->getQuote()->getAllShippingAddresses() as $address) {
            $id = $address->getId();
            $result[$id] = [
                'id' => $id,
                'countryId' => $address->getCountryId(),
                'postcode' => $address->getPostcode(),
            ];
        }

        return $result;
    }

    /**
     * Returns array of item weights in current quote grouped by address.
     *
     * @return array
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getQuoteItemWeights()
    {
        $weights = null;
        $quote = $this->checkoutSession->getQuote();

        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(ConfigurationService::CLASS_NAME);
        $parcel = $configService->getDefaultParcel() ?: ParcelInfo::defaultParcel();

        if ($quote) {
            /** @var \Magento\Quote\Model\Quote\Address $address */
            foreach ($quote->getAllShippingAddresses() as $address) {
                $totalWeight = 0;
                /** @var \Magento\Quote\Model\Quote\Item $item */
                foreach ($address->getAllVisibleItems() as $item) {
                    $weight = (float)$item->getWeight() ?: $parcel->weight;
                    $totalWeight += $weight * $item->getQty();
                }

                $weights[$address->getId()] = $totalWeight;
            }
        }

        return $weights ?: [0];
    }

    /**
     * Returns shipping address country code.
     *
     * @return string Shipping address country code.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCountryCode()
    {
        $countryId = '';
        $quote = $this->checkoutSession->getQuote();

        if ($quote) {
            $countryId = $quote->getShippingAddress()->getCountryId();
        }

        return $countryId;
    }

    /**
     * Returns shipping address postcode.
     *
     * @return string Shipping address postcode.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getPostcode()
    {
        $postcode = '';
        $quote = $this->checkoutSession->getQuote();

        if ($quote) {
            $postcode = $quote->getShippingAddress()->getPostcode();
        }

        return $postcode;
    }

    /**
     * Returns base shop URL.
     *
     * @return string Base URL of the shop.
     */
    public function getBaseShopUrl()
    {
        return $this->getBaseUrl();
    }

    /**
     * Returns array of all methods that have logos enabled on them.
     *
     * @return array Array of key-value pairs in following format: <shipping_method_ID> => <carrier_logo_URL>
     */
    public function getMethodsWithEnabledLogos()
    {
        $result = [];

        try {
            $repository = RepositoryRegistry::getRepository(ShippingMethod::CLASS_NAME);

            /** @var ShippingMethod[] $shippingMethods */
            $shippingMethods = $repository->select();

            foreach ($shippingMethods as $shippingMethod) {
                if ($shippingMethod->isDisplayLogo()) {
                    $result[$shippingMethod->getId()] = $this->getCarrierLogoUrl($shippingMethod->getId());
                }
            }
        } catch (RepositoryNotRegisteredException $e) {
            Logger::logError(__('Repository not registered'), 'Integration');
        }

        return $result;
    }

    /**
     * Returns URL to default carrier logo URL.
     *
     * @return string
     */
    public function getDefaultCarrierLogoUrl()
    {
        return $this->carrierLogoHelper->getDefaultCarrierLogoPath();
    }

    /**
     * Gets current language code. For example: en.
     *
     * @return string
     */
    public function getCurrentLanguageCode()
    {
        $locale = $this->locale->getLocale();

        return $locale ? substr($locale, 0, 2) : 'en';
    }

    /**
     * Returns carrier logo URL for shipping method with provided ID.
     *
     * @param int $shippingMethodId ID of the shipping method.
     *
     * @return string Carrier logo URL.
     */
    private function getCarrierLogoUrl($shippingMethodId)
    {
        /** @var \Packlink\PacklinkPro\Services\BusinessLogic\CarrierService $carrierService */
        $carrierService = ServiceRegister::getService(ShopShippingMethodService::CLASS_NAME);

        return $carrierService->getCarrierLogoById($shippingMethodId);
    }
}
