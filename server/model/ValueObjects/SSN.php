<?php
/**
 * SSN.php - Social Security Number Value Object for SafeShift EHR
 * 
 * Immutable value object representing a Social Security Number with
 * encryption, masking, and HIPAA-compliant handling.
 * 
 * @package    SafeShift\Model\ValueObjects
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Model\ValueObjects;

use InvalidArgumentException;
use RuntimeException;

/**
 * SSN value object
 * 
 * Represents a Social Security Number with secure encryption and masking.
 * SSNs are always stored encrypted and only decrypted when necessary.
 * Immutable after creation.
 * 
 * HIPAA COMPLIANCE NOTE:
 * SSNs are considered PHI and must be encrypted at rest.
 * Access to decrypted values should be logged for audit purposes.
 */
final class SSN
{
    /** @var string The encrypted SSN */
    private string $encrypted;
    
    /** @var string Last 4 digits for display/verification (stored unencrypted) */
    private string $lastFour;
    
    /** @var string Encryption cipher method */
    private const CIPHER = 'aes-256-gcm';
    
    /** @var int Tag length for GCM mode */
    private const TAG_LENGTH = 16;

    /**
     * Create a new SSN instance from plain text
     * 
     * @param string $ssn Plain text SSN to validate and encrypt
     * @throws InvalidArgumentException If SSN is invalid
     * @throws RuntimeException If encryption fails
     */
    public function __construct(string $ssn)
    {
        $normalized = $this->normalize($ssn);
        $this->validate($normalized);
        $this->lastFour = substr($normalized, -4);
        $this->encrypted = $this->encrypt($normalized);
    }

    /**
     * Create SSN from already encrypted value (from database)
     * 
     * @param string $encrypted Encrypted SSN
     * @param string $lastFour Last 4 digits
     * @return self New SSN instance
     */
    public static function fromEncrypted(string $encrypted, string $lastFour): self
    {
        $instance = new \ReflectionClass(self::class);
        $ssn = $instance->newInstanceWithoutConstructor();
        
        $encryptedProp = $instance->getProperty('encrypted');
        $encryptedProp->setValue($ssn, $encrypted);
        
        $lastFourProp = $instance->getProperty('lastFour');
        $lastFourProp->setValue($ssn, $lastFour);
        
        return $ssn;
    }

    /**
     * Normalize SSN by removing formatting
     * 
     * @param string $ssn Raw SSN input
     * @return string Normalized SSN (9 digits only)
     */
    private function normalize(string $ssn): string
    {
        return preg_replace('/[^0-9]/', '', $ssn);
    }

    /**
     * Validate the SSN format
     * 
     * @param string $ssn Normalized SSN (9 digits)
     * @throws InvalidArgumentException If SSN is invalid
     */
    private function validate(string $ssn): void
    {
        if (strlen($ssn) !== 9) {
            throw new InvalidArgumentException('SSN must be exactly 9 digits');
        }
        
        // SSN cannot start with 9 (reserved for ITINs)
        if ($ssn[0] === '9') {
            throw new InvalidArgumentException('Invalid SSN: cannot start with 9');
        }
        
        // SSN cannot start with 000
        if (substr($ssn, 0, 3) === '000') {
            throw new InvalidArgumentException('Invalid SSN: area number cannot be 000');
        }
        
        // Group number cannot be 00
        if (substr($ssn, 3, 2) === '00') {
            throw new InvalidArgumentException('Invalid SSN: group number cannot be 00');
        }
        
        // Serial number cannot be 0000
        if (substr($ssn, 5, 4) === '0000') {
            throw new InvalidArgumentException('Invalid SSN: serial number cannot be 0000');
        }
        
        // Known invalid SSNs
        $invalidSSNs = [
            '078051120', // Woolworth's promotional SSN
            '219099999', // Famous invalid SSN
            '123456789', // Sequential pattern
        ];
        
        if (in_array($ssn, $invalidSSNs, true)) {
            throw new InvalidArgumentException('Invalid SSN: known invalid number');
        }
        
        // Check for obvious patterns (all same digit)
        if (preg_match('/^(\d)\1{8}$/', $ssn)) {
            throw new InvalidArgumentException('Invalid SSN: cannot be all same digits');
        }
    }

    /**
     * Encrypt the SSN
     * 
     * @param string $ssn Plain text SSN to encrypt
     * @return string Encrypted SSN (base64 encoded)
     * @throws RuntimeException If encryption fails
     */
    private function encrypt(string $ssn): string
    {
        $key = $this->getEncryptionKey();
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $tag = '';
        
        $encrypted = openssl_encrypt(
            $ssn,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );
        
        if ($encrypted === false) {
            throw new RuntimeException('SSN encryption failed');
        }
        
        // Combine IV, tag, and encrypted data
        return base64_encode($iv . $tag . $encrypted);
    }

