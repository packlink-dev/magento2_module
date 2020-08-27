<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2020 Packlink
 */

namespace Packlink\PacklinkPro\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Sales\Model\Order;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\Helper\LocalizationHelper;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Configuration;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Utility\ShipmentStatus;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\Logger;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\Exceptions\TaskRunnerStatusStorageUnavailableException;
use Packlink\PacklinkPro\Services\BusinessLogic\ConfigurationService;

/**
 * Class InstallSchema
 *
 * @package Packlink\PacklinkPro\Setup
 */
class InstallSchema implements InstallSchemaInterface
{
    const PACKLINK_ENTITY_TABLE = 'packlink_entity';
    /**
     * @var LocalizationHelper
     */
    private $localizationHelper;

    /**
     * InstallSchema constructor.
     *
     * @param Bootstrap $bootstrap Bootstrap component.
     * @param LocalizationHelper $localizationHelper
     */
    public function __construct(Bootstrap $bootstrap, LocalizationHelper $localizationHelper)
    {
        $this->localizationHelper = $localizationHelper;
        $bootstrap->initInstance();
    }

    /**
     * Installs DB schema for a module
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     *
     * @return void
     *
     * @throws \Zend_Db_Exception
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup->startSetup();

        $databaseHandler = new DatabaseHandler($installer);
        $databaseHandler->dropEntityTable(self::PACKLINK_ENTITY_TABLE);
        $databaseHandler->createEntityTable(self::PACKLINK_ENTITY_TABLE);
        $this->initializePlugin();
        $this->localizationHelper->copyTranslations();

        $installer->endSetup();
    }

    /**
     * Initializes entity table.
     */
    private function initializePlugin()
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
