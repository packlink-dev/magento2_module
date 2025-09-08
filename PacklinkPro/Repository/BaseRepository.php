<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Repository;

use Magento\Framework\App\ObjectManager;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Entity;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Interfaces\RepositoryInterface;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\QueryFilter\QueryFilter;
use Packlink\PacklinkPro\ResourceModel\PacklinkEntity;

class BaseRepository implements RepositoryInterface
{
    /**
     * Fully qualified name of this class.
     */
    const THIS_CLASS_NAME = __CLASS__;
    /**
     * Number of indexes in Packlink entity table.
     */
    const NUMBER_OF_INDEXES = 7;
    /**
     * @var string
     */
    protected $entityClass;
    /**
     * @var PacklinkEntity
     */
    protected $resourceEntity;
    /**
     * Name of the base entity table in database.
     */
    const TABLE_NAME = 'packlink_entity';

    /**
     * Returns full class name.
     *
     * @return string Full class name.
     */
    public static function getClassName()
    {
        return static::THIS_CLASS_NAME;
    }

    /**
     * BaseRepository constructor.
     */
    public function __construct()
    {
        $this->resourceEntity = ObjectManager::getInstance()->create($this->getResourceEntity());
        $this->resourceEntity->setTableName(static::TABLE_NAME);
    }

    /**
     * Sets repository entity.
     *
     * @param string $entityClass Repository entity class.
     */
    public function setEntityClass($entityClass)
    {
        $this->entityClass = $entityClass;
    }

    /**
     * Selects all Packlink entities in the system.
     *
     * @return array All entities as arrays.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function selectAll()
    {
        return $this->resourceEntity->selectAll();
    }

    /**
     * Executes select query.
     *
     * @param QueryFilter $filter Filter for query.
     *
     * @return Entity[] A list of found entities ot empty array.
     *
     * @throws QueryFilterInvalidParamException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function select(QueryFilter $filter = null)
    {
        /** @var Entity $entity */
        $entity = new $this->entityClass;

        $records = $this->resourceEntity->selectEntities($filter, $entity);

        return $this->deserializeEntities($records);
    }

    /**
     * Executes select query and returns first result.
     *
     * @param QueryFilter $filter Filter for query.
     *
     * @return Entity|null First found entity or NULL.
     *
     * @throws QueryFilterInvalidParamException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function selectOne(QueryFilter $filter = null)
    {
        if ($filter === null) {
            $filter = new QueryFilter();
        }

        $filter->setLimit(1);
        $results = $this->select($filter);

        return empty($results) ? null : $results[0];
    }

    /**
     * Executes saveEntity query and returns ID of created entity. PacklinkEntity will be updated with new ID.
     *
     * @param Entity $entity PacklinkEntity to be saved.
     *
     * @return int Identifier of saved entity.
     *
     * @throws \Exception
     */
    public function save(Entity $entity)
    {
        $id = $this->resourceEntity->saveEntity($entity);
        $entity->setId($id);
        $this->resourceEntity->updateEntity($entity);

        return $id;
    }

    /**
     * Executes updateEntity query and returns success flag.
     *
     * @param Entity $entity PacklinkEntity to be updated.
     *
     * @return bool TRUE if operation succeeded; otherwise, FALSE.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function update(Entity $entity)
    {
        return $this->resourceEntity->updateEntity($entity);
    }

    /**
     * Executes delete query and returns success flag.
     *
     * @param Entity $entity PacklinkEntity to be deleted.
     *
     * @return bool TRUE if operation succeeded; otherwise, FALSE.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(Entity $entity)
    {
        return $this->resourceEntity->deleteEntity((int)$entity->getId());
    }

    /**
     * Counts records that match filter criteria.
     *
     * @param QueryFilter $filter Filter for query.
     *
     * @return int Number of records that match filter criteria.
     *
     * @throws QueryFilterInvalidParamException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function count(QueryFilter $filter = null)
    {
        return count($this->select($filter));
    }

    /**
     * Returns resource entity.
     *
     * @return string Resource entity class name.
     */
    protected function getResourceEntity()
    {
        return PacklinkEntity::class;
    }

    /**
     * Translates database records to Packlink entities.
     *
     * @param array $records Array of database records.
     *
     * @return Entity[]
     */
    protected function deserializeEntities($records)
    {
        $entities = [];
        foreach ($records as $record) {
            /** @var Entity $entity */
            $entity = $this->deserializeEntity($record['data']);
            if ($entity !== null) {
                $entity->setId((int)$record['id']);
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    /**
     * Deserialize entity from given string.
     *
     * @param string $data Serialized entity as string.
     *
     * @return Entity Created entity object.
     */
    private function deserializeEntity($data)
    {
        $jsonEntity = json_decode($data, true);

        if (empty($jsonEntity)) {
            return null;
        }

        if (array_key_exists('class_name', $jsonEntity)) {
            $entity = new $jsonEntity['class_name'];
        } else {
            $entity = new $this->entityClass;
        }

        /** @var Entity $entity */
        $entity->inflate($jsonEntity);

        return $entity;
    }
}
