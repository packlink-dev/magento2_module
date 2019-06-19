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
use Magento\Tax\Model\TaxClass\Source\Product as ProductTaxClassSource;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\Helper\CarrierLogoHelper;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Controllers\DTO\ShippingMethodConfiguration;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Controllers\DTO\ShippingMethodResponse;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Controllers\ShippingMethodController;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Http\DTO\BaseDto;

/**
 * Class ShippingMethods
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Configuration
 */
class ShippingMethods extends Configuration
{
    /**
     * @var ProductTaxClassSource
     */
    private $productTaxClassSource;
    /**
     * @var CarrierLogoHelper
     */
    private $carrierLogoHelper;
    /**
     * @var ShippingMethodController
     */
    private $controller;

    /**
     * ShippingMethods constructor.
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Packlink\PacklinkPro\Bootstrap $bootstrap
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param \Magento\Tax\Model\TaxClass\Source\Product $productTaxClassSource
     * @param \Packlink\PacklinkPro\Helper\CarrierLogoHelper $logoHelper
     */
    public function __construct(
        Context $context,
        Bootstrap $bootstrap,
        JsonFactory $jsonFactory,
        ProductTaxClassSource $productTaxClassSource,
        CarrierLogoHelper $logoHelper
    ) {
        parent::__construct($context, $bootstrap, $jsonFactory);

        $this->productTaxClassSource = $productTaxClassSource;
        $this->carrierLogoHelper = $logoHelper;

        $this->allowedActions = [
            'getAll',
            'activate',
            'deactivate',
            'save',
        ];
    }

    /**
     * Returns all available shipping methods.
     */
    protected function getAll()
    {
        $shippingMethods = $this->getShippingMethodController()->getAll();

        return $this->result->setData($this->formatCollectionJsonResponse($shippingMethods));
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

        $model->logoUrl = $model->id ? $this->carrierLogoHelper->getCarrierLogoFilePath($model->id) : '';

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
     * Transforms shipping method collection to JSON response.
     *
     * @param BaseDto[] $data DTO collection.
     *
     * @return array Transformed JSON response.
     */
    private function formatCollectionJsonResponse($data)
    {
        $collection = [];

        /** @var ShippingMethodResponse $shippingMethod */
        foreach ($data as $shippingMethod) {
            $shippingMethod->logoUrl = $this->carrierLogoHelper->getCarrierLogoFilePath($shippingMethod->id);
            $collection[] = $shippingMethod->toArray();
        }

        return $collection;
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
}
