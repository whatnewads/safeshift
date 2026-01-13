<?php
/**
 * EntityInterface.php - Base Entity Interface for SafeShift EHR
 * 
 * Defines the contract that all domain entities must implement.
 * Provides consistent methods for entity identification, serialization,
 * and safe data exposure.
 * 
 * @package    SafeShift\Model\Interfaces
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Model\Interfaces;

/**
 * Entity interface
 * 
 * Defines the contract for all domain entities in the system.
 * Ensures consistent behavior for identification and serialization.
 */
interface EntityInterface
{
    /**
     * Get the entity's unique identifier
     * 
     * @return string|null The entity ID (UUID) or null if not yet persisted
     */
    public function getId(): ?string;

    /**
     * Convert entity to array representation
     * 
     * Returns all entity properties as an associative array.
     * This may include sensitive data and should be used carefully.
     * 
     * @return array<string, mixed> Entity data as array
     */
    public function toArray(): array;

    /**
     * Convert entity to safe array representation
     * 
     * Returns entity properties safe for external exposure.
     * Sensitive data (passwords, SSN, etc.) are either excluded
     * or masked in this representation.
     * 
     * @return array<string, mixed> Safe entity data as array
     */
    public function toSafeArray(): array;

    /**
     * Create entity instance from array data
     * 
     * Factory method to instantiate an entity from an array of data,
     * typically from database results or API input.
     * 
     * @param array<string, mixed> $data Entity data
     * @return static New entity instance
     */
    public static function fromArray(array $data): static;

    /**
     * Check if entity has been persisted
     * 
     * @return bool True if entity has an ID (persisted), false otherwise
     */
    public function isPersisted(): bool;

    /**
     * Get entity creation timestamp
     * 
     * @return \DateTimeInterface|null Creation timestamp or null if not set
     */
    public function getCreatedAt(): ?\DateTimeInterface;

    /**
     * Get entity last update timestamp
     * 
     * @return \DateTimeInterface|null Update timestamp or null if not set
     */
    public function getUpdatedAt(): ?\DateTimeInterface;

    /**
     * Validate entity data
     * 
     * Checks if the entity's current state is valid for persistence.
     * 
     * @return array<string, string> Array of validation errors (empty if valid)
     */
    public function validate(): array;
}
