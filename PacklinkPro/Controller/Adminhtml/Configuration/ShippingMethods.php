<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2021 Packlink
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
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\DTO\Exceptions\FrontDtoValidationException;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Exceptions\BaseException;
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
    private $baseController;

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
        $this->baseController = new ShippingMethodController();

        $this->allowedActions = [
            'getAll',
            'getActive',
            'getInactive',
            'getShippingMethod',
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
        $shippingMethods = $this->baseController->getAll();

        return $this->formatDtoEntitiesResponse($shippingMethods);
    }

    /**
     * Returns active shipping methods.
     */
    protected function getActive()
    {
        $shippingMethods = $this->baseController->getActive();

        return $this->formatDtoEntitiesResponse($shippingMethods);
    }

    /**
     * Returns inactive shipping methods.
     */
    protected function getInactive()
    {
        $shippingMethods = $this->baseController->getInactive();

        return $this->formatDtoEntitiesResponse($shippingMethods);
    }

    /**
     * Returns a single shipping method identified by the provided ID.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function getShippingMethod()
    {
        $request = $this->getRequest();

        if (!$request->getParam('id')) {
            return $this->formatNotFoundResponse();
        }

        $shippingMethod = $this->baseController->getShippingMethod($request->getParam('id'));
        if ($shippingMethod === null) {
            return $this->formatNotFoundResponse();
        }

        return $this->result->setData($shippingMethod->toArray());
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

        if (!$data['id'] || !$this->baseController->activate((int)$data['id'])) {
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

        if (!$data['id'] || !$this->baseController->deactivate((int)$data['id'])) {
            $this->result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);

            return $this->result->setData(['message' => __('Failed to deselect shipping method.')]);
        }

        return $this->result->setData(['message' => __('Shipping method successfully deselected.')]);
    }

    /**
     * Saves shipping method.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function save()
    {
        try {
            $configuration = $this->getShippingMethodConfiguration();
        } catch (FrontDtoValidationException $e) {
            return $this->formatValidationErrorResponse($e->getValidationErrors());
        }

        /** @var ShippingMethodResponse $model */
        $model = $this->baseController->save($configuration);
        if ($model === null) {
            $this->result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);

            return $this->result->setData(['message' => __('Failed to save shipping method.')]);
        }

        if ($model->activated) {
            return $this->result->setData($model->toArray());
        }

        if (!$model->id || !$this->baseController->activate((int)$model->id)) {
            $this->result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);

            return $this->result->setData(['message' => __('Failed to activate shipping method.')]);
        }

        return $this->result->setData($model->toArray());
    }

    /**
     * Returns shipping configuration.
     *
     * @return ShippingMethodConfiguration
     *
     * @throws \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\DTO\Exceptions\FrontDtoValidationException
     */
    private function getShippingMethodConfiguration()
    {
        $data = $this->getPacklinkPostData();

        $data['taxClass'] = (int)$data['taxClass'];

        return ShippingMethodConfiguration::fromArray($data);
    }
}
