<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * Class DatabaseHandler
 *
 * @package Packlink\PacklinkPro\Setup
 */
class DatabaseHandler
{
    /**
     * @var \Magento\Framework\Setup\SchemaSetupInterface
     */
    private $installer;

    public function __construct(SchemaSetupInterface $installer)
    {
        $this->installer = $installer;
    }

    /**
     * Creates Packlink entity table.
     *
     * @param string $tableName Name of the table.
     *
     * @throws \Zend_Db_Exception
     */
    public function createEntityTable($tableName)
    {
        $entityTable = $this->installer->getTable($tableName);

        if (!$this->installer->getConnection()->isTableExists($entityTable)) {
            $entityTable = $this->installer->getConnection()
                ->newTable($this->installer->getTable($tableName))
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
                )
                ->addColumn(
                    'index_1',
                    Table::TYPE_TEXT,
                    255,
                    ['default' => null, 'nullable' => true],
                    'Index1'
                )
                ->addColumn(
                    'index_2',
                    Table::TYPE_TEXT,
                    255,
                    ['default' => null, 'nullable' => true],
                    'Index2'
                )
                ->addColumn(
                    'index_3',
                    Table::TYPE_TEXT,
                    255,
                    ['default' => null, 'nullable' => true],
                    'Index3'
                )
                ->addColumn(
                    'index_4',
                    Table::TYPE_TEXT,
                    255,
                    ['default' => null, 'nullable' => true],
                    'Index4'
                )
                ->addColumn(
                    'index_5',
                    Table::TYPE_TEXT,
                    255,
                    ['default' => null, 'nullable' => true],
                    'Index5'
                )
                ->addColumn(
                    'index_6',
                    Table::TYPE_TEXT,
                    255,
                    ['default' => null, 'nullable' => true],
                    'Index6'
                )
                ->addColumn(
                    'index_7',
                    Table::TYPE_TEXT,
                    255,
                    ['default' => null, 'nullable' => true],
                    'Index7'
                )
                ->addColumn(
                    'index_8',
                    Table::TYPE_TEXT,
                    255,
                    ['default' => null, 'nullable' => true],
                    'Index8'
                )
                ->addColumn(
                    'data',
                    Table::TYPE_TEXT,
                    Table::MAX_TEXT_SIZE,
                    ['nullable' => false],
                    'Data'
                );

            $this->installer->getConnection()->createTable($entityTable);
        }
    }

    /**
     * Adds additional index column to the Packlink entity table.
     */
    public function addAdditionalIndex()
    {
        $entityTable = $this->installer->getTable(InstallSchema::PACKLINK_ENTITY_TABLE);

        if ($this->installer->getConnection()->isTableExists($entityTable)) {
            $this->installer->getConnection()->addColumn(
                $entityTable,
                'index_8',
                [
                    'type' => Table::TYPE_TEXT,
                    'size' => 255,
                    'nullable' => true,
                    'comment' => 'Index8',
                ]
            );
        }
    }

    /**
     * Drops Packlink entity table.
     *
     * @param string $tableName Name of the table.
     */
    public function dropEntityTable($tableName)
    {
        $tableInstance = $this->installer->getTable($tableName);
        if ($this->installer->getConnection()->isTableExists($tableInstance)) {
            $this->installer->getConnection()->dropTable($tableInstance);
        }
    }
}
