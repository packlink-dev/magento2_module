<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Webapi\Exception;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Controllers\DTO\ShippingMethodConfiguration;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Controllers\DTO\ShippingMethodResponse;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Controllers\ShippingMethodController;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Controllers\UpdateShippingServicesTaskStatusController;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Exceptions\BaseException;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\QueueItem;

/**
 * Class ShippingMethods
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Configuration
 */
class ShippingMethods extends Configuration
{
    /**
     * @var ShippingMethodController
     */
    private $controller;
    /**
     * @var \Packlink\PacklinkPro\Services\BusinessLogic\CarrierService
     */
    private $carrierService;

    /**
     * ShippingMethods constructor.
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
            'getAll',
            'activate',
            'deactivate',
            'save',
            'getTaskStatus',
        ];
    }

    /**
     * Returns all available shipping methods.
     */
    protected function getAll()
    {
        $shippingMethods = $this->getShippingMethodController()->getAll();

        return $this->formatDtoEntitiesResponse($shippingMethods);
    }

    /**
     * Gets the status of the task for updating shipping services.
     */
    protected function getTaskStatus()
    {
        $status = QueueItem::FAILED;
        try {
            $controller = new UpdateShippingServicesTaskStatusController();
            $status = $controller->getLastTaskStatus();
        } catch (BaseException $e) {
        }

        return $this->result->setData(['status' => $status]);
    }

    /**
     * Activates shipping method.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function activate()
    {
        $data = $this->getPacklinkPostData();

        if (!$data['id'] || !$this->getShippingMethodController()->activate((int)$data['id'])) {
            $this->result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);

            return $this->result->setData(['message' => __('Failed to select shipping method.')]);
        }

        return $this->result->setData(['message' => __('Shipping method successfully selected.')]);
    }

    /**
     * Deactivates shipping method.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function deactivate()
    {
        $data = $this->getPacklinkPostData();

        if (!$data['id'] || !$this->getShippingMethodController()->deactivate((int)$data['id'])) {
            $this->result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);

            return $this->result->setData(['message' => __('Failed to deselect shipping method.')]);
        }

        return $this->result->setData(['message' => __('Shipping method successfully deselected.')]);
    }

    protected function save()
    {
        $configuration = $this->getShippingMethodConfiguration();

        /** @var ShippingMethodResponse $model */
        $model = $this->getShippingMethodController()->save($configuration);
        if ($model === null) {
            $this->result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);

            return $this->result->setData(['message' => __('Failed to save shipping method.')]);
        }

        $model->logoUrl = $model->id ? $this->getCarrierService()->getCarrierLogoById($model->id) : '';

        if ($model->selected) {
            return $this->result->setData($model->toArray());
        }

        if (!$model->id || !$this->getShippingMethodController()->activate((int)$model->id)) {
            $this->result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);

            return $this->result->setData(['message' => __('Failed to activate shipping method.')]);
        }

        $model->selected = true;

        return $this->result->setData($model->toArray());
    }

    /**
     * Returns shipping configuration.
     *
     * @return ShippingMethodConfiguration
     */
    private function getShippingMethodConfiguration()
    {
        $data = $this->getPacklinkPostData();

        $data['taxClass'] = (int)$data['taxClass'];

        return ShippingMethodConfiguration::fromArray($data);
    }

    /**
     * Returns instance of shipping method controller.
     *
     * @return ShippingMethodController
     */
    private function getShippingMethodController()
    {
        if ($this->controller === null) {
            $this->controller = new ShippingMethodController();
        }

        return $this->controller;
    }

    /**
     * Returns an instance of carrier service.
     *
     * @return \Packlink\PacklinkPro\Services\BusinessLogic\CarrierService
     */
    private function getCarrierService()
    {
        if ($this->carrierService === null) {
            $this->carrierService = ServiceRegister::getService(ShopShippingMethodService::CLASS_NAME);
        }

        return $this->carrierService;
    }
}
