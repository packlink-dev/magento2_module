<?php

namespace Packlink\PacklinkPro\Setup\Patch\Schema;

use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\Logger;
use Packlink\PacklinkPro\Setup\Patch\AbstractPatch;

/**
 * Class Version114
 *
 * @package Packlink\PacklinkPro\Setup\Patch\Schema
 */
class Version114 extends AbstractPatch implements SchemaPatchInterface
{
    /**
     * @inheritDoc
     */
    public static function getVersion()
    {
        return '1.1.4';
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
        Logger::logInfo('Started executing V1.1.4 update script.');

        $this->databaseHandler->addAdditionalIndex();

        Logger::logInfo('Update script V1.1.4 has been successfully completed.');
    }
}