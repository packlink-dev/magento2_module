<?php

namespace Packlink\PacklinkPro\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Controllers\ManualRefreshController;

/**
 * Class ManualRefresh
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Configuration
 */
class ManualRefresh extends Configuration
{
    /**
     * @var \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Controllers\ManualRefreshServiceController
     */
    private $baseController;

    public function __construct(
        Context $context,
        Bootstrap $bootstrap,
        JsonFactory $jsonFactory
    ) {
        parent::__construct($context, $bootstrap, $jsonFactory);

        $this->baseController = new ManualRefreshController();
        $this->allowedActions = [
            'refresh',
            'getTaskStatus',
        ];
    }

    protected function refresh()
    {
        return $this->result->setData($this->baseController->enqueueUpdateTask()->toArray());
    }

    /**
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\Exceptions\QueueItemDeserializationException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryClassException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    protected function getTaskStatus()
    {
        return $this->result->setData($this->baseController->getTaskStatus()->toArray());
    }
}