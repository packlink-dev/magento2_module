<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2022 Packlink
 */

namespace Packlink\PacklinkPro\Block\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Quote\Model\Quote\Address;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Http\DTO\ParcelInfo;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\ShippingMethodService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\Services\BusinessLogic\CarrierService;
use Packlink\PacklinkPro\Services\BusinessLogic\ConfigurationService;

class Config extends Template
{
    /**
     * @var Session
     */
    private $checkoutSession;
    /**
     * @var \Magento\Framework\Locale\Resolver
     */
    private $locale;
    /**
     * @var CarrierService
     */
    private $carrierService;

    /**
     * @param Context $context
     * @param Session $session
     * @param Resolver $locale
     * @param Bootstrap $bootstrap
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Session $session,
        Resolver $locale,
        Bootstrap $bootstrap,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->checkoutSession = $session;
        $this->locale = $locale;

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

        /** @var ShippingMethodService $shippingMethodService */
        $shippingMethodService = ServiceRegister::getService(ShippingMethodService::CLASS_NAME);
        $shippingMethods = $shippingMethodService->getAllMethods();

        foreach ($shippingMethods as $shippingMethod) {
            if ($shippingMethod->isDisplayLogo()) {
                $result[$shippingMethod->getId()] = $shippingMethod->getLogoUrl();
            }
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
        return $this->getCarrierService()->getCarrierLogoFilePath('');
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
     * Csp nonce is necessary for Magento version 2.1.7 and higher.
     * Magento\Csp\Helper\CspNonceProvider class does not exist in earlier versions.
     *
     * @return string
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getCspNonce()
    {
        $objectManager = ObjectManager::getInstance();
        /** @var ProductMetadataInterface $productMetadata */
        $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');

        if (version_compare($productMetadata->getVersion(), '2.1.7', '>=')) {
            /** @var \Magento\Csp\Helper\CspNonceProvider $cspNonceProvider */
            $cspNonceProvider = $objectManager->get('Magento\Csp\Helper\CspNonceProvider');

            return $cspNonceProvider->generateNonce();
        }

        return '';
    }

    /**
     * @return ShopShippingMethodService
     */
    private function getCarrierService()
    {
        if ($this->carrierService === null) {
            $this->carrierService = ServiceRegister::getService(ShopShippingMethodService::CLASS_NAME);
        }

        return $this->carrierService;
    }
}
