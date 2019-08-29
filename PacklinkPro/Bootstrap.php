<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro;

use Packlink\PacklinkPro\Entity\QuoteCarrierDropOffMapping;
use Packlink\PacklinkPro\Entity\ShopOrderDetails;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\BootstrapComponent;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Configuration;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Interfaces\OrderRepository;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Scheduler\Models\Schedule;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Models\ShippingMethod;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Configuration\ConfigEntity;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Http\CurlHttpClient;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Http\HttpClient;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\Interfaces\ShopLoggerAdapter;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\LogData;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Entity;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\RepositoryRegistry;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\Process;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\QueueItem;
use Packlink\PacklinkPro\Repository\BaseRepository;
use Packlink\PacklinkPro\Repository\QueueItemRepository;
use Packlink\PacklinkPro\Services\BusinessLogic\CarrierService;
use Packlink\PacklinkPro\Services\BusinessLogic\ConfigurationService;
use Packlink\PacklinkPro\Services\BusinessLogic\OrderRepositoryService;
use Packlink\PacklinkPro\Services\Infrastructure\LoggerService;

/**
 * Class Bootstrap
 *
 * @package Packlink\PacklinkPro
 */
class Bootstrap extends BootstrapComponent
{
    /**
     * Class instance.
     *
     * @var static
     */
    protected static $instance;
    /**
     * @var CurlHttpClient
     */
    private $httpClientService;
    /**
     * @var LoggerService
     */
    private $loggerService;
    /**
     * @var ConfigurationService
     */
    private $configService;
    /**
     * @var OrderRepositoryService
     */
    private $orderRepositoryService;
    /**
     * @var CarrierService
     */
    private $carrierService;

    /**
     * Bootstrap constructor.
     *
     * @param CurlHttpClient $httpClientService
     * @param LoggerService $loggerService
     * @param ConfigurationService $configService
     * @param OrderRepositoryService $orderRepositoryService
     * @param CarrierService $carrierService
     */
    public function __construct(
        CurlHttpClient $httpClientService,
        LoggerService $loggerService,
        ConfigurationService $configService,
        OrderRepositoryService $orderRepositoryService,
        CarrierService $carrierService
    ) {
        $this->httpClientService = $httpClientService;
        $this->loggerService = $loggerService;
        $this->configService = $configService;
        $this->orderRepositoryService = $orderRepositoryService;
        $this->carrierService = $carrierService;

        static::$instance = $this;
    }

    /**
     * Initializes instance.
     */
    public function initInstance()
    {
        self::init();
    }

    /**
     * Initializes infrastructure services and utilities.
     */
    protected static function initServices()
    {
        parent::initServices();

        static::$instance->initInstanceServices();
    }

    /**
     * Initializes repositories.
     *
     * @throws IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryClassException
     */
    protected static function initRepositories()
    {
        parent::initRepositories();

        RepositoryRegistry::registerRepository(Process::CLASS_NAME, BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(ConfigEntity::CLASS_NAME, BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(QueueItem::CLASS_NAME, QueueItemRepository::getClassName());
        RepositoryRegistry::registerRepository(Schedule::CLASS_NAME, BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(ShopOrderDetails::CLASS_NAME, BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(ShippingMethod::CLASS_NAME, BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(QuoteCarrierDropOffMapping::CLASS_NAME, BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(Entity::CLASS_NAME, BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(LogData::CLASS_NAME, BaseRepository::getClassName());
    }

    /**
     * Initializes instance services.
     */
    protected function initInstanceServices()
    {
        $instance = static::$instance;

        ServiceRegister::registerService(
            ShopLoggerAdapter::CLASS_NAME,
            function () use ($instance) {
                return $instance->loggerService;
            }
        );

        ServiceRegister::registerService(
            Configuration::CLASS_NAME,
            function () use ($instance) {
                return $instance->configService;
            }
        );

        ServiceRegister::registerService(
            HttpClient::CLASS_NAME,
            function () use ($instance) {
                return $instance->httpClientService;
            }
        );

        ServiceRegister::registerService(
            OrderRepository::CLASS_NAME,
            function () use ($instance) {
                return $instance->orderRepositoryService;
            }
        );

        ServiceRegister::registerService(
            ShopShippingMethodService::CLASS_NAME,
            function () use ($instance) {
                return $instance->carrierService;
            }
        );
    }
}
