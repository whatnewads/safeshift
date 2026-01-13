<?php
/**
 * UUID.php - UUID Value Object for SafeShift EHR
 * 
 * Immutable value object representing a Universally Unique Identifier
 * with generation and validation capabilities.
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
 * UUID value object
 * 
 * Represents a validated UUID (Universally Unique Identifier).
 * Supports UUID v4 (random) generation and validation.
 * Immutable after creation.
 */
final class UUID
{
    /** @var string The UUID value */
    private string $value;

    /** UUID v4 regex pattern */
    private const UUID_V4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
    
    /** General UUID regex pattern (any version) */
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * Create a new UUID instance
     * 
     * @param string|null $uuid UUID string to use, or null to generate new
     * @throws InvalidArgumentException If UUID is invalid
     */
    public function __construct(?string $uuid = null)
    {
        if ($uuid === null) {
            $this->value = self::generateV4();
        } else {
            $this->validate($uuid);
            $this->value = strtolower($uuid);
        }
    }

    /**
     * Validate UUID format
     * 
     * @param string $uuid UUID to validate
     * @throws InvalidArgumentException If UUID is invalid
     */
    private function validate(string $uuid): void
    {
        $uuid = trim($uuid);
        
        if (empty($uuid)) {
            throw new InvalidArgumentException('UUID cannot be empty');
        }
        
        if (!preg_match(self::UUID_PATTERN, $uuid)) {
            throw new InvalidArgumentException('Invalid UUID format');
        }
    }

    /**
     * Generate a new UUID v4 (random)
     * 
     * @return string Generated UUID
     */
    private static function generateV4(): string
    {
        // Generate 16 random bytes
        $data = random_bytes(16);
        
        // Set version to 0100 (UUID v4)
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        
        // Set variant to 10xx
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        // Format as UUID string
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Generate a new UUID instance
     * 
     * @return self New UUID instance
     */
    public static function generate(): self
    {
        return new self();
    }

    /**
     * Create UUID from string (factory method)
     * 
     * @param string $uuid UUID string
     * @return self New UUID instance
     * @throws InvalidArgumentException If UUID is invalid
     */
    public static function fromString(string $uuid): self
    {
        return new self($uuid);
    }

    /**
     * Create UUID from binary (16 bytes)
     * 
     * @param string $binary Binary UUID (16 bytes)
     * @return self New UUID instance
     * @throws InvalidArgumentException If binary is invalid
     */
    public static function fromBinary(string $binary): self
    {
        if (strlen($binary) !== 16) {
            throw new InvalidArgumentException('Binary UUID must be exactly 16 bytes');
        }
        
        $hex = bin2hex($binary);
        $uuid = sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
        
        return new self($uuid);
    }

    /**
     * Get the UUID value
     * 
     * @return string UUID string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Get UUID as binary (16 bytes)
     * 
     * @return string Binary representation
     */
    public function toBinary(): string
    {
        return hex2bin(str_replace('-', '', $this->value));
    }

    /**
     * Get UUID without dashes
     * 
     * @return string UUID without dashes (32 hex characters)
     */
    public function getHex(): string
    {
        return str_replace('-', '', $this->value);
    }

    /**
     * Get UUID in uppercase
     * 
     * @return string Uppercase UUID
     */
    public function getUppercase(): string
    {
        return strtoupper($this->value);
    }

    /**
     * Get UUID version
     * 
     * @return int UUID version (1-5) or 0 if unknown
     */
    public function getVersion(): int
    {
        // Version is the first character of the 3rd group
        $version = substr($this->value, 14, 1);
        return is_numeric($version) ? (int) $version : 0;
    }

    /**
     * Check if this is a v4 (random) UUID
     * 
     * @return bool True if UUID v4
     */
    public function isV4(): bool
    {
        return $this->getVersion() === 4;
    }

    /**
     * Get the variant
     * 
     * @return string Variant identifier
     */
    public function getVariant(): string
    {
        $variant = hexdec(substr($this->value, 19, 1));
        
        if (($variant & 0x8) === 0) {
            return 'NCS';
        } elseif (($variant & 0xC) === 0x8) {
            return 'RFC4122';
        } elseif (($variant & 0xE) === 0xC) {
            return 'Microsoft';
        } else {
            return 'Future';
        }
    }

    /**
     * Check if this is a nil (all zeros) UUID
     * 
     * @return bool True if nil UUID
     */
    public function isNil(): bool
    {
        return $this->value === '00000000-0000-0000-0000-000000000000';
    }

    /**
     * Create a nil UUID (all zeros)
     * 
     * @return self Nil UUID instance
     */
    public static function nil(): self
    {
        return new self('00000000-0000-0000-0000-000000000000');
    }

    /**
     * String representation of UUID
     * 
     * @return string UUID string
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Check equality with another UUID
     * 
     * @param UUID $other Another UUID to compare
     * @return bool True if UUIDs are equal
     */
    public function equals(UUID $other): bool
    {
        return $this->value === $other->getValue();
    }

    /**
     * Check equality with a string UUID
     * 
     * @param string $uuid UUID string to compare
     * @return bool True if equal
     */
    public function equalsString(string $uuid): bool
    {
        return $this->value === strtolower(trim($uuid));
    }

    /**
     * Check if a string is a valid UUID without throwing exception
     * 
     * @param string $uuid UUID to validate
     * @return bool True if valid
     */
    public static function isValid(string $uuid): bool
    {
        return (bool) preg_match(self::UUID_PATTERN, $uuid);
    }

    /**
     * Check if a string is a valid UUID v4 without throwing exception
     * 
     * @param string $uuid UUID to validate
     * @return bool True if valid v4 UUID
     */
    public static function isValidV4(string $uuid): bool
    {
        return (bool) preg_match(self::UUID_V4_PATTERN, $uuid);
    }

    /**
     * JSON serialization
     * 
     * @return string UUID string
     */
    public function jsonSerialize(): string
    {
        return $this->value;
    }

    /**
     * Compare two UUIDs
     * 
     * @param UUID $other UUID to compare with
     * @return int -1, 0, or 1
     */
    public function compareTo(UUID $other): int
    {
        return strcmp($this->value, $other->getValue());
    }

    /**
     * Get timestamp from UUID v1 (if applicable)
     * 
     * Note: Only works for UUID v1, returns null for other versions.
     * 
     * @return \DateTimeInterface|null Timestamp or null if not v1
     */
    public function getTimestamp(): ?\DateTimeInterface
    {
        if ($this->getVersion() !== 1) {
            return null;
        }
        
        $timeLow = substr($this->value, 0, 8);
        $timeMid = substr($this->value, 9, 4);
        $timeHi = substr($this->value, 14, 4);
        
        $timestamp = hexdec($timeHi . $timeMid . $timeLow);
        $unixTime = ($timestamp - 122192928000000000) / 10000000;
        
        $dateTime = new \DateTimeImmutable();
        return $dateTime->setTimestamp((int) $unixTime);
    }

    /**
     * Create multiple UUIDs at once
     * 
     * @param int $count Number of UUIDs to generate
     * @return array<int, self> Array of UUID instances
     */
    public static function generateMultiple(int $count): array
    {
        $uuids = [];
        for ($i = 0; $i < $count; $i++) {
            $uuids[] = self::generate();
        }
        return $uuids;
    }
}
