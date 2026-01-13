<?php
/**
 * PhoneNumber.php - Phone Number Value Object for SafeShift EHR
 * 
 * Immutable value object representing a phone number with
 * validation and formatting capabilities.
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
 * PhoneNumber value object
 * 
 * Represents a validated phone number with formatting options.
 * Supports US phone numbers primarily but can handle international formats.
 * Immutable after creation.
 */
final class PhoneNumber
{
    /** @var string The raw phone number (digits only) */
    private string $value;
    
    /** @var string|null Country code (e.g., '1' for US) */
    private ?string $countryCode;
    
    /** @var string Phone type (mobile, home, work, fax) */
    private string $type;

    /** Phone type constants */
    public const TYPE_MOBILE = 'mobile';
    public const TYPE_HOME = 'home';
    public const TYPE_WORK = 'work';
    public const TYPE_FAX = 'fax';
    public const TYPE_OTHER = 'other';

    /**
     * Create a new PhoneNumber instance
     * 
     * @param string $phone Phone number to validate and store
     * @param string $type Phone type (mobile, home, work, fax)
     * @throws InvalidArgumentException If phone number is invalid
     */
    public function __construct(string $phone, string $type = self::TYPE_OTHER)
    {
        $this->validate($phone);
        $this->parseNumber($phone);
        $this->type = $this->validateType($type);
    }

    /**
     * Validate the phone number
     * 
     * @param string $phone Phone number to validate
     * @throws InvalidArgumentException If phone number is invalid
     */
    private function validate(string $phone): void
    {
        // Remove all non-digit characters
        $digits = preg_replace('/[^0-9]/', '', $phone);
        
        if (empty($digits)) {
            throw new InvalidArgumentException('Phone number cannot be empty');
        }
        
        // US numbers should be 10-11 digits (with or without country code)
        if (strlen($digits) < 10) {
            throw new InvalidArgumentException('Phone number must have at least 10 digits');
        }
        
        if (strlen($digits) > 15) {
            throw new InvalidArgumentException('Phone number is too long (max 15 digits)');
        }
    }

    /**
     * Parse the phone number and extract components
     * 
     * @param string $phone Raw phone number input
     */
    private function parseNumber(string $phone): void
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        
        // Handle US country code
        if (strlen($digits) === 11 && $digits[0] === '1') {
            $this->countryCode = '1';
            $this->value = substr($digits, 1);
        } elseif (strlen($digits) === 10) {
            $this->countryCode = '1'; // Assume US
            $this->value = $digits;
        } else {
            // International format
            $this->countryCode = null;
            $this->value = $digits;
        }
    }

    /**
     * Validate phone type
     * 
     * @param string $type Phone type
     * @return string Validated type
     */
    private function validateType(string $type): string
    {
        $validTypes = [
            self::TYPE_MOBILE,
            self::TYPE_HOME,
            self::TYPE_WORK,
            self::TYPE_FAX,
            self::TYPE_OTHER,
        ];
        
        $type = strtolower($type);
        
        if (!in_array($type, $validTypes, true)) {
            return self::TYPE_OTHER;
        }
        
        return $type;
    }

    /**
     * Get the raw phone number (digits only, no country code)
     * 
     * @return string Raw digits
     */
    public function getRaw(): string
    {
        return $this->value;
    }

    /**
     * Get full number with country code
     * 
     * @return string Full number with country code
     */
    public function getFullNumber(): string
    {
        if ($this->countryCode !== null) {
            return $this->countryCode . $this->value;
        }
        return $this->value;
    }

    /**
     * Get formatted phone number
     * 
     * @param string $format Format style: 'standard', 'dots', 'dashes', 'e164'
     * @return string Formatted phone number
     */
    public function getFormatted(string $format = 'standard'): string
    {
        // Only format US numbers (10 digits)
        if (strlen($this->value) !== 10) {
            return $this->value;
        }
        
        $areaCode = substr($this->value, 0, 3);
        $exchange = substr($this->value, 3, 3);
        $subscriber = substr($this->value, 6, 4);
        
        return match ($format) {
            'standard' => "({$areaCode}) {$exchange}-{$subscriber}",
            'dots' => "{$areaCode}.{$exchange}.{$subscriber}",
            'dashes' => "{$areaCode}-{$exchange}-{$subscriber}",
            'e164' => "+{$this->countryCode}{$this->value}",
            'national' => "{$areaCode}-{$exchange}-{$subscriber}",
            default => "({$areaCode}) {$exchange}-{$subscriber}",
        };
    }

    /**
     * Get E.164 formatted number (international standard)
     * 
     * @return string E.164 formatted number (e.g., +15551234567)
     */
    public function getE164(): string
    {
        $countryCode = $this->countryCode ?? '1';
        return '+' . $countryCode . $this->value;
    }

    /**
     * Get the country code
     * 
     * @return string|null Country code or null if not set
     */
    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    /**
     * Get the phone type
     * 
     * @return string Phone type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the area code (for US numbers)
     * 
     * @return string|null Area code or null if not US number
     */
    public function getAreaCode(): ?string
    {
        if (strlen($this->value) === 10) {
            return substr($this->value, 0, 3);
        }
        return null;
    }

    /**
     * Check if this is a US number
     * 
     * @return bool True if US number
     */
    public function isUSNumber(): bool
    {
        return $this->countryCode === '1' && strlen($this->value) === 10;
    }

    /**
     * Check if this is a mobile number (based on type)
     * 
     * @return bool True if mobile type
     */
    public function isMobile(): bool
    {
        return $this->type === self::TYPE_MOBILE;
    }

    /**
     * Get masked phone number for display
     * 
     * @return string Masked phone (e.g., (***) ***-1234)
     */
    public function getMasked(): string
    {
        if (strlen($this->value) >= 4) {
            $lastFour = substr($this->value, -4);
            return "(***) ***-{$lastFour}";
        }
        return '***-****';
    }

    /**
     * String representation of phone number
     * 
     * @return string Formatted phone number
     */
    public function __toString(): string
    {
        return $this->getFormatted();
    }

    /**
     * Check equality with another PhoneNumber
     * 
     * @param PhoneNumber $other Another PhoneNumber to compare
     * @return bool True if phone numbers are equal
     */
    public function equals(PhoneNumber $other): bool
    {
        return $this->getFullNumber() === $other->getFullNumber();
    }

    /**
     * Create PhoneNumber from string (factory method)
     * 
     * @param string $phone Phone number string
     * @param string $type Phone type
     * @return self New PhoneNumber instance
     * @throws InvalidArgumentException If phone is invalid
     */
    public static function fromString(string $phone, string $type = self::TYPE_OTHER): self
    {
        return new self($phone, $type);
    }

    /**
     * Check if a string is a valid phone number without throwing exception
     * 
     * @param string $phone Phone number to validate
     * @return bool True if valid
     */
    public static function isValid(string $phone): bool
    {
        try {
            new self($phone);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Serialize to array
     * 
     * @return array{value: string, country_code: ?string, type: string, formatted: string}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'country_code' => $this->countryCode,
            'type' => $this->type,
            'formatted' => $this->getFormatted(),
        ];
    }
}