    /**
     * Decrypt the SSN
     * 
     * @return string Decrypted SSN
     * @throws RuntimeException If decryption fails
     */
    private function decrypt(): string
    {
        $key = $this->getEncryptionKey();
        $data = base64_decode($this->encrypted);
        
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, $ivLength, self::TAG_LENGTH);
        $encrypted = substr($data, $ivLength + self::TAG_LENGTH);
        
        $decrypted = openssl_decrypt(
            $encrypted,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        
        if ($decrypted === false) {
            throw new RuntimeException('SSN decryption failed');
        }
        
        return $decrypted;
    }

    /**
     * Get the encryption key
     * 
     * @return string Encryption key
     * @throws RuntimeException If key is not configured
     */
    private function getEncryptionKey(): string
    {
        // Try environment variable first
        $key = getenv('SSN_ENCRYPTION_KEY');
        
        if ($key === false) {
            $key = $_ENV['SSN_ENCRYPTION_KEY'] ?? null;
        }
        
        if ($key === null) {
            // Try defined constant (legacy support)
            if (defined('SSN_ENCRYPTION_KEY')) {
                $key = SSN_ENCRYPTION_KEY;
            }
        }
        
        if (empty($key)) {
            throw new RuntimeException('SSN encryption key not configured');
        }
        
        // Ensure key is proper length for AES-256 (32 bytes)
        return hash('sha256', $key, true);
    }

    /**
     * Get masked SSN for display
     * 
     * @return string Masked SSN (e.g., ***-**-1234)
     */
    public function getMasked(): string
    {
        return '***-**-' . $this->lastFour;
    }

    /**
     * Get partially masked SSN
     * 
     * @return string Partially masked (e.g., XXX-XX-1234)
     */
    public function getPartiallyMasked(): string
    {
        return 'XXX-XX-' . $this->lastFour;
    }

    /**
     * Get last 4 digits
     * 
     * @return string Last 4 digits of SSN
     */
    public function getLastFour(): string
    {
        return $this->lastFour;
    }

    /**
     * Get encrypted SSN (for storage)
     * 
     * @return string Encrypted SSN
     */
    public function getEncrypted(): string
    {
        return $this->encrypted;
    }

    /**
     * Get decrypted SSN (requires authorization)
     * 
     * WARNING: This should only be called when absolutely necessary
     * and access should be logged for HIPAA compliance.
     * 
     * @return string Decrypted SSN (9 digits)
     * @throws RuntimeException If decryption fails
     */
    public function getDecrypted(): string
    {
        return $this->decrypt();
    }

    /**
     * Get formatted decrypted SSN
     * 
     * WARNING: This should only be called when absolutely necessary
     * and access should be logged for HIPAA compliance.
     * 
     * @return string Formatted SSN (e.g., 123-45-6789)
     * @throws RuntimeException If decryption fails
     */
    public function getFormattedDecrypted(): string
    {
        $ssn = $this->decrypt();
        return substr($ssn, 0, 3) . '-' . substr($ssn, 3, 2) . '-' . substr($ssn, 5, 4);
    }

    /**
     * Verify if a given SSN matches this one
     * 
     * @param string $ssn SSN to verify
     * @return bool True if SSN matches
     */
    public function verify(string $ssn): bool
    {
        $normalized = $this->normalize($ssn);
        
        try {
            return hash_equals($this->decrypt(), $normalized);
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * String representation (always masked)
     * 
     * @return string Masked SSN
     */
    public function __toString(): string
    {
        return $this->getMasked();
    }

    /**
     * Check equality with another SSN
     * 
     * @param SSN $other Another SSN to compare
     * @return bool True if SSNs are equal
     */
    public function equals(SSN $other): bool
    {
        try {
            return hash_equals($this->decrypt(), $other->getDecrypted());
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Check if a string is a valid SSN format without throwing exception
     * 
     * @param string $ssn SSN to validate
     * @return bool True if valid format
     */
    public static function isValidFormat(string $ssn): bool
    {
        $normalized = preg_replace('/[^0-9]/', '', $ssn);
        
        if (strlen($normalized) !== 9) {
            return false;
        }
        
        // Basic format checks
        if ($normalized[0] === '9') return false;
        if (substr($normalized, 0, 3) === '000') return false;
        if (substr($normalized, 3, 2) === '00') return false;
        if (substr($normalized, 5, 4) === '0000') return false;
        
        return true;
    }

    /**
     * Serialize to safe array (encrypted only)
     * 
     * @return array{encrypted: string, last_four: string, masked: string}
     */
    public function toArray(): array
    {
        return [
            'encrypted' => $this->encrypted,
            'last_four' => $this->lastFour,
            'masked' => $this->getMasked(),
        ];
    }

    /**
     * Prevent serialization of SSN for security
     * 
     * @return array<string>
     */
    public function __sleep(): array
    {
        // Only allow encrypted value and last four to be serialized
        return ['encrypted', 'lastFour'];
    }

    /**
     * Debug info - hide sensitive data
     * 
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return [
            'masked' => $this->getMasked(),
            'lastFour' => $this->lastFour,
        ];
    }
}
