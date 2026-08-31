<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Test\Unit;

use Magento\Framework\App\ObjectManager;
use Magento\Setup\Module\Setup;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\RepositoryRegistry;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\QueueItem;
use Packlink\PacklinkPro\IntegrationCore\Tests\Infrastructure\ORM\AbstractGenericQueueItemRepositoryTest;
use Packlink\PacklinkPro\Setup\DatabaseHandler;
use Packlink\PacklinkPro\Test\Unit\Repository\TestQueueItemRepository;

/**
 * Class MagentoGenericQueueItemRepositoryTest
 *
 * @package Packlink\PacklinkPro\Test\Unit
 */
class MagentoGenericQueueItemRepositoryTest extends AbstractGenericQueueItemRepositoryTest
{
    /**
     * @inheritdoc
     */
    public static function tearDownAfterClass(): void
    {
        $setup = ObjectManager::getInstance()->create(Setup::class);
        $installer = $setup->startSetup();

        $databaseHandler = new TestDatabaseHandler($installer);
        $databaseHandler->dropEntityTable(TestQueueItemRepository::TABLE_NAME);

        $installer->endSetup();
    }

    /**
     * @return string
     */
    public function getQueueItemEntityRepositoryClass()
    {
        return TestQueueItemRepository::getClassName();
    }

    /**
     * @inheritdoc
     *
     * @throws \Zend_Db_Exception
     */
    public function setUp(): void
    {
        parent::setUp();
        $setup = ObjectManager::getInstance()->create(Setup::class);
        /** @var \Packlink\PacklinkPro\Bootstrap $bootstrap */
        $bootstrap = ObjectManager::getInstance()->create(Bootstrap::class);
        $bootstrap->initInstance();
        $installer = $setup->startSetup();

        $databaseHandler = new TestDatabaseHandler($installer);
        $databaseHandler->createEntityTable(TestQueueItemRepository::TABLE_NAME);

        $installer->endSetup();

        RepositoryRegistry::registerRepository(QueueItem::CLASS_NAME, TestQueueItemRepository::getClassName());
    }

    /**
     * Cleans up all storage services used by repositories
     */
    public function cleanUpStorage()
    {
        // Was a no-op stub, so rows accumulated across every test in this class and the size
        // assertions failed with growing multiples (150 vs 50, 76 vs 19, ...). The abstract base
        // calls this between tests; setUp() recreates the table afterwards.
        /** @var Setup $setup */
        $setup = ObjectManager::getInstance()->create(Setup::class);
        $installer = $setup->startSetup();

        $databaseHandler = new TestDatabaseHandler($installer);
        $databaseHandler->dropEntityTable(TestQueueItemRepository::TABLE_NAME);

        $installer->endSetup();
    }
}
