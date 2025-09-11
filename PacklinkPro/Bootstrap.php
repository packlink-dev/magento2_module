<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2022 Packlink
 */

namespace Packlink\PacklinkPro;

use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Module\Dir;
use Magento\Store\Model\Information;
use Magento\Store\Model\StoreManagerInterface;
use Packlink\PacklinkPro\Entity\QuoteCarrierDropOffMapping;
use Packlink\PacklinkPro\IntegrationCore\Brands\Packlink\PacklinkConfigurationService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\BootstrapComponent;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Brand\BrandConfigurationService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\CashOnDelivery\Model\CashOnDelivery;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Configuration;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\FileResolver\FileResolverService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Interfaces\ShopOrderService as ShopOrderServiceInterface;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\OrderShipmentDetails\Models\OrderShipmentDetails;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Scheduler\Models\Schedule;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShipmentDraft\Models\OrderSendDraftTaskMap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Models\ShippingMethod;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Configuration\ConfigEntity;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Http\CurlHttpClient;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Http\HttpClient;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\Interfaces\ShopLoggerAdapter;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\LogData;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Entity;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\RepositoryRegistry;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Serializer\Concrete\JsonSerializer;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Serializer\Serializer;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\Process;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\QueueItem;
use Packlink\PacklinkPro\Repository\BaseRepository;
use Packlink\PacklinkPro\Repository\QueueItemRepository;
use Packlink\PacklinkPro\Services\BusinessLogic\CarrierService;
use Packlink\PacklinkPro\Services\BusinessLogic\ConfigurationService;
use Packlink\PacklinkPro\Services\BusinessLogic\ShopOrderService;
use Packlink\PacklinkPro\Services\BusinessLogic\SystemInfoService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\SystemInformation\SystemInfoService as SystemInfoInterface;
use Packlink\PacklinkPro\Services\BusinessLogic\UserAccountService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\User\UserAccountService as BaseUserAccountService;
use Packlink\PacklinkPro\Services\Infrastructure\LoggerService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Registration\RegistrationInfoService as RegistrationInfoServiceInterface;
use Packlink\PacklinkPro\Services\BusinessLogic\RegistrationInfoService;
use Magento\Framework\Module\Dir\Reader;

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
     * @var ShopOrderService
     */
    private $shopOrderService;
    /**
     * @var CarrierService
     */
    private $carrierService;
    /**
     * @var UserAccountService
     */
    private $userAccountService;
    /**
     * @var Session
     */
    private $session;
    /**
     * @var Information
     */
    private $information;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var SystemInfoService
     */
    private $systemInfoService;
    /**
     * @var Reader
     */
    private $moduleReader;

    /**
     * Bootstrap constructor.
     *
     * @param CurlHttpClient $httpClientService
     * @param LoggerService $loggerService
     * @param ConfigurationService $configService
     * @param ShopOrderService $shopOrderService
     * @param CarrierService $carrierService
     * @param UserAccountService $userAccountService
     * @param \Magento\Backend\Model\Auth\Session $session
     * @param \Magento\Store\Model\Information $information
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param SystemInfoService $systemInfoService
     * @param \Magento\Framework\Module\Dir\Reader $moduleReader
     */
    public function __construct(
        CurlHttpClient $httpClientService,
        LoggerService $loggerService,
        ConfigurationService $configService,
        ShopOrderService $shopOrderService,
        CarrierService $carrierService,
        UserAccountService $userAccountService,
        Session $session,
        Information $information,
        StoreManagerInterface $storeManager,
        SystemInfoService $systemInfoService,
        Reader $moduleReader
    ) {
        $this->httpClientService = $httpClientService;
        $this->loggerService = $loggerService;
        $this->configService = $configService;
        $this->shopOrderService = $shopOrderService;
        $this->carrierService = $carrierService;
        $this->userAccountService = $userAccountService;
        $this->session = $session;
        $this->information = $information;
        $this->storeManager = $storeManager;
        $this->systemInfoService = $systemInfoService;
        $this->moduleReader = $moduleReader;

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
        RepositoryRegistry::registerRepository(OrderShipmentDetails::CLASS_NAME, BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(ShippingMethod::CLASS_NAME, BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(QuoteCarrierDropOffMapping::CLASS_NAME, BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(Entity::CLASS_NAME, BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(LogData::CLASS_NAME, BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(OrderSendDraftTaskMap::CLASS_NAME, BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(CashOnDelivery::getClassName(), BaseRepository::getClassName());
    }

    /**
     * Initializes instance services.
     */
    protected function initInstanceServices()
    {
        $instance = static::$instance;

        ServiceRegister::registerService(
            Serializer::CLASS_NAME,
            function () {
                return new JsonSerializer();
            }
        );

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
            BrandConfigurationService::CLASS_NAME,
            function () {
                return new PacklinkConfigurationService();
            }
        );

        ServiceRegister::registerService(
            HttpClient::CLASS_NAME,
            function () use ($instance) {
                return $instance->httpClientService;
            }
        );

        ServiceRegister::registerService(
            ShopOrderServiceInterface::CLASS_NAME,
            function () use ($instance) {
                return $instance->shopOrderService;
            }
        );

        ServiceRegister::registerService(
            ShopShippingMethodService::CLASS_NAME,
            function () use ($instance) {
                return $instance->carrierService;
            }
        );

        ServiceRegister::registerService(
            BaseUserAccountService::CLASS_NAME,
            function () use ($instance) {
                return $instance->userAccountService;
            }
        );

        ServiceRegister::registerService(
            RegistrationInfoServiceInterface::CLASS_NAME,
            function () {
                return new RegistrationInfoService(
                    $this->session,
                    $this->information,
                    $this->storeManager
                );
            }
        );

        ServiceRegister::registerService(
            SystemInfoInterface::CLASS_NAME,
            function () use ($instance) {
                return $instance->systemInfoService;
            }
        );

        $viewDir = $this->moduleReader->getModuleDir(
            Dir::MODULE_VIEW_DIR,
            'Packlink_PacklinkPro'
        );

        ServiceRegister::registerService(
            FileResolverService::CLASS_NAME,
            function () use ($viewDir) {
                return new FileResolverService([
                    $viewDir . '/adminhtml/web/packlink/countries',
                    $viewDir . '/adminhtml/web/packlink/brand/countries',
                ]);
            }
        );
    }
}
