<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2021 Packlink
 */

namespace Packlink\PacklinkPro\Services\Infrastructure;

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
    private static $maskedContextPattern =
        '/(token|api[ _-]*key|secret|password|passwd|auth|email|phone|address|zip|postal)/i';
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
                $message .= '"' . $item->getName() . '" => "' . $this->formatContextValue($item) . '", ';
            }

            $message .= ']';
        }

        \call_user_func([$this->logger, self::$logLevelName[$logLevel]], $message);
    }

    /**
     * Renders a log context value, masking anything that looks like a secret or
     * personal data so it does not reach the log file or the support export.
     *
     * @param \Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\LogContextData $item
     *
     * @return string
     */
    private function formatContextValue($item)
    {
        if (preg_match(static::$maskedContextPattern, $item->getName())) {
            return '***';
        }

        return print_r($item->getValue(), true);
    }
}
