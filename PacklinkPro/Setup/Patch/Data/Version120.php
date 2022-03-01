<?php

namespace Packlink\PacklinkPro\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Models\ShippingMethod;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShippingMethod\Models\ShippingPricePolicy;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\Logger;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\RepositoryRegistry;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\Services\BusinessLogic\CarrierService;
use Packlink\PacklinkPro\Setup\InstallSchema;
use Packlink\PacklinkPro\Setup\Patch\AbstractPatch;

/**
 * Class Version120
 *
 * @package Packlink\PacklinkPro\Setup\Patch\Data
 */
class Version120 extends AbstractPatch implements DataPatchInterface
{
    /**
     * @inheritDoc
     */
    public static function getVersion()
    {
        return '1.2.0';
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
        Logger::logInfo('Started executing V1.2.0 update script.');

        $this->convertParcelProperties($this->databaseHandler->getInstaller());

        $this->updateShippingMethods($this->databaseHandler->getInstaller());

        Logger::logInfo('Update script V1.2.0 has been successfully completed.');
    }

    /**
     * Converts parcel properties from strings to numbers.
     *
     * @param ModuleDataSetupInterface $setup
     */
    protected function convertParcelProperties(ModuleDataSetupInterface $setup)
    {
        $connection = $setup->getConnection();

        $select = $connection->select()
            ->from('packlink_entity')
            ->where('index_1 = ?', 'defaultParcel');

        $entities = $connection->fetchAll($select);

        foreach ($entities as $entity) {
            if (empty($entity['data'])) {
                continue;
            }

            $parcel = json_decode($entity['data'], true);

            if (!empty($parcel['value']['weight'])) {
                $weight = (float)$parcel['value']['weight'];
                $parcel['value']['weight'] = !empty($weight) ? $weight : 1;
            }

            foreach (['length', 'height', 'width'] as $field) {
                if (!empty($parcel['value'][$field])) {
                    $fieldValue = (int)$parcel['value'][$field];
                    $parcel['value'][$field] = !empty($fieldValue) ? $fieldValue : 10;
                }
            }

            if (!empty($entity['id'])) {
                $connection->update(InstallSchema::PACKLINK_ENTITY_TABLE, ['data' => json_encode($parcel)], ['id =? ' => $entity['id']]);
            }
        }
    }

    /**
     * Updates shipping methods.
     *
     * @param ModuleDataSetupInterface $setup
     *
     * @throws RepositoryNotRegisteredException
     */
    protected function updateShippingMethods(ModuleDataSetupInterface $setup)
    {
        $repository = RepositoryRegistry::getRepository(ShippingMethod::getClassName());
        /** @var CarrierService $carrierService */
        $carrierService = ServiceRegister::getService(ShopShippingMethodService::CLASS_NAME);

        $entities = $this->getShippingMethodRecords($setup);

        foreach ($entities as $entity) {
            $data = json_decode($entity['data'], true);
            $data['pricingPolicies'] = $this->getTransformedPricingPolicies($data);
            $data['logoUrl'] = $this->getLogoUrl($data);

            $shippingMethod = ShippingMethod::fromArray($data);
            $repository->update($shippingMethod);

            if ($shippingMethod->isActivated()) {
                $carrierService->update($shippingMethod);
            }
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
     * Returns transformed pricing policies for a given shipping method.
     *
     * @param array $method
     *
     * @return array
     */
    protected function getTransformedPricingPolicies(array $method)
    {
        $result = [];

        if (empty($method['pricingPolicy'])) {
            return $result;
        }

        switch ($method['pricingPolicy']) {
            case 1:
                // Packlink prices.
                break;
            case 2:
                // Percent prices.
                $pricingPolicy = new ShippingPricePolicy();
                $pricingPolicy->rangeType = ShippingPricePolicy::RANGE_PRICE_AND_WEIGHT;
                $pricingPolicy->fromPrice = 0;
                $pricingPolicy->fromWeight = 0;
                $pricingPolicy->pricingPolicy = ShippingPricePolicy::POLICY_PACKLINK_ADJUST;
                $pricingPolicy->increase = $method['percentPricePolicy']['increase'];
                $pricingPolicy->changePercent = $method['percentPricePolicy']['amount'];
                $result[] = $pricingPolicy->toArray();
                break;
            case 3:
                // Fixed price by weight.
                foreach ($method['fixedPriceByWeightPolicy'] as $policy) {
                    $pricingPolicy = new ShippingPricePolicy();
                    $pricingPolicy->rangeType = ShippingPricePolicy::RANGE_WEIGHT;
                    $pricingPolicy->fromWeight = $policy['from'];
                    $pricingPolicy->toWeight = !empty($policy['to']) ? $policy['to'] : null;
                    $pricingPolicy->pricingPolicy = ShippingPricePolicy::POLICY_FIXED_PRICE;
                    $pricingPolicy->fixedPrice = $policy['amount'];
                    $result[] = $pricingPolicy->toArray();
                }
                break;
            case 4:
                // Fixed price by price.
                foreach ($method['fixedPriceByValuePolicy'] as $policy) {
                    $pricingPolicy = new ShippingPricePolicy();
                    $pricingPolicy->rangeType = ShippingPricePolicy::RANGE_PRICE;
                    $pricingPolicy->fromPrice = $policy['from'];
                    $pricingPolicy->toPrice = !empty($policy['to']) ? $policy['to'] : null;
                    $pricingPolicy->pricingPolicy = ShippingPricePolicy::POLICY_FIXED_PRICE;
                    $pricingPolicy->fixedPrice = $policy['amount'];
                    $result[] = $pricingPolicy->toArray();
                }
                break;
        }

        return $result;
    }

    /**
     * Returns updated carrier logo file path for the given shipping method.
     *
     * @param array $method
     *
     * @return string
     */
    protected function getLogoUrl($method)
    {
        if (strpos($method['logoUrl'], '/images/carriers/') === false) {
            return  $method['logoUrl'];
        }

        return str_replace('/images/carriers/', '/packlink/images/carriers/', $method['logoUrl']);
    }
}
