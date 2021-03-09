<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2021 Packlink
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
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Models\ShippingMethod;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Models\ShippingPricePolicy;
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
use \Packlink\PacklinkPro\Services\BusinessLogic\CarrierService;

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

        if (version_compare($context->getVersion(), '1.1.9', '<')) {
            $this->upgradeTo119($setup);
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
     * Runs the upgrade script for v1.1.9.
     *
     * @param SchemaSetupInterface $setup
     *
     * @throws \Exception
     */
    protected function upgradeTo119(SchemaSetupInterface $setup)
    {
        Logger::logInfo('Started executing V1.1.9 update script.');

        $this->updateShippingMethods($setup);

        $this->convertParcelProperties($setup);

        Logger::logInfo('Update script V1.1.9 has been successfully completed.');
    }

    /**
     * Updates shipping methods.
     *
     * @param SchemaSetupInterface $setup
     *
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    protected function updateShippingMethods(SchemaSetupInterface $setup)
    {
        $repository = RepositoryRegistry::getRepository(ShippingMethod::getClassName());
        /** @var CarrierService $carrierService */
        $carrierService = ServiceRegister::getService(ShopShippingMethodService::CLASS_NAME);

        $connection = $setup->getConnection();

        $select = $connection->select()
            ->from(InstallSchema::PACKLINK_ENTITY_TABLE)
            ->where('type = ?', 'ShippingService');

        $entities = $connection->fetchAll($select);

        foreach ($entities as $entity) {
            $data = json_decode($entity['data'], true);
            $data['pricingPolicies'] = $this->getTransformedPricingPolicies($data);
            $data['logoUrl'] = $this->getLogoUrl($data);

            $shippingMethod = ShippingMethod::fromArray($data);
            $repository->update($shippingMethod);

            if ($shippingMethod->isActivated()) {
                $carrierService->update($shippingMethod);
            }
        }
    }

    /**
     * Converts parcel properties from strings to numbers.
     *
     * @param SchemaSetupInterface $setup
     */
    protected function convertParcelProperties(SchemaSetupInterface $setup)
    {
        $connection = $setup->getConnection();

        $select = $connection->select()
            ->from(InstallSchema::PACKLINK_ENTITY_TABLE)
            ->where('index_1 = ?', 'defaultParcel');

        $entities = $connection->fetchAll($select);

        foreach ($entities as $entity) {
            if (empty($entity['data'])) {
                continue;
            }

            $parcel = json_decode($entity['data'], true);

            if (!empty($parcel['value']['weight'])) {
                $weight = (float)$parcel['value']['weight'];
                $parcel['value']['weight'] = !empty($weight) ? $weight : 1;
            }

            foreach (['length', 'height', 'width'] as $field) {
                if (!empty($parcel['value'][$field])) {
                    $fieldValue = (int)$parcel['value'][$field];
                    $parcel['value'][$field] = !empty($fieldValue) ? $fieldValue : 10;
                }
            }

            if (!empty($entity['id'])) {
                $connection->update(InstallSchema::PACKLINK_ENTITY_TABLE, ['data' => json_encode($parcel)], ['id =? ' => $entity['id']]);
            }
        }
    }

    /**
     * Returns transformed pricing policies for a given shipping method.
     *
     * @param array $method
     *
     * @return array
     */
    protected function getTransformedPricingPolicies(array $method)
    {
        $result = [];

        if (empty($method['pricingPolicy'])) {
            return $result;
        }

        switch ($method['pricingPolicy']) {
            case 1:
                // Packlink prices.
                break;
            case 2:
                // Percent prices.
                $pricingPolicy = new ShippingPricePolicy();
                $pricingPolicy->rangeType = ShippingPricePolicy::RANGE_PRICE_AND_WEIGHT;
                $pricingPolicy->fromPrice = 0;
                $pricingPolicy->fromWeight = 0;
                $pricingPolicy->pricingPolicy = ShippingPricePolicy::POLICY_PACKLINK_ADJUST;
                $pricingPolicy->increase = $method['percentPricePolicy']['increase'];
                $pricingPolicy->changePercent = $method['percentPricePolicy']['amount'];
                $result[] = $pricingPolicy->toArray();
                break;
            case 3:
                // Fixed price by weight.
                foreach ($method['fixedPriceByWeightPolicy'] as $policy) {
                    $pricingPolicy = new ShippingPricePolicy();
                    $pricingPolicy->rangeType = ShippingPricePolicy::RANGE_WEIGHT;
                    $pricingPolicy->fromWeight = $policy['from'];
                    $pricingPolicy->toWeight = !empty($policy['to']) ? $policy['to'] : null;
                    $pricingPolicy->pricingPolicy = ShippingPricePolicy::POLICY_FIXED_PRICE;
                    $pricingPolicy->fixedPrice = $policy['amount'];
                    $result[] = $pricingPolicy->toArray();
                }
                break;
            case 4:
                // Fixed price by price.
                foreach ($method['fixedPriceByValuePolicy'] as $policy) {
                    $pricingPolicy = new ShippingPricePolicy();
                    $pricingPolicy->rangeType = ShippingPricePolicy::RANGE_PRICE;
                    $pricingPolicy->fromPrice = $policy['from'];
                    $pricingPolicy->toPrice = !empty($policy['to']) ? $policy['to'] : null;
                    $pricingPolicy->pricingPolicy = ShippingPricePolicy::POLICY_FIXED_PRICE;
                    $pricingPolicy->fixedPrice = $policy['amount'];
                    $result[] = $pricingPolicy->toArray();
                }
                break;
        }

        return $result;
    }

    /**
     * Returns updated carrier logo file path for the given shipping method.
     *
     * @param array $method
     *
     * @return string
     */
    protected function getLogoUrl($method)
    {
        if (strpos($method['logoUrl'], '/images/carriers/') === false) {
            return  $method['logoUrl'];
        }

        return str_replace('/images/carriers/', '/packlink/images/carriers/', $method['logoUrl']);
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
