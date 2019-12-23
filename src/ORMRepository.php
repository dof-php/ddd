<?php

declare(strict_types=1);

namespace DOF\DDD;

use DOF\Util\Collection;

interface ORMRepository extends RepositoryInterface
{
    /**
     * Add an entity to repository
     *
     * @param Entity $entity
     * @return int|null: Primary key when added succssflly or null on failed
     */
    public function add(Entity &$entity) : Entity;

    /**
     * Remove an entity from repository
     *
     * @param Entity|int: The entity instance or primary key of entity to remove
     * @return int|null: Number of rows affected or null on failed
     */
    public function remove($entity) : ?int;

    /**
     * Update an entity from repository
     *
     * @param Entity $entity: The entity instance to be updated
     * @param Reference $updated: Status of entity is actually updated or not
     * @return Entity|null: The entity updated
     */
    public function save(Entity &$entity, &$updated = false) : ?Entity;

    /**
     * Find entity by primary key
     *
     * @param int $id: Primary key
     * @return Entity|null
     */
    public function find(int $id) : ?Entity;

    /**
     * Get list of entities with pagination
     *
     * @param int $page
     * @param int $size
     * @param Collection $filter
     * @param string $sortField
     * @param string $sortOrder
     * @return array
     */
    public function list(
        int $page,
        int $size,
        Collection $filter,
        string $sortField = null,
        string $sortOrder = null
    );
}
