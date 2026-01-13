<?php
/**
 * RepositoryInterface.php - Base Repository Interface for SafeShift EHR
 * 
 * Defines the contract that all repositories must implement.
 * Provides a consistent data access layer abstraction.
 * 
 * @package    SafeShift\Model\Interfaces
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Model\Interfaces;

/**
 * Repository interface
 * 
 * Defines the contract for all repositories in the system.
 * Repositories provide data access abstraction between the domain
 * layer and the persistence layer.
 */
interface RepositoryInterface
{
    /**
     * Find entity by ID
     * 
     * @param string $id Entity unique identifier (UUID)
     * @return EntityInterface|null Entity instance or null if not found
     */
    public function findById(string $id): ?EntityInterface;

    /**
     * Find all entities matching criteria
     * 
     * @param array<string, mixed> $criteria Search criteria
     * @param array<string, string> $orderBy Order by fields ['field' => 'ASC|DESC']
     * @param int|null $limit Maximum number of results
     * @param int|null $offset Number of results to skip
     * @return array<int, EntityInterface> Array of matching entities
     */
    public function findAll(
        array $criteria = [],
        array $orderBy = [],
        ?int $limit = null,
        ?int $offset = null
    ): array;

    /**
     * Find single entity matching criteria
     * 
     * @param array<string, mixed> $criteria Search criteria
     * @return EntityInterface|null Entity instance or null if not found
     */
    public function findOneBy(array $criteria): ?EntityInterface;

    /**
     * Create a new entity
     * 
     * @param array<string, mixed> $data Entity data
     * @return EntityInterface Created entity
     * @throws \InvalidArgumentException If data validation fails
     */
    public function create(array $data): EntityInterface;

    /**
     * Update an existing entity
     * 
     * @param string $id Entity ID to update
     * @param array<string, mixed> $data Updated entity data
     * @return EntityInterface Updated entity
     * @throws \InvalidArgumentException If data validation fails
     * @throws \RuntimeException If entity not found
     */
    public function update(string $id, array $data): EntityInterface;

    /**
     * Delete an entity
     * 
     * @param string $id Entity ID to delete
     * @return bool True if deleted successfully
     * @throws \RuntimeException If entity not found
     */
    public function delete(string $id): bool;

    /**
     * Check if entity exists
     * 
     * @param string $id Entity ID
     * @return bool True if entity exists
     */
    public function exists(string $id): bool;

    /**
     * Count entities matching criteria
     * 
     * @param array<string, mixed> $criteria Search criteria
     * @return int Number of matching entities
     */
    public function count(array $criteria = []): int;

    /**
     * Save entity (create or update)
     * 
     * Determines whether to create or update based on entity state.
     * 
     * @param EntityInterface $entity Entity to save
     * @return EntityInterface Saved entity
     */
    public function save(EntityInterface $entity): EntityInterface;

    /**
     * Begin database transaction
     * 
     * @return bool True if transaction started
     */
    public function beginTransaction(): bool;

    /**
     * Commit database transaction
     * 
     * @return bool True if transaction committed
     */
    public function commit(): bool;

    /**
     * Rollback database transaction
     * 
     * @return bool True if transaction rolled back
     */
    public function rollback(): bool;

    /**
     * Get the entity class this repository manages
     * 
     * @return string Fully qualified entity class name
     */
    public function getEntityClass(): string;

    /**
     * Get the database table name for this repository
     * 
     * @return string Table name
     */
    public function getTableName(): string;
}
