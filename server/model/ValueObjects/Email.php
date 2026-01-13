<?php
/**
 * Email.php - Email Value Object for SafeShift EHR
 * 
 * Immutable value object representing an email address with
 * built-in validation.
 * 
 * @package    SafeShift\Model\ValueObjects
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Model\ValueObjects;

use InvalidArgumentException;

/**
 * Email value object
 * 
 * Represents a validated email address. Immutable after creation.
 */
final class Email
{
    /** @var string The validated email address */
    private string $value;

    /**
     * Create a new Email instance
     * 
     * @param string $email Email address to validate and store
     * @throws InvalidArgumentException If email is invalid
     */
    public function __construct(string $email)
    {
        $this->validate($email);
        $this->value = strtolower(trim($email));
    }

    /**
     * Validate the email address
     * 
     * @param string $email Email address to validate
     * @throws InvalidArgumentException If email is invalid
     */
    private function validate(string $email): void
    {
        $email = trim($email);
        
        if (empty($email)) {
            throw new InvalidArgumentException('Email address cannot be empty');
        }
        
        if (strlen($email) > 254) {
            throw new InvalidArgumentException('Email address is too long (max 254 characters)');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address format');
        }
        
        // Additional RFC 5321 validation
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException('Invalid email address format');
        }
        
        [$local, $domain] = $parts;
        
        // Local part validation
        if (strlen($local) > 64) {
            throw new InvalidArgumentException('Email local part is too long (max 64 characters)');
        }
        
        // Domain validation
        if (strlen($domain) > 253) {
            throw new InvalidArgumentException('Email domain is too long (max 253 characters)');
        }
        
        // Check domain has valid format
        if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?)*\.[a-zA-Z]{2,}$/', $domain)) {
            throw new InvalidArgumentException('Invalid email domain format');
        }
    }

    /**
     * Get the email address value
     * 
     * @return string The email address
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Get the local part (before @)
     * 
     * @return string Local part of email
     */
    public function getLocalPart(): string
    {
        return explode('@', $this->value)[0];
    }

    /**
     * Get the domain part (after @)
     * 
     * @return string Domain part of email
     */
    public function getDomain(): string
    {
        return explode('@', $this->value)[1];
    }

    /**
     * Check if email is from a specific domain
     * 
     * @param string $domain Domain to check
     * @return bool True if email is from the specified domain
     */
    public function isFromDomain(string $domain): bool
    {
        return strtolower($this->getDomain()) === strtolower($domain);
    }

    /**
     * Check if email matches a list of allowed domains
     * 
     * @param array<string> $allowedDomains List of allowed domains
     * @return bool True if email domain is in the list
     */
    public function isAllowedDomain(array $allowedDomains): bool
    {
        $emailDomain = strtolower($this->getDomain());
        
        foreach ($allowedDomains as $domain) {
            if (strtolower($domain) === $emailDomain) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get masked version of email for display
     * 
     * @return string Masked email (e.g., j***@example.com)
     */
    public function getMasked(): string
    {
        $local = $this->getLocalPart();
        $domain = $this->getDomain();
        
        if (strlen($local) <= 2) {
            return $local[0] . '***@' . $domain;
        }
        
        return $local[0] . str_repeat('*', min(strlen($local) - 2, 5)) . $local[-1] . '@' . $domain;
    }

    /**
     * String representation of email
     * 
     * @return string The email address
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Check equality with another Email
     * 
     * @param Email $other Another Email to compare
     * @return bool True if emails are equal
     */
    public function equals(Email $other): bool
    {
        return $this->value === $other->getValue();
    }

    /**
     * Create Email from string (factory method)
     * 
     * @param string $email Email address string
     * @return self New Email instance
     * @throws InvalidArgumentException If email is invalid
     */
    public static function fromString(string $email): self
    {
        return new self($email);
    }

    /**
     * Check if a string is a valid email without throwing exception
     * 
     * @param string $email Email address to validate
     * @return bool True if valid
     */
    public static function isValid(string $email): bool
    {
        try {
            new self($email);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
