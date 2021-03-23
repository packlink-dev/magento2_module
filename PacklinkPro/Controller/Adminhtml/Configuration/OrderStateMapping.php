<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2021 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Controllers\OrderStatusMappingController;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Language\Translator;

/**
 * Class OrderStateMapping
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Configuration
 */
class OrderStateMapping extends Configuration
{
    /**
     * @var CollectionFactory
     */
    private $statusCollectionFactory;
    /**
     * @var OrderStatusMappingController
     */
    private $baseController;

    /**
     * OrderStateMapping constructor.
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Packlink\PacklinkPro\Bootstrap $bootstrap
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $statusCollectionFactory
     */
    public function __construct(
        Context $context,
        Bootstrap $bootstrap,
        JsonFactory $jsonFactory,
        CollectionFactory $statusCollectionFactory
    ) {
        parent::__construct($context, $bootstrap, $jsonFactory);

        $this->allowedActions = [
            'getMappingsAndStatuses',
            'setMappings',
        ];

        $this->statusCollectionFactory = $statusCollectionFactory;
        $this->baseController = new OrderStatusMappingController();
    }

    /**
     * Returns order state mappings.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function getMappingsAndStatuses()
    {
        return $this->result->setData([
            'systemName' => $this->getConfigService()->getIntegrationName(),
            'mappings' => $this->baseController->getMappings(),
            'packlinkStatuses' => $this->baseController->getPacklinkStatuses(),
            'orderStatuses' => $this->getAvailableStatuses(),
        ]);
    }

    /**
     * Sets order state mappings.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function setMappings()
    {
        $data = $this->getPacklinkPostData();
        $this->baseController->setMappings($data);

        return $this->result->setData(['success' => true]);
    }

    /**
     * Retrieves the list of available order statuses.
     *
     * @return array
     */
    private function getAvailableStatuses()
    {
        $result = ['' => Translator::translate('orderStatusMapping.none')];
        $orderStates = $this->statusCollectionFactory->create()->toOptionArray();

        foreach ($orderStates as $orderState) {
            $result[$orderState['value']] = $orderState['label'];
        }

        return $result;
    }
}
