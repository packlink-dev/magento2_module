<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory;
use Packlink\PacklinkPro\Bootstrap;

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
            'getSystemOrderStatuses',
            'getMappings',
            'setMappings',
        ];

        $this->statusCollectionFactory = $statusCollectionFactory;
    }

    /**
     * Returns order state mappings.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function getMappings()
    {
        $mappings = $this->getConfigService()->getOrderStatusMappings();

        return $this->result->setData($mappings ?: []);
    }

    /**
     * Sets order state mappings.
     *
     * * @return \Magento\Framework\Controller\Result\Json
     */
    protected function setMappings()
    {
        $data = $this->getPacklinkPostData();
        $this->getConfigService()->setOrderStatusMappings($data);

        return $this->result;
    }

    /**
     * Returns all order statuses that exist in Magento.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function getSystemOrderStatuses()
    {
        return $this->result->setData($this->getAvailableStatuses());
    }

    /**
     * Retrieves list of available order statuses in following format:
     *
     * [
     *      [
     *          'code' => 1,
     *          'label' => Shipped,
     *      ],
     *
     *      ...
     * ]
     *
     * @return array
     */
    private function getAvailableStatuses()
    {
        $result = [];
        $orderStates = $this->statusCollectionFactory->create()->toOptionArray();

        foreach ($orderStates as $orderState) {
            $result[] = ['code' => $orderState['value'], 'label' => $orderState['label']];
        }

        return $result;
    }
}
