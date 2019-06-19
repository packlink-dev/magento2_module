<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Helper;

use Magento\Backend\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Config\ConfigOptionsListConstants;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\Model\Theme\ThemeProvider;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Configuration;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Models\ShippingMethod;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Entity;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\QueryFilter\QueryFilter;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\RepositoryRegistry;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\QueueItem;
use Packlink\PacklinkPro\Repository\BaseRepository;
use Packlink\PacklinkPro\Repository\QueueItemRepository;
use Packlink\PacklinkPro\Services\BusinessLogic\ConfigurationService;

/**
 * Class SystemInfoHelper
 *
 * @package Packlink\PacklinkPro\Helper
 */
class SystemInfoHelper
{
    const PHP_INFO_FILE_NAME = 'phpinfo.html';
    const SYSTEM_INFO_FILE_NAME = 'system-info.txt';
    const SYSTEM_LOG_FILE_NAME = 'system-logs.txt';
    const DEBUG_LOG_FILE_NAME = 'debug-logs.txt';
    const USER_INFO_FILE_NAME = 'packlink-user-info.txt';
    const QUEUE_INFO_FILE_NAME = 'queue.txt';
    const PARCEL_WAREHOUSE_FILE_NAME = 'parcel-warehouse.txt';
    const ENTITY_TABLE_FILE_NAME = 'entity-table.txt';
    const SERVICE_INFO_FILE_NAME = 'services.txt';
    const MODULE_NAME = 'Packlink_PacklinkPro';
    /**
     * @var ProductMetadataInterface
     */
    protected $productMetadata;
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var ThemeProvider
     */
    protected $themeProvider;
    /**
     * @var Data
     */
    protected $backendHelper;
    /**
     * @var DeploymentConfig
     */
    protected $deploymentConfig;
    /**
     * @var ModuleListInterface
     */
    protected $moduleList;
    /**
     * @var DirectoryList
     */
    protected $directoryList;
    /**
     * @var ConfigurationService
     */
    private $configService;

    /**
     * SystemInfoHelper constructor.
     *
     * @param \Magento\Framework\App\ProductMetadataInterface $productMetadata
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Theme\Model\Theme\ThemeProvider $themeProvider
     * @param \Magento\Backend\Helper\Data $backendHelper
     * @param \Magento\Framework\App\DeploymentConfig $deploymentConfig
     * @param \Magento\Framework\Module\ModuleListInterface $moduleList
     * @param \Magento\Framework\Filesystem\DirectoryList $directoryList
     */
    public function __construct(
        ProductMetadataInterface $productMetadata,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        ThemeProvider $themeProvider,
        Data $backendHelper,
        DeploymentConfig $deploymentConfig,
        ModuleListInterface $moduleList,
        DirectoryList $directoryList
    ) {
        $this->productMetadata = $productMetadata;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->themeProvider = $themeProvider;
        $this->backendHelper = $backendHelper;
        $this->deploymentConfig = $deploymentConfig;
        $this->moduleList = $moduleList;
        $this->directoryList = $directoryList;
    }

    /**
     * Returns information about current Magento store and the state of the plugin.
     *
     * @return string
     *
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    public function getSystemInfo()
    {
        $file = tempnam(sys_get_temp_dir(), 'packlink_system_info');

        $zip = new \ZipArchive();
        $zip->open($file, \ZipArchive::CREATE);

        $phpInfo = $this->getPhpInfo();

        if ($phpInfo !== false) {
            $zip->addFromString(static::PHP_INFO_FILE_NAME, $phpInfo);
        }

        $zip->addFromString(static::SYSTEM_INFO_FILE_NAME, $this->getMagentoInfo());
        $zip->addFromString(static::SYSTEM_LOG_FILE_NAME, $this->getSystemLogs());
        $zip->addFromString(static::DEBUG_LOG_FILE_NAME, $this->getDebugLogs());
        $zip->addFromString(static::USER_INFO_FILE_NAME, $this->getUserInfo());
        $zip->addFromString(static::QUEUE_INFO_FILE_NAME, $this->getQueueStatus());
        $zip->addFromString(static::PARCEL_WAREHOUSE_FILE_NAME, $this->getParcelAndWarehouseInfo());
        $zip->addFromString(static::SERVICE_INFO_FILE_NAME, $this->getServicesInfo());
        $zip->addFromString(static::ENTITY_TABLE_FILE_NAME, $this->getEntityTable());

        $zip->close();

        return $file;
    }

    /**
     * Retrieves php info.
     *
     * @return false | string
     */
    protected function getPhpInfo()
    {
        ob_start();
        phpinfo();

        return ob_get_clean();
    }

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getMagentoInfo()
    {
        $result = 'Magento version: ' . $this->productMetadata->getVersion();
        $result .= "\ntheme: " . $this->getThemeCode();
        $result .= "\nbase admin url: " . $this->backendHelper->getHomePageUrl();
        $result .= "\ndatabase: " . $this->deploymentConfig->get(
                ConfigOptionsListConstants::CONFIG_PATH_DB_CONNECTION_DEFAULT
                . '/' . ConfigOptionsListConstants::KEY_NAME
            );
        $result .= "\nplugin version: " . $this->moduleList->getOne(self::MODULE_NAME)['setup_version'];

        return $result;
    }

