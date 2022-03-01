<?php

namespace Packlink\PacklinkPro\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Sales\Model\Order;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Utility\ShipmentStatus;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Configuration\Configuration;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\Logger;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\Exceptions\TaskRunnerStatusStorageUnavailableException;
use Packlink\PacklinkPro\Services\BusinessLogic\ConfigurationService;

/**
 * Class Initializer
 *
 * @package Packlink\PacklinkPro\Setup\Patch\Data
 */
class Initializer implements DataPatchInterface
{
    /**
     * @param Bootstrap $bootstrap
     */
    public function __construct(Bootstrap $bootstrap)
    {
        $bootstrap::init();
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
            /** @var ConfigurationService $configService */
            $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
            $configService->setTaskRunnerStatus('', null);
            $mappings = $configService->getOrderStatusMappings();
            if (empty($mappings)) {
                $configService->setOrderStatusMappings(
                    [
                        ShipmentStatus::STATUS_PENDING => '',
                        ShipmentStatus::STATUS_ACCEPTED => Order::STATE_PROCESSING,
                        ShipmentStatus::STATUS_READY => Order::STATE_PROCESSING,
                        ShipmentStatus::STATUS_IN_TRANSIT => Order::STATE_PROCESSING,
                        ShipmentStatus::STATUS_DELIVERED => Order::STATE_COMPLETE,
                        ShipmentStatus::STATUS_CANCELLED => Order::STATE_CANCELED,
                    ]
                );
            }
        } catch (TaskRunnerStatusStorageUnavailableException $e) {
            Logger::logError(__('Error creating default task runner status configuration.'), 'Integration');
        }
    }
}
