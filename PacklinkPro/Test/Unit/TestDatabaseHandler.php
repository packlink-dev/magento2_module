<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Test\Unit;

use Magento\Framework\DB\Ddl\Table;
use Packlink\PacklinkPro\Setup\DatabaseHandler;

/**
 * Class TestDatabaseHandler
 *
 * Creates the throwaway entity tables the repository tests need.
 *
 * DatabaseHandler::createEntityTable() was removed in "Switch to declarative database schema"
 * (cf7f5e0), because production tables now come from etc/db_schema.xml. The test tables
 * (TestRepository::TABLE_NAME, TestQueueItemRepository::TABLE_NAME) are deliberately NOT in
 * db_schema.xml - they must not exist in a real installation - so they still have to be built at
 * runtime. Keeping that here rather than back on DatabaseHandler means production keeps the
 * declarative-only schema and nothing extra ships: deploy.sh strips PacklinkPro/Test.
 *
 * @package Packlink\PacklinkPro\Test\Unit
 */
class TestDatabaseHandler extends DatabaseHandler
{
    /**
     * Creates an entity table matching the packlink_entity layout declared in etc/db_schema.xml.
     *
     * @param string $tableName Name of the table.
     */
    public function createEntityTable($tableName)
    {
        $installer = $this->getInstaller();
        $entityTable = $installer->getTable($tableName);

        if ($installer->getConnection()->isTableExists($entityTable)) {
            return;
        }

        $table = $installer->getConnection()
            ->newTable($entityTable)
            ->addColumn(
                'id',
                Table::TYPE_INTEGER,
                null,
                [
                    'identity' => true,
                    'unsigned' => true,
                    'nullable' => false,
                    'primary' => true,
                    'auto_increment' => true,
                ],
                'Id'
            )
            ->addColumn(
                'type',
                Table::TYPE_TEXT,
                128,
                ['nullable' => false],
                'Type'
            );

        // index_1 .. index_8, matching db_schema.xml.
        for ($i = 1; $i <= 8; $i++) {
            $table->addColumn(
                'index_' . $i,
                Table::TYPE_TEXT,
                255,
                ['default' => null, 'nullable' => true],
                'Index' . $i
            );
        }

        $table->addColumn(
            'data',
            Table::TYPE_TEXT,
            Table::MAX_TEXT_SIZE,
            ['nullable' => false],
            'Data'
        );

        $installer->getConnection()->createTable($table);
    }
}
