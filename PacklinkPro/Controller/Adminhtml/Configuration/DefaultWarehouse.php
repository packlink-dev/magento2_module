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
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Country\WarehouseCountryService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\DTO\Exceptions\FrontDtoValidationException;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Location\LocationService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Warehouse\WarehouseService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;

/**
 * Class DefaultWarehouse
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Configuration
 */
class DefaultWarehouse extends Configuration
{
    /**
     * DefaultWarehouse constructor.
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
            'getDefaultWarehouse',
            'setDefaultWarehouse',
            'getSupportedCountries',
            'searchPostalCodes',
        ];
    }

    /**
     * Returns default warehouse data.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function getDefaultWarehouse()
    {
        /** @var WarehouseService $warehouseService */
        $warehouseService = ServiceRegister::getService(WarehouseService::CLASS_NAME);

        $warehouse = $warehouseService->getWarehouse();

        return $this->result->setData($warehouse->toArray());
    }

    /**
     * Sets warehouse data.
     *
     * @return \Magento\Framework\Controller\Result\Json
     *
     * @throws \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\DTO\Exceptions\FrontDtoNotRegisteredException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     */
    protected function setDefaultWarehouse()
    {
        $data = $this->getPacklinkPostData();
        $data['default'] = true;

        /** @var WarehouseService $warehouseService */
        $warehouseService = ServiceRegister::getService(WarehouseService::CLASS_NAME);

        try {
            $warehouse = $warehouseService->updateWarehouseData($data);

            return $this->result->setData($warehouse->toArray());
        } catch (FrontDtoValidationException $e) {
            return $this->formatValidationErrorResponse($e->getValidationErrors());
        }
    }

    /**
     * Returns Packlink supported warehouse countries.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function getSupportedCountries()
    {
        /** @var WarehouseCountryService $countryService */
        $countryService = ServiceRegister::getService(WarehouseCountryService::CLASS_NAME);
        $supportedCountries = $countryService->getSupportedCountries();

        foreach ($supportedCountries as $country) {
            $country->name = __($country->name);
        }

        return $this->formatDtoEntitiesResponse($supportedCountries);
    }

    /**
     * Performs location search.
     *
     * @return array|\Magento\Framework\Controller\Result\Json
     */
    protected function searchPostalCodes()
    {
        $input = $this->getPacklinkPostData();

        if (empty($input['query']) || empty($input['country'])) {
            return $this->result;
        }

        /** @var LocationService $locationService */
        $locationService = ServiceRegister::getService(LocationService::CLASS_NAME);
        try {
            $locations = $locationService->searchLocations($input['country'], $input['query']);
        } catch (\Exception $e) {
            return $this->result;
        }

        return $this->formatDtoEntitiesResponse($locations);
    }
}