    /**
     * Returns all logs contained within Magento system log.
     *
     * @return string
     *
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    protected function getSystemLogs()
    {
        return $this->getLogs('system');
    }

    /**
     * Returns all logs contained within Magento debug log.
     *
     * @return string
     *
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    protected function getDebugLogs()
    {
        return $this->getLogs('debug');
    }

    /**
     * Returns user info.
     *
     * @return string
     */
    protected function getUserInfo()
    {
        $result = 'user info :' . json_encode($this->getConfigService()->getUserInfo());

        $result .= "\n\napi key: " . $this->getConfigService()->getAuthorizationToken();

        return $result;
    }

    /**
     * Returns current queue status.
     *
     * @return string
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getQueueStatus()
    {
        $result = [];

        try {
            /** @var QueueItemRepository $repository */
            $repository = RepositoryRegistry::getRepository(QueueItem::CLASS_NAME);

            $query = new QueryFilter();
            $query->orWhere('status', '=', QueueItem::QUEUED);
            $query->orWhere('status', '=', QueueItem::CREATED);
            $query->orWhere('status', '=', QueueItem::IN_PROGRESS);
            $query->orWhere('status', '=', QueueItem::FAILED);

            $result = $repository->select($query);
        } catch (RepositoryNotRegisteredException $e) {
        } catch (QueryFilterInvalidParamException $e) {
        }

        return $this->formatJsonOutput($result);
    }

    /**
     * Returns parcel and warehouse information.
     *
     * @return string
     */
    protected function getParcelAndWarehouseInfo()
    {
        $result = 'default parcel: ' . json_encode($this->getConfigService()->getDefaultParcel() ?: []);
        $result .= "\n\ndefault warehouse: " . json_encode($this->getConfigService()->getDefaultWarehouse() ?: []);

        return $result;
    }

    /**
     * Returns service info.
     *
     * @return string
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    protected function getServicesInfo()
    {
        $result = [];

        try {
            /** @var BaseRepository $repository */
            $repository = RepositoryRegistry::getRepository(ShippingMethod::CLASS_NAME);
            $result = $repository->select();
        } catch (RepositoryNotRegisteredException $e) {
        }

        return "[\n" . $this->formatJsonOutput($result) . "\n]";
    }

    /**
     * Returns all records from Packlink entity table.
     *
     * @return string
     */
    protected function getEntityTable()
    {
        $result = [];

        try {
            /** @var BaseRepository $repository */
            $repository = RepositoryRegistry::getRepository(Entity::CLASS_NAME);
            $result = $repository->encodeAllEntities();
        } catch (RepositoryNotRegisteredException $e) {
        } catch (LocalizedException $e) {
        }

        return $result;
    }

    /**
     * Returns all logs for the provided log type.
     *
     * @param string $logType Type of requested logs (system or debug)
     *
     * @return string
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    private function getLogs($logType)
    {
        $logs = '';
        $logPath = $this->directoryList->getPath('log') . '/' . $logType . '.log';
        if (file_exists($logPath)) {
            $logs = file_get_contents($logPath);
        }

        return $logs . "\n]";
    }

    /**
     * Returns theme code.
     *
     * @return string Theme code.
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getThemeCode()
    {
        $themeId = $this->scopeConfig->getValue(
            DesignInterface::XML_PATH_THEME_ID,
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getId()
        );

        /** @var $theme \Magento\Framework\View\Design\ThemeInterface */
        $theme = $this->themeProvider->getThemeById($themeId);

        return $theme ? $theme->getCode() : '';
    }

    /**
     * Formats json output.
     *
     * @param array $items
     *
     * @return string
     */
    private function formatJsonOutput(array &$items)
    {
        $result = '';

        foreach ($items as $item) {
            if (is_array($item)) {
                $result .= json_encode($item) . ",\n\n";
            } else {
                $result .= json_encode($item->toArray()) . ",\n\n";
            }
        }

        return rtrim($result, ",\n");
    }

    /**
     * Returns instance of configuration service.
     *
     * @return ConfigurationService
     */
    private function getConfigService()
    {
        if ($this->configService === null) {
            $this->configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        }

        return $this->configService;
    }
}
