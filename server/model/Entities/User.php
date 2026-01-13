<?php
/**
 * User.php - User Entity for SafeShift EHR
 * 
 * Represents a system user with proper encapsulation and security.
 * Password hashes are never exposed externally.
 * 
 * @package    SafeShift\Model\Entities
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Model\Entities;

use Model\Interfaces\EntityInterface;
use Model\ValueObjects\Email;
use Model\ValueObjects\UUID;
use DateTimeInterface;
use DateTimeImmutable;

/**
 * User entity
 * 
 * Represents a user in the SafeShift EHR system.
 * Implements security best practices by never exposing password hashes.
 */
class User implements EntityInterface
{
    /** @var string|null User unique identifier (UUID) */
    protected ?string $userId;
    
    /** @var string Username */
    protected string $username;
    
    /** @var Email User email */
    protected Email $email;
    
    /** @var string Password hash (never exposed) */
    protected string $passwordHash;
    
    /** @var string User's first name */
    protected string $firstName;
    
    /** @var string User's last name */
    protected string $lastName;
    
    /** @var string User role slug */
    protected string $role;
    
    /** @var string|null Clinic ID this user belongs to */
    protected ?string $clinicId;
    
    /** @var bool Two-factor authentication enabled */
    protected bool $twoFactorEnabled;
    
    /** @var string|null Two-factor secret (encrypted) */
    protected ?string $twoFactorSecret;
    
    /** @var bool User active status */
    protected bool $activeStatus;
    
    /** @var DateTimeInterface|null Last login timestamp */
    protected ?DateTimeInterface $lastLoginAt;
    
    /** @var string|null Last login IP address */
    protected ?string $lastLoginIp;
    
    /** @var int Failed login attempts */
    protected int $failedLoginAttempts;
    
    /** @var DateTimeInterface|null Account lockout until timestamp */
    protected ?DateTimeInterface $lockedUntil;
    
    /** @var bool Password reset required flag */
    protected bool $passwordResetRequired;
    
    /** @var DateTimeInterface|null Password last changed timestamp */
    protected ?DateTimeInterface $passwordChangedAt;
    
    /** @var DateTimeInterface|null Entity creation timestamp */
    protected ?DateTimeInterface $createdAt;
    
    /** @var DateTimeInterface|null Entity update timestamp */
    protected ?DateTimeInterface $updatedAt;

    /** User role constants */
    public const ROLE_CLINICIAN = '1clinician';
    public const ROLE_PROVIDER_CLINICIAN = 'pclinician';
    public const ROLE_DIRECTOR_CLINICIAN = 'dclinician';
    public const ROLE_CLINIC_ADMIN = 'cadmin';
    public const ROLE_TENANT_ADMIN = 'tadmin';
    public const ROLE_EMPLOYEE = 'employee';
    public const ROLE_EMPLOYER = 'employer';
    public const ROLE_CUSTOM = 'custom';

    /**
     * Create a new User instance
     * 
     * @param string $username Username
     * @param Email $email User email
     * @param string $firstName First name
     * @param string $lastName Last name
     * @param string $role User role
     */
    public function __construct(
        string $username,
        Email $email,
        string $firstName,
        string $lastName,
        string $role = self::ROLE_CLINICIAN
    ) {
        $this->userId = null;
        $this->username = $username;
        $this->email = $email;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->role = $role;
        $this->clinicId = null;
        $this->passwordHash = '';
        $this->twoFactorEnabled = false;
        $this->twoFactorSecret = null;
        $this->activeStatus = true;
        $this->lastLoginAt = null;
        $this->lastLoginIp = null;
        $this->failedLoginAttempts = 0;
        $this->lockedUntil = null;
        $this->passwordResetRequired = false;
        $this->passwordChangedAt = null;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Get the user ID
     * 
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->userId;
    }

