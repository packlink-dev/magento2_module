<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2020 Packlink
 */

namespace Packlink\PacklinkPro\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Country\CountryService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\OrderShipmentDetails\Models\OrderShipmentDetails;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Scheduler\Models\DailySchedule;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Scheduler\Models\HourlySchedule;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Scheduler\Models\Schedule;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Scheduler\Models\WeeklySchedule;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Scheduler\ScheduleCheckTask;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShipmentDraft\OrderSendDraftTaskMapService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Utility\ShipmentStatus;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Tasks\TaskCleanupTask;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Tasks\UpdateShipmentDataTask;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Tasks\UpdateShippingServicesTask;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Configuration\Configuration;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\Logger;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\RepositoryRegistry;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\QueueItem;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\QueueService;

class UpgradeSchema implements UpgradeSchemaInterface
{
    protected $bootstrap;

    /**
     * UpgradeSchema constructor.
     *
     * @param Bootstrap $bootstrap
     */
    public function __construct(Bootstrap $bootstrap)
    {
        $this->bootstrap = $bootstrap;
    }

    /**
     * Upgrades DB schema for a module
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     *
     * @return void
     *
     * @throws \Exception
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        if (empty($context->getVersion())) {
            return;
        }

        Bootstrap::init();

        if (version_compare($context->getVersion(), '1.0.1', '<')) {
            $this->upgradeTo101();
        }

        if (version_compare($context->getVersion(), '1.1.0', '<')) {
            $this->upgradeTo110($setup);
        }

        if (version_compare($context->getVersion(), '1.1.4', '<')) {
            $this->upgradeTo114($setup);
        }
    }

    /**
     * Runs the upgrade script for v1.0.1.
     * Changes shipment data update interval.
     *
     * @throws \Exception
     */
    protected function upgradeTo101()
    {
        try {
            Logger::logInfo('Started executing V1.0.1 update script.');

            $configuration = $this->getConfigService();
            $repository = RepositoryRegistry::getRepository(Schedule::getClassName());

            $schedules = $repository->select();

            /** @var Schedule $schedule */
            foreach ($schedules as $schedule) {
                $repository->delete($schedule);
            }

            foreach ([0, 30] as $minute) {
                $hourlyStatuses = [
                    ShipmentStatus::STATUS_PENDING,
                ];

                $shipmentDataHalfHourSchedule = new HourlySchedule(
                    new UpdateShipmentDataTask($hourlyStatuses),
                    $configuration->getDefaultQueueName()
                );
                $shipmentDataHalfHourSchedule->setMinute($minute);
                $shipmentDataHalfHourSchedule->setNextSchedule();
                $repository->save($shipmentDataHalfHourSchedule);
            }

            $dailyStatuses = [
                ShipmentStatus::STATUS_IN_TRANSIT,
                ShipmentStatus::STATUS_READY,
                ShipmentStatus::STATUS_ACCEPTED,
            ];

            $dailyShipmentDataSchedule = new DailySchedule(
                new UpdateShipmentDataTask($dailyStatuses),
                $configuration->getDefaultQueueName()
            );

            $dailyShipmentDataSchedule->setHour(11);
            $dailyShipmentDataSchedule->setNextSchedule();

            $repository->save($dailyShipmentDataSchedule);

            // Schedule weekly task for updating services
            $shippingServicesSchedule = new WeeklySchedule(
                new UpdateShippingServicesTask(),
                $configuration->getDefaultQueueName()
            );
            $shippingServicesSchedule->setDay(1);
            $shippingServicesSchedule->setHour(2);
            $shippingServicesSchedule->setNextSchedule();
            $repository->save($shippingServicesSchedule);

            Logger::logInfo('Update script V1.0.1 has been successfully completed.');
        } catch (\Exception $e) {
            Logger::logError("V1.0.1 update script failed because: {$e->getMessage()}");

            throw $e;
        }
    }

    /**
     * Runs the upgrade script for v1.1.0.
     *
     * @param SchemaSetupInterface $setup
     *
     * @throws \Exception
     */
    protected function upgradeTo110(SchemaSetupInterface $setup)
    {
        try {
            Logger::logInfo('Started executing V1.1.0 update script.');

            $this->addTaskCleanupSchedule();
            $this->migrateShopOrderDetailsEntities($setup);
            $this->updateShippingServices();

            Logger::logInfo('Update script V1.1.0 has been successfully completed.');
        } catch (\Exception $e) {
            Logger::logError("V1.1.0 update script failed because: {$e->getMessage()}");

            throw $e;
        }
    }

    /**
     * Runs the upgrade script for v1.1.4.
     *
     * @param SchemaSetupInterface $setup
     *
     * @throws \Exception
     */
    protected function upgradeTo114(SchemaSetupInterface $setup)
    {
        Logger::logInfo('Started executing V1.1.4 update script.');

        $installer = $setup->startSetup();

        $databaseHandler = new DatabaseHandler($installer);

        $databaseHandler->addAdditionalIndex();

        Logger::logInfo('Update script V1.1.4 has been successfully completed.');
    }

    /**
     * Enqueues task for cleaning up completed queue items.
     *
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
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
     * @param \Magento\Framework\Setup\SchemaSetupInterface $installer
     *
     * @throws \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShipmentDraft\Exceptions\DraftTaskMapExists
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
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

                /** @var CountryService $countryService */
                $countryService = ServiceRegister::getService(CountryService::CLASS_NAME);

                $userInfo = $this->getConfigService()->getUserInfo();
                $userDomain = 'com';
                if ($userInfo !== null && $countryService->isBaseCountry($userInfo->country)) {
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
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
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
     * @return \Packlink\PacklinkPro\Services\BusinessLogic\ConfigurationService
     */
    protected function getConfigService()
    {
        /** @var \Packlink\PacklinkPro\Services\BusinessLogic\ConfigurationService $configuration */
        $configuration = ServiceRegister::getService(Configuration::CLASS_NAME);

        return $configuration;
    }
}
