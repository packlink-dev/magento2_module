<?php

namespace Packlink\PacklinkPro\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Scheduler\Models\DailySchedule;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Scheduler\Models\HourlySchedule;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Scheduler\Models\Schedule;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Scheduler\Models\WeeklySchedule;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Utility\ShipmentStatus;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Tasks\UpdateShipmentDataTask;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Tasks\UpdateShippingServicesTask;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Configuration\Configuration;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\Logger;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\RepositoryRegistry;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\Services\BusinessLogic\ConfigurationService;
use Packlink\PacklinkPro\Setup\Patch\AbstractPatch;

/**
 * Class Version101
 *
 * @package Packlink\PacklinkPro\Setup\Patch\Data
 */
class Version101 extends AbstractPatch implements DataPatchInterface
{
    /**
     * @inheritDoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * @inheritDoc
     *
     * @throws RepositoryNotRegisteredException
     */
    public function apply()
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
     * @inheritDoc
     */
    public static function getVersion()
    {
        return '1.0.1';
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
