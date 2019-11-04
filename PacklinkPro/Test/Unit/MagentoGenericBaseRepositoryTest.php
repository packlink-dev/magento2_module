<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Test\Unit;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Setup\Module\Setup;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\RepositoryRegistry;
use Packlink\PacklinkPro\IntegrationCore\Tests\Infrastructure\Common\TestComponents\ORM\Entity\StudentEntity;
use Packlink\PacklinkPro\IntegrationCore\Tests\Infrastructure\ORM\AbstractGenericStudentRepositoryTest;
use Packlink\PacklinkPro\Setup\DatabaseHandler;
use Packlink\PacklinkPro\Test\Unit\Repository\TestRepository;

/**
 * Class MagentoGenericBaseRepositoryTest
 *
 * @package Packlink\PacklinkPro\Test\Unit
 */
class MagentoGenericBaseRepositoryTest extends AbstractGenericStudentRepositoryTest
{
    /**
     * @return string
     */
    public function getStudentEntityRepositoryClass()
    {
        return TestRepository::getClassName();
    }

    /**
     * @inheritdoc
     *
     * @throws \Zend_Db_Exception
     */
    public function setUp()
    {
        parent::setUp();

        /** @var Setup $setup */
        $setup = ObjectManager::getInstance()->create(Setup::class);
        /** @var \Packlink\PacklinkPro\Bootstrap $bootstrap */
        $bootstrap = ObjectManager::getInstance()->create(Bootstrap::class);
        $bootstrap->initInstance();
        $installer = $setup->startSetup();

        $databaseHandler = new DatabaseHandler($installer);
        $databaseHandler->createEntityTable(TestRepository::TABLE_NAME);

        $installer->endSetup();

        RepositoryRegistry::registerRepository(StudentEntity::CLASS_NAME, TestRepository::getClassName());
    }

    /**
     * Cleans up all storage services used by repositories
     */
    public function cleanUpStorage()
    {
        /** @var Setup $setup */
        $setup = ObjectManager::getInstance()->create(Setup::class);
        $installer = $setup->startSetup();

        $databaseHandler = new DatabaseHandler($installer);
        $databaseHandler->dropEntityTable(TestRepository::TABLE_NAME);

        $installer->endSetup();
    }
}
