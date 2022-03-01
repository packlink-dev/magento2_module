<?php

namespace Packlink\PacklinkPro\Setup\Patch\Data;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\DTO\Exceptions\FrontDtoValidationException;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Http\DTO\SystemInfo;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Models\ShippingMethod;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Models\ShippingPricePolicy;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\SystemInformation\SystemInfoService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Tasks\UpdateShippingServicesTask;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Configuration\Configuration;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\Logger;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\RepositoryRegistry;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\QueueService;
use Packlink\PacklinkPro\Services\BusinessLogic\ConfigurationService;
use Packlink\PacklinkPro\Setup\InstallSchema;
use Packlink\PacklinkPro\Setup\Patch\AbstractPatch;

/**
 * Class Version130
 *
 * @package Packlink\PacklinkPro\Setup\Patch\Data
 */
class Version130 extends AbstractPatch implements DataPatchInterface
{
    /**
     * @inheritDoc
     */
    public static function getVersion()
    {
        return '1.3.0';
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
        Logger::logInfo('Started executing V1.3.0 update script.');

        $this->updateSystemSpecificShippingMethods($this->databaseHandler->getInstaller());
        $this->updateShippingServices();

        Logger::logInfo('Update script V1.3.0 has been successfully completed.');
    }

    /**
     * Updates Packlink shipping services.
     *
     * @throws QueueStorageUnavailableException
     */
    protected function updateShippingServices()
    {
        /** @var QueueService $queueService */
        $queueService = ServiceRegister::getService(QueueService::CLASS_NAME);

        if ($queueService->findLatestByType('UpdateShippingServicesTask') !== null) {
            $queueService->enqueue($this->getConfigService()->getDefaultQueueName(), new UpdateShippingServicesTask());
        }
    }

    /**
     * Updates system specific shipping methods.
     *
     * @param ModuleDataSetupInterface $setup
     *
     * @throws NoSuchEntityException
     * @throws RepositoryNotRegisteredException
     * @throws FrontDtoValidationException
     */
    protected function updateSystemSpecificShippingMethods(ModuleDataSetupInterface $setup)
    {
        $repository = RepositoryRegistry::getRepository(ShippingMethod::getClassName());
        /** @var \Packlink\PacklinkPro\Services\BusinessLogic\SystemInfoService $systemInfoService */
        $systemInfoService = ServiceRegister::getService(SystemInfoService::CLASS_NAME);
        $systemDetails = $systemInfoService->getSystemDetails();

        $entities = $this->getShippingMethodRecords($setup);

        foreach ($entities as $entity) {
            $data = json_decode($entity['data'], true);
            $data['currency'] = 'EUR';
            $data['fixedPrices'] = null;
            $data['systemDefaults'] = null;
            $data['pricingPolicies'] = $this->getSystemSpecificPricingPolicies($data, $systemDetails);

            $shippingMethod = ShippingMethod::fromArray($data);
            $repository->update($shippingMethod);
        }
    }

    /**
     * Returns shipping method records from the entity table.
     *
     * @param ModuleDataSetupInterface $setup
     *
     * @return array
     */
    protected function getShippingMethodRecords(ModuleDataSetupInterface $setup)
    {
        $connection = $setup->getConnection();

        $select = $connection->select()
            ->from(InstallSchema::PACKLINK_ENTITY_TABLE)
            ->where('type = ?', 'ShippingService');

        return $connection->fetchAll($select);
    }

    /**
     * Returns system specific pricing policies for a given shipping method.
     *
     * @param array $method
     * @param SystemInfo[] $systemDetails
     *
     * @return array
     *
     * @throws FrontDtoValidationException
     */
    protected function getSystemSpecificPricingPolicies(array $method, $systemDetails)
    {
        $policies = [];

        if (!empty($method['pricingPolicies'])) {
            foreach ($method['pricingPolicies'] as $policy) {
                foreach ($systemDetails as $systemInfo) {
                    $newPolicy = ShippingPricePolicy::fromArray($policy);
                    $newPolicy->systemId = $systemInfo->systemId;

                    $policies[] = $newPolicy->toArray();
                }
            }
        }

        return $policies;
    }

    /**
     * Gets the instance of the configuration service.
     *
     * @return ConfigurationService
     */
    protected function getConfigService()
    {
        /** @var ConfigurationService $configuration */
        $configuration = ServiceRegister::getService(Configuration::CLASS_NAME);

        return $configuration;
    }
}
