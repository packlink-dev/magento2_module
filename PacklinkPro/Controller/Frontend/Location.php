<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Frontend;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Webapi\Exception;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\Entity\QuoteCarrierDropOffMapping;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Http\DTO\Package;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Http\DTO\ParcelInfo;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Location\LocationService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\Logger;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\QueryFilter\QueryFilter;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\RepositoryRegistry;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\Repository\BaseRepository;
use Packlink\PacklinkPro\Services\BusinessLogic\ConfigurationService;

/**
 * Class Location
 *
 * @package Packlink\PacklinkPro\Controller\Webhook
 */
class Location extends Action
{
    private static $allowedActions = [
        'getLocations',
        'setDropOff',
    ];
    /**
     * @var Session
     */
    private $checkoutSession;
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;
    /**
     * @var BaseRepository
     */
    private $mappingRepository;

    public function __construct(Context $context, Session $session, JsonFactory $jsonFactory, Bootstrap $bootstrap)
    {
        parent::__construct($context);

        $this->checkoutSession = $session;
        $this->resultJsonFactory = $jsonFactory;

        $bootstrap->initInstance();
    }

    /**
     * Execute action based on request and return result.
     *
     * Note: Request will be added as operation argument in future
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function execute()
    {
        Logger::logDebug('Getting locations for service.', 'Integration', $this->getRequest()->getParams());

        $result = $this->resultJsonFactory->create();
        $action = $this->getRequest()->getParam('action');
        $methodId = $this->getRequest()->getParam('methodId');
        $addressId = $this->getRequest()->getParam('addressId');

        if (empty($methodId) || empty($action) || !in_array($action, self::$allowedActions, true)) {
            $result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);

            return $result->setData(
                [
                    'success' => false,
                    'message' => __('Invalid payload'),
                ]
            );
        }

        $resultData = ['success' => true];
        if ($action === 'getLocations') {
            $resultData['locations'] = $this->getLocations($methodId, $addressId);
        } else {
            $this->setDropOff($methodId, $addressId);
        }

        return $result->setData($resultData);
    }

    /**
     * Returns all available drop off locations for given shipping method.
     *
     * @param int $methodId ID of the shipping method.
     * @param int $addressId ID of the address.
     *
     * @return array Array of available locations.
     */
    private function getLocations($methodId, $addressId)
    {
        $country = $this->getRequest()->getParam('country');
        $zip = $this->getRequest()->getParam('zip');
        $weight = $this->getRequest()->getParam('totalWeight', 0);

        if (empty($country) || empty($zip)) {
            return [];
        }

        try {
            /** @var LocationService $locationService */
            $locationService = ServiceRegister::getService(LocationService::CLASS_NAME);

            $locations = $locationService->getLocations($methodId, $country, $zip, $this->getQuotePackages($weight));
            $mapping = $this->getMapping($methodId, $addressId);

            $dropOff = null;
            if ($mapping) {
                $dropOff = $mapping->getDropOff();
            }

            foreach ($locations as &$location) {
                $locationSelected = $dropOff && (int)$dropOff['id'] === (int)$location['id'];
                $location['selected'] = $locationSelected;
                $location['dropOffId'] = $locationSelected ? $dropOff['id'] : 0;
            }

            return $locations;
        } catch (\Exception $e) {
            Logger::logWarning('Error getting locations for service. Error: ' . $e->getMessage(), 'Integration');

            return [];
        }
    }

    /**
     * Sets shipping method drop-off information.
     *
     * @param int $methodId Shipping method ID.
     * @param int $addressId ID of the address.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Exception
     */
    private function setDropOff($methodId, $addressId)
    {
        $dropOff = json_decode($this->getRequest()->getParam('dropOff'), true);

        if (empty($dropOff)) {
            return;
        }

        $mapping = $this->getMapping($methodId, $addressId);

        if ($mapping === null) {
            $mapping = new QuoteCarrierDropOffMapping();

            $mapping->setCarrierId((int)$methodId);
            $mapping->setQuoteId((int)$this->checkoutSession->getQuoteId());
            $mapping->setDropOff($dropOff);

            if (!empty($addressId)) {
                $mapping->setAddressId((int)$addressId);
            }

            $this->getMappingRepository()->save($mapping);
        } else {
            $mapping->setDropOff($dropOff);
            $this->getMappingRepository()->update($mapping);
        }
    }

    /**
     * Returns quote carrier drop-off mapping if it exists.
     *
     * @param int $carrierId ID of the carrier.
     * @param int $addressId ID of the address.
     *
     * @return QuoteCarrierDropOffMapping
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    private function getMapping($carrierId, $addressId)
    {
        $query = new QueryFilter();
        $query->where('quoteId', '=', (int)$this->checkoutSession->getQuoteId())
            ->where('carrierId', '=', (int)$carrierId);

        if (!empty($addressId)) {
            $query->where('addressId', '=', (int)$addressId);
        }

        /** @var QuoteCarrierDropOffMapping $mapping */
        $mapping = $this->getMappingRepository()->selectOne($query);

        return $mapping ?: null;
    }

    /**
     * Gets packages out of cart products.
     *
     * @param array $quoteItemsWeight Quote items total weight.
     *
     * @return Package[]
     */
    private function getQuotePackages($quoteItemsWeight)
    {
        $configService = ServiceRegister::getService(ConfigurationService::CLASS_NAME);
        $parcel = $configService->getDefaultParcel() ?: ParcelInfo::defaultParcel();

        return [new Package($quoteItemsWeight ?: $parcel->weight, $parcel->width, $parcel->height, $parcel->length)];
    }

    /**
     * Returns mapping repository.
     *
     * @return BaseRepository
     *
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    private function getMappingRepository()
    {
        if ($this->mappingRepository === null) {
            $this->mappingRepository = RepositoryRegistry::getRepository(QuoteCarrierDropOffMapping::CLASS_NAME);
        }

        return $this->mappingRepository;
    }
}
