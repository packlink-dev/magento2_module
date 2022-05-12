<?php

namespace Packlink\PacklinkPro\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\OrderShipmentDetails\Models\OrderShipmentDetails;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Scheduler\Models\HourlySchedule;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Scheduler\Models\Schedule;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Scheduler\ScheduleCheckTask;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShipmentDraft\Exceptions\DraftTaskMapExists;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShipmentDraft\OrderSendDraftTaskMapService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Tasks\TaskCleanupTask;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Tasks\UpdateShippingServicesTask;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Configuration\Configuration;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\Logger;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\RepositoryRegistry;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\QueueItem;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\QueueService;
use Packlink\PacklinkPro\Services\BusinessLogic\ConfigurationService;
use Packlink\PacklinkPro\Setup\InstallSchema;
use Packlink\PacklinkPro\Setup\Patch\AbstractPatch;

/**
 * Class Version104
 *
 * @package Packlink\PacklinkPro\Setup\Patch\Data
 */
class Version110 extends AbstractPatch implements DataPatchInterface
{
    /**
     * @inheritDoc
     */
    public static function getVersion()
    {
        return '1.1.0';
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }

    public function apply()
    {
        try {
            Logger::logInfo('Started executing V1.1.0 update script.');

            $this->addTaskCleanupSchedule();
            $this->migrateShopOrderDetailsEntities($this->databaseHandler->getInstaller());
            $this->updateShippingServices();

            Logger::logInfo('Update script V1.1.0 has been successfully completed.');
        } catch (\Exception $e) {
            Logger::logError("V1.1.0 update script failed because: {$e->getMessage()}");
        }
    }

    /**
     * Enqueues task for cleaning up completed queue items.
     *
     * @throws RepositoryNotRegisteredException
     */
    protected function addTaskCleanupSchedule()
    {
        $repository = RepositoryRegistry::getRepository(Schedule::getClassName());

        $schedule = new HourlySchedule(
            new TaskCleanupTask(ScheduleCheckTask::getClassName(), [QueueItem::COMPLETED], 3600),
            $this->getConfigService()->getDefaultQueueName()
        );

        $schedule->setMinute(10);
        $schedule->setNextSchedule();
        $repository->save($schedule);
    }

    /**
     * Migrates all old shop order details entities from Magento integration to Core order shipment details entities.
     *
     * @param ModuleDataSetupInterface $installer
     *
     * @throws DraftTaskMapExists
     * @throws RepositoryNotRegisteredException
     */
    protected function migrateShopOrderDetailsEntities(SchemaSetupInterface $installer)
    {
        $connection = $installer->getConnection();

        if ($connection->isTableExists(InstallSchema::PACKLINK_ENTITY_TABLE)) {
            $select = $connection->select()
                ->from(InstallSchema::PACKLINK_ENTITY_TABLE)
                ->where('type = ?', 'ShopOrderDetails');

            $entities = $connection->fetchAll($select);
            if (!empty($entities)) {
                $orderShipmentDetailsRepository = RepositoryRegistry::getRepository(
                    OrderShipmentDetails::getClassName()
                );
                /** @var OrderSendDraftTaskMapService $orderSendDraftTaskMapService */
                $orderSendDraftTaskMapService = ServiceRegister::getService(OrderSendDraftTaskMapService::CLASS_NAME);

                $userInfo = $this->getConfigService()->getUserInfo();
                $userDomain = 'com';
                if ($userInfo !== null && in_array($userInfo->country, ['ES', 'DE', 'FR', 'IT'])) {
                    $userDomain = strtolower($userInfo->country);
                }

                $baseShipmentUrl = "https://pro.packlink.$userDomain/private/shipments/";

                foreach ($entities as $entity) {
                    $data = json_decode($entity['data'], true);
                    $orderShipmentDetails = OrderShipmentDetails::fromArray($data);
                    $orderShipmentDetails->setReference($data['shipmentReference']);
                    $orderShipmentDetails->setOrderId((string)$data['orderId']);
                    $orderShipmentDetails->setShippingCost($data['packlinkShippingPrice']);
                    $orderShipmentDetails->setShipmentUrl($baseShipmentUrl . $orderShipmentDetails->getReference());
                    $orderShipmentDetailsRepository->save($orderShipmentDetails);
                    $orderSendDraftTaskMapService->createOrderTaskMap((string)$data['orderId'], $data['taskId']);
                }

                $connection->delete(InstallSchema::PACKLINK_ENTITY_TABLE, ['type = ?' => 'ShopOrderDetails']);
            }
        }
    }

    /**
     * Updates Packlink shipping services.
     *
     * @throws QueueStorageUnavailableException
     */
    protected function updateShippingServices()
    {
        /** @var QueueService $queueService */
        $queueService = ServiceRegister::getService(QueueService::CLASS_NAME);

        if ($queueService->findLatestByType('UpdateShippingServicesTask') !== null) {
            $queueService->enqueue($this->getConfigService()->getDefaultQueueName(), new UpdateShippingServicesTask());
        }
    }

    /**
     * Gets the instance of the configuration service.
     *
     * @return ConfigurationService
     */
    protected function getConfigService()
    {
        /** @var ConfigurationService $configuration */
        $configuration = ServiceRegister::getService(Configuration::CLASS_NAME);

        return $configuration;
    }
}
