<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Ui\Component\Listing\Column;

use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\Helper\UrlHelper;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\OrderService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\OrderShipmentDetails\OrderShipmentDetailsService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;

/**
 * Class ShipmentLabel
 *
 * @package Packlink\PacklinkPro\Ui\Component\Listing\Column
 */
class ShipmentLabel extends Column
{
    /**
     * @var UrlHelper
     */
    private $urlHelper;
    /**
     * @var FormKey
     */
    private $formKey;

    /**
     * ShipmentLabel constructor.
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param Bootstrap $bootstrap
     * @param UrlHelper $urlHelper
     * @param FormKey $formKey
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        Bootstrap $bootstrap,
        UrlHelper $urlHelper,
        FormKey $formKey,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);

        $this->urlHelper = $urlHelper;
        $this->formKey = $formKey;

        $bootstrap->initInstance();
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     *
     * @return array
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            /** @var OrderShipmentDetailsService $orderShipmentDetailsService */
            $orderShipmentDetailsService = ServiceRegister::getService(OrderShipmentDetailsService::CLASS_NAME);
            /** @var OrderService $orderService */
            $orderService = ServiceRegister::getService(OrderService::CLASS_NAME);

            $fieldName = $this->getData('name');
            foreach ($dataSource['data']['items'] as &$item) {
                $orderDetails = $orderShipmentDetailsService->getDetailsByOrderId($item['order_id']);
                if ($orderDetails === null
                    || !$orderService->isReadyToFetchShipmentLabels($orderDetails->getShippingStatus())
                ) {
                    continue;
                }

                $controllerUrl = $this->urlHelper->getBackendUrl(
                    'packlink/shipmentlabels/shipmentlabels',
                    [
                        'ajax' => 1,
                        'form_key' => $this->formKey->getFormKey(),
                    ]
                );

                $labels = $orderDetails->getShipmentLabels();
                $isPrinted = !empty($labels) && $labels[0]->isPrinted();

                $printText = ($isPrinted ? __('Printed') : __('Print'));
                $classList = (!$isPrinted ? 'primary ' : '') . 'pl-print-label-button';

                $element = '<button type="button" '
                    . 'data-order-id="' . $orderDetails->getOrderId() . '" '
                    . 'data-controller-url="' . $controllerUrl . '" '
                    . 'class="' . $classList . '" '
                    . 'onclick="plPrintShipmentLabel(this)"'
                    . '>'
                    . $printText
                    . '</button>'
                    . '<div class="pl-printed-label" hidden>' . __('Printed') . '</div>';

                $item[$fieldName] = $element;
            }
        }

        return $dataSource;
    }
}
