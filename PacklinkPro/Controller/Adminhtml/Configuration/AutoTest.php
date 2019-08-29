<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\AutoTest\AutoTestLogger;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\AutoTest\AutoTestService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Exceptions\StorageNotAccessibleException;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryClassException;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException;
use Packlink\PacklinkPro\Services\Infrastructure\LoggerService;

/**
 * Class AutoTest.
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Configuration
 */
class AutoTest extends Configuration
{
    /**
     * @var \Magento\Framework\App\Response\Http\FileFactory
     */
    private $fileFactory;

    /**
     * AutoConfigure constructor.
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Packlink\PacklinkPro\Bootstrap $bootstrap
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param \Magento\Framework\App\Response\Http\FileFactory $fileFactory
     */
    public function __construct(
        Context $context,
        Bootstrap $bootstrap,
        JsonFactory $jsonFactory,
        FileFactory $fileFactory
    ) {
        parent::__construct($context, $bootstrap, $jsonFactory);

        $this->allowedActions = ['start', 'checkStatus', 'exportLogs'];
        $this->fileFactory = $fileFactory;
    }

    /**
     * Returns current setup status.
     *
     * @return \Magento\Framework\Controller\Result\Json
     *
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     */
    protected function start()
    {
        $service = new AutoTestService();
        try {
            return $this->result->setData(
                [
                    'success' => true,
                    'itemId' => $service->startAutoTest(),
                ]
            );
        } catch (StorageNotAccessibleException $e) {
            return $this->result->setData(
                [
                    'success' => false,
                    'error' => __('Database not accessible.'),
                ]
            );
        }
    }

    /**
     * Checks the status of the auto-test task.
     *
     * @throws QueryFilterInvalidParamException When queue filter is wrong.
     * @throws RepositoryClassException When repository class is not available.
     * @throws RepositoryNotRegisteredException When repository is not registered in bootstrap.
     */
    protected function checkStatus()
    {
        $service = new AutoTestService();
        $status = $service->getAutoTestTaskStatus($this->getRequest()->getParam('queueItemId'));

        if ($status->finished) {
            $service->stopAutoTestMode(
                static function () {
                    return LoggerService::getInstance();
                }
            );
        }

        return $this->result->setData(
            [
                'finished' => $status->finished,
                'error' => __($status->error, 'packlink-pro-shipping'),
                'logs' => AutoTestLogger::getInstance()->getLogsArray(),
            ]
        );
    }

    /**
     * Exports all logs as a JSON file.
     *
     * @throws RepositoryNotRegisteredException When repository is not registered in bootstrap.
     * @throws \Exception
     */
    protected function exportLogs()
    {
        $data = json_encode(AutoTestLogger::getInstance()->getLogsArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return $this->fileFactory->create('auto-test-logs.json', $data, DirectoryList::VAR_DIR);
    }
}
