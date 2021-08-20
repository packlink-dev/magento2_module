<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2021 Packlink
 */

namespace Packlink\PacklinkPro\Services\Infrastructure;

use Magento\Framework\App\ObjectManager;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Configuration\Configuration;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\Interfaces\ShopLoggerAdapter;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\LogData;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\Logger;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Singleton;
use Packlink\PacklinkPro\Services\BusinessLogic\ConfigurationService;
use Psr\Log\LoggerInterface;

/**
 * Class LoggerService
 *
 * @package Packlink\PacklinkPro\Services\Infrastructure
 */
class LoggerService extends Singleton implements ShopLoggerAdapter
{
    /**
     * Singleton instance of this class.
     *
     * @var static
     */
    protected static $instance;
    /**
     * Log level names for corresponding log level codes.
     *
     * @var array
     */
    private static $logLevelName = [
        Logger::ERROR => 'error',
        Logger::WARNING => 'warning',
        Logger::INFO => 'info',
        Logger::DEBUG => 'debug',
    ];
    /**
     * Magento logger interface.
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Logger service constructor.
     *
     * @param LoggerInterface $logger Magento logger interface.
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct();

        $this->logger = $logger;

        static::$instance = $this;
    }

    /**
     * Logs message in the system.
     *
     * @param LogData $data
     */
    public function logMessage(LogData $data)
    {
        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        $minLogLevel = $configService->getMinLogLevel();
        $logLevel = $data->getLogLevel();

        if (($logLevel > $minLogLevel) && !$configService->isDebugModeEnabled()) {
            return;
        }

        $message = 'PACKLINK LOG: 
            Date: ' . date('d/m/Y') . '
            Time: ' . date('H:i:s') . '
            Log level: ' . self::$logLevelName[$logLevel] . '
            Message: ' . $data->getMessage();
        $context = $data->getContext();
        if (!empty($context)) {
            $message .= '
            Context data: [';
            foreach ($context as $item) {
                $message .= '"' . $item->getName() . '" => "' . print_r($item->getValue(), true) . '", ';
            }

            $message .= ']';
        }

        \call_user_func([$this->logger, self::$logLevelName[$logLevel]], $message);
    }

    /**
     * Creates instance of this class.
     *
     * @return static
     */
    public static function create()
    {
        return ObjectManager::getInstance()->create(__CLASS__);
    }
}
