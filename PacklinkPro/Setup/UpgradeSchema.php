<?php

namespace Packlink\PacklinkPro\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Scheduler\Models\DailySchedule;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Scheduler\Models\HourlySchedule;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Scheduler\Models\Schedule;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Utility\ShipmentStatus;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Tasks\UpdateShipmentDataTask;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Configuration\Configuration;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\Logger;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\RepositoryRegistry;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;

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
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        Bootstrap::init();

        if (version_compare($context->getVersion(), '1.0.1', '<')) {
            $this->changeShipmentDataUpdateInterval();
        }
    }

    /**
     * Changes shipment data update interval.
     *
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    protected function changeShipmentDataUpdateInterval()
    {
        Logger::logInfo('Started executing V1.0.1 update script.');

        $configuration = ServiceRegister::getService(Configuration::CLASS_NAME);
        try {
            $repository = RepositoryRegistry::getRepository(Schedule::getClassName());
        } catch (RepositoryNotRegisteredException $e) {
            Logger::logError("V1.0.1 update script failed because: {$e->getMessage()}");

            throw $e;
        }

        $schedules = $repository->select();

        /** @var Schedule $schedule */
        foreach ($schedules as $schedule) {
            $task = $schedule->getTask();

            if ($task->getType() === UpdateShipmentDataTask::getClassName()) {
                $repository->delete($schedule);
            }
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

        Logger::logInfo('Update script V1.0.1 has been successfully completed.');
    }
}