    /**
     * Set the user ID
     * 
     * @param string $userId
     * @return self
     */
    public function setId(string $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * Get username
     * 
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Set username
     * 
     * @param string $username
     * @return self
     */
    public function setUsername(string $username): self
    {
        $this->username = $username;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get email
     * 
     * @return Email
     */
    public function getEmail(): Email
    {
        return $this->email;
    }

    /**
     * Set email
     * 
     * @param Email $email
     * @return self
     */
    public function setEmail(Email $email): self
    {
        $this->email = $email;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get first name
     * 
     * @return string
     */
    public function getFirstName(): string
    {
        return $this->firstName;
    }

    /**
     * Set first name
     * 
     * @param string $firstName
     * @return self
     */
    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get last name
     * 
     * @return string
     */
    public function getLastName(): string
    {
        return $this->lastName;
    }

    /**
     * Set last name
     * 
     * @param string $lastName
     * @return self
     */
    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get full name
     * 
     * @return string
     */
    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    /**
     * Get user role
     * 
     * @return string
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * Set user role
     * 
     * @param string $role
     * @return self
     */
    public function setRole(string $role): self
    {
        $this->role = $role;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get clinic ID
     * 
     * @return string|null
     */
    public function getClinicId(): ?string
    {
        return $this->clinicId;
    }

    /**
     * Set clinic ID
     * 
     * @param string|null $clinicId
     * @return self
     */
    public function setClinicId(?string $clinicId): self
    {
        $this->clinicId = $clinicId;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Set password (hashes automatically)
     * 
     * @param string $password Plain text password
     * @return self
     */
    public function setPassword(string $password): self
    {
        $this->passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->passwordChangedAt = new DateTimeImmutable();
        $this->passwordResetRequired = false;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Verify password
     * 
     * @param string $password Password to verify
     * @return bool True if password matches
     */
    public function verifyPassword(string $password): bool
    {
        if (empty($this->passwordHash)) {
            return false;
        }
        return password_verify($password, $this->passwordHash);
    }

    /**
     * Check if password needs rehash
     * 
     * @return bool True if password hash needs updating
     */
    public function passwordNeedsRehash(): bool
    {
        return password_needs_rehash($this->passwordHash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Check if two-factor authentication is enabled
     * 
     * @return bool
     */
    public function isTwoFactorEnabled(): bool
    {
        return $this->twoFactorEnabled;
    }

    /**
     * Enable two-factor authentication
     * 
     * @param string $secret Encrypted 2FA secret
     * @return self
     */
    public function enableTwoFactor(string $secret): self
    {
        $this->twoFactorEnabled = true;
        $this->twoFactorSecret = $secret;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Disable two-factor authentication
     * 
     * @return self
     */
    public function disableTwoFactor(): self
    {
        $this->twoFactorEnabled = false;
        $this->twoFactorSecret = null;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Check if user is active
     * 
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->activeStatus;
    }

    /**
     * Activate user
     * 
     * @return self
     */
    public function activate(): self
    {
        $this->activeStatus = true;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Deactivate user
     * 
     * @return self
     */
    public function deactivate(): self
    {
        $this->activeStatus = false;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Check if user account is locked
     * 
     * @return bool
     */
    public function isLocked(): bool
    {
        if ($this->lockedUntil === null) {
            return false;
        }
        return $this->lockedUntil > new DateTimeImmutable();
    }

    /**
     * Lock user account
     * 
     * @param int $minutes Duration in minutes
     * @return self
     */
    public function lock(int $minutes = 15): self
    {
        $this->lockedUntil = (new DateTimeImmutable())->modify("+{$minutes} minutes");
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Unlock user account
     * 
     * @return self
     */
    public function unlock(): self
    {
        $this->lockedUntil = null;
        $this->failedLoginAttempts = 0;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Record failed login attempt
     * 
     * @param int $maxAttempts Maximum attempts before lockout
     * @param int $lockoutMinutes Lockout duration in minutes
     * @return self
     */
    public function recordFailedLogin(int $maxAttempts = 5, int $lockoutMinutes = 15): self
    {
        $this->failedLoginAttempts++;
        
        if ($this->failedLoginAttempts >= $maxAttempts) {
            $this->lock($lockoutMinutes);
        }
        
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Record successful login
     * 
     * @param string|null $ipAddress Client IP address
     * @return self
     */
    public function recordSuccessfulLogin(?string $ipAddress = null): self
    {
        $this->lastLoginAt = new DateTimeImmutable();
        $this->lastLoginIp = $ipAddress;
        $this->failedLoginAttempts = 0;
        $this->lockedUntil = null;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get last login timestamp
     * 
     * @return DateTimeInterface|null
     */
    public function getLastLoginAt(): ?DateTimeInterface
    {
        return $this->lastLoginAt;
    }

    /**
     * Check if password reset is required
     * 
     * @return bool
     */
    public function isPasswordResetRequired(): bool
    {
        return $this->passwordResetRequired;
    }

    /**
     * Require password reset
     * 
     * @return self
     */
    public function requirePasswordReset(): self
    {
        $this->passwordResetRequired = true;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Check if entity has been persisted
     * 
     * @return bool
     */
    public function isPersisted(): bool
    {
        return $this->userId !== null;
    }

    /**
     * Get creation timestamp
     * 
     * @return DateTimeInterface|null
     */
    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Get update timestamp
     * 
     * @return DateTimeInterface|null
     */
    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * Check if user has specific role
     * 
     * @param string $role Role to check
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user is a clinician (any type)
     * 
     * @return bool
     */
    public function isClinician(): bool
    {
        return in_array($this->role, [
            self::ROLE_CLINICIAN,
            self::ROLE_PROVIDER_CLINICIAN,
            self::ROLE_DIRECTOR_CLINICIAN,
        ], true);
    }

    /**
     * Check if user is an admin (any type)
     * 
     * @return bool
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, [
            self::ROLE_CLINIC_ADMIN,
            self::ROLE_TENANT_ADMIN,
        ], true);
    }

    /**
     * Validate entity data
     * 
     * @return array<string, string>
     */
    public function validate(): array
    {
        $errors = [];
        
        if (empty($this->username)) {
            $errors['username'] = 'Username is required';
        } elseif (strlen($this->username) < 3) {
            $errors['username'] = 'Username must be at least 3 characters';
        }
        
        if (empty($this->firstName)) {
            $errors['first_name'] = 'First name is required';
        }
        
        if (empty($this->lastName)) {
            $errors['last_name'] = 'Last name is required';
        }
        
        if (empty($this->role)) {
            $errors['role'] = 'Role is required';
        }
        
        return $errors;
    }

    /**
     * Convert to array (includes all data except password)
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'username' => $this->username,
            'email' => $this->email->getValue(),
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->getFullName(),
            'role' => $this->role,
            'clinic_id' => $this->clinicId,
            'two_factor_enabled' => $this->twoFactorEnabled,
            'active_status' => $this->activeStatus,
            'last_login_at' => $this->lastLoginAt?->format('Y-m-d H:i:s'),
            'password_reset_required' => $this->passwordResetRequired,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Convert to safe array (for external exposure)
     * 
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'user_id' => $this->userId,
            'username' => $this->username,
            'email' => $this->email->getMasked(),
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->getFullName(),
            'role' => $this->role,
            'two_factor_enabled' => $this->twoFactorEnabled,
            'active_status' => $this->activeStatus,
        ];
    }

    /**
     * Create from array data
     * 
     * @param array<string, mixed> $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        $email = $data['email'] instanceof Email 
            ? $data['email'] 
            : new Email($data['email']);
        
        $user = new static(
            $data['username'],
            $email,
            $data['first_name'] ?? '',
            $data['last_name'] ?? '',
            $data['role'] ?? self::ROLE_CLINICIAN
        );
        
        if (isset($data['user_id'])) {
            $user->userId = $data['user_id'];
        }
        
        if (isset($data['password_hash'])) {
            $user->passwordHash = $data['password_hash'];
        }
        
        if (isset($data['clinic_id'])) {
            $user->clinicId = $data['clinic_id'];
        }
        
        if (isset($data['two_factor_enabled'])) {
            $user->twoFactorEnabled = (bool) $data['two_factor_enabled'];
        }
        
        if (isset($data['two_factor_secret'])) {
            $user->twoFactorSecret = $data['two_factor_secret'];
        }
        
        if (isset($data['active_status'])) {
            $user->activeStatus = (bool) $data['active_status'];
        }
        
        if (isset($data['last_login_at'])) {
            $user->lastLoginAt = new DateTimeImmutable($data['last_login_at']);
        }
        
        if (isset($data['last_login_ip'])) {
            $user->lastLoginIp = $data['last_login_ip'];
        }
        
        if (isset($data['failed_login_attempts'])) {
            $user->failedLoginAttempts = (int) $data['failed_login_attempts'];
        }
        
        if (isset($data['locked_until'])) {
            $user->lockedUntil = new DateTimeImmutable($data['locked_until']);
        }
        
        if (isset($data['password_reset_required'])) {
            $user->passwordResetRequired = (bool) $data['password_reset_required'];
        }
        
        if (isset($data['password_changed_at'])) {
            $user->passwordChangedAt = new DateTimeImmutable($data['password_changed_at']);
        }
        
        if (isset($data['created_at'])) {
            $user->createdAt = new DateTimeImmutable($data['created_at']);
        }
        
        if (isset($data['updated_at'])) {
            $user->updatedAt = new DateTimeImmutable($data['updated_at']);
        }
        
        return $user;
    }
}
