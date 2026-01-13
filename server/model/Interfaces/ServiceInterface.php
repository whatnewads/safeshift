<?php
/**
 * ServiceInterface.php - Base Service Interface for SafeShift EHR
 * 
 * Defines the contract that all services must implement.
 * Services contain business logic and validation rules.
 * 
 * @package    SafeShift\Model\Interfaces
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Model\Interfaces;

/**
 * Service interface
 * 
 * Defines the contract for all services in the system.
 * Services encapsulate business logic and provide validation
 * for domain operations.
 */
interface ServiceInterface
{
    /**
     * Validate data
     * 
     * Validates the provided data against business rules.
     * Returns an array of validation errors, empty if valid.
     * 
     * @param array<string, mixed> $data Data to validate
     * @return array<string, string|array<string>> Validation errors by field
     */
    public function validate(array $data): array;

    /**
     * Check if data is valid
     * 
     * Convenience method to check if data passes validation.
     * 
     * @param array<string, mixed> $data Data to validate
     * @return bool True if data is valid
     */
    public function isValid(array $data): bool;

    /**
     * Get validation rules
     * 
     * Returns the validation rules for this service.
     * 
     * @return array<string, array<string, mixed>> Validation rules by field
     */
    public function getValidationRules(): array;

    /**
     * Get last validation errors
     * 
     * Returns the errors from the last validation call.
     * 
     * @return array<string, string|array<string>> Last validation errors
     */
    public function getErrors(): array;

    /**
     * Clear validation errors
     * 
     * @return self
     */
    public function clearErrors(): self;

    /**
     * Check if service has errors
     * 
     * @return bool True if there are validation errors
     */
    public function hasErrors(): bool;

    /**
     * Add a validation error
     * 
     * @param string $field Field name
     * @param string $message Error message
     * @return self
     */
    public function addError(string $field, string $message): self;

    /**
     * Process data through service logic
     * 
     * Main entry point for service operations. Validates and
     * processes the data according to business rules.
     * 
     * @param array<string, mixed> $data Input data
     * @return array<string, mixed> Processed result
     * @throws \InvalidArgumentException If validation fails
     */
    public function process(array $data): array;

    /**
     * Get service name
     * 
     * Returns a human-readable name for this service.
     * 
     * @return string Service name
     */
    public function getName(): string;
}
