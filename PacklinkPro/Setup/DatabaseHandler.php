<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\SetupInterface;

/**
 * Class DatabaseHandler
 *
 * @package Packlink\PacklinkPro\Setup
 */
class DatabaseHandler
{
    /**
     * Packlink entity table name
     */
    const ENTITY_TABLE = 'packlink_entity';

    /**
     * @var ModuleDataSetupInterface
     */
    private $installer;

    public function __construct(SetupInterface $installer)
    {
        $this->installer = $installer;
    }

    /**
     * @return ModuleDataSetupInterface
     */
    public function getInstaller()
    {
        return $this->installer;
    }

    /**
     * Adds additional index column to the Packlink entity table.
     */
    public function addAdditionalIndex()
    {
        $entityTable = $this->installer->getTable(self::ENTITY_TABLE);

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
