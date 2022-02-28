<?php

namespace Packlink\PacklinkPro\Setup\Patch;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;
use Packlink\PacklinkPro\Setup\DatabaseHandler;

/**
 * Class AbstractPatch
 *
 * @package Packlink\PacklinkPro\Setup\Patch
 */
abstract class AbstractPatch implements PatchVersionInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    protected $moduleDataSetup;
    /**
     * @var DatabaseHandler
     */
    protected $databaseHandler;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->databaseHandler = new DatabaseHandler($this->moduleDataSetup);
    }
}