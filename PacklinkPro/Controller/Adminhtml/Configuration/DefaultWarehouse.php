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
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Controllers\LocationsController;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Controllers\WarehouseController;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\DTO\Exceptions\FrontDtoValidationException;

/**
 * Class DefaultWarehouse
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Configuration
 */
class DefaultWarehouse extends Configuration
{
    /**
     * @var WarehouseController
     */
    private $baseController;
    /**
     * @var LocationsController
     */
    private $locationsController;
    /**
     * @var Session
     */
    private $authSession;

    /**
     * DefaultWarehouse constructor.
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
            'getDefaultWarehouse',
            'setDefaultWarehouse',
            'getSupportedCountries',
            'searchPostalCodes',
        ];

        $this->authSession = $session;
        $this->baseController = new WarehouseController();
        $this->locationsController = new LocationsController();
    }

    /**
     * Returns default warehouse data.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function getDefaultWarehouse()
    {
        $warehouse = $this->baseController->getWarehouse();

        return $this->result->setData($warehouse ? $warehouse->toArray() : []);
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

        try {
            $warehouse = $this->baseController->updateWarehouse($data);
        } catch (FrontDtoValidationException $e) {
            return $this->formatValidationErrorResponse($e->getValidationErrors());
        }

        return $this->result->setData($warehouse->toArray());
    }

    /**
     * Returns Packlink supported warehouse countries.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function getSupportedCountries()
    {
        $user = $this->authSession->getUser();

        if ($user) {
            ConfigService::setCurrentLanguage(substr($user->getInterfaceLocale(), 0, 2));
        }

        $countries = $this->baseController->getWarehouseCountries();

        return $this->formatDtoEntitiesResponse($countries);
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

        try {
            $locations = $this->locationsController->searchLocations($input);
        } catch (\Exception $e) {
            return $this->result;
        }

        return $this->formatDtoEntitiesResponse($locations);
    }
}
