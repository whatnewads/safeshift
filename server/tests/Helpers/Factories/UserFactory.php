<?php
/**
 * UserFactory - Test Data Factory for Users
 * 
 * Creates mock user data for testing purposes.
 * 
 * @package SafeShift\Tests\Helpers\Factories
 */

declare(strict_types=1);

namespace Tests\Helpers\Factories;

/**
 * Factory for generating test user data
 */
class UserFactory
{
    /** @var array<string> Valid backend roles */
    private const VALID_ROLES = [
        'Admin',
        'Manager',
        'pclinician',
        'dclinician',
        '1clinician',
        'QA',
        'cadmin',
        'tadmin',
        'PrivacyOfficer',
        'SecurityOfficer',
    ];

    /** @var array<string, string> Role to display name mapping */
    private const ROLE_DISPLAY_NAMES = [
        'Admin' => 'System Administrator',
        'Manager' => 'Manager',
        'pclinician' => 'Clinical Provider',
        'dclinician' => 'Drug Screen Technician',
        '1clinician' => 'Intake Clinician',
        'QA' => 'Quality Assurance',
        'cadmin' => 'Clinic Administrator',
        'tadmin' => 'Technical Administrator',
        'PrivacyOfficer' => 'Privacy Officer',
        'SecurityOfficer' => 'Security Officer',
    ];

    /** @var array<string> First names */
    private const FIRST_NAMES = [
        'Alice', 'Bob', 'Carol', 'Dave', 'Eve', 'Frank',
        'Grace', 'Henry', 'Ivy', 'Jack', 'Kate', 'Leo'
    ];

    /** @var array<string> Last names */
    private const LAST_NAMES = [
        'Adams', 'Baker', 'Clark', 'Davis', 'Evans', 'Foster',
        'Green', 'Harris', 'Irving', 'Jones', 'King', 'Lewis'
    ];

    /**
     * Create a basic user data array
     * 
     * @param string $role
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function make(string $role = 'pclinician', array $overrides = []): array
    {
        $firstName = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
        $lastName = self::LAST_NAMES[array_rand(self::LAST_NAMES)];
        $username = strtolower($firstName . '.' . $lastName . random_int(1, 99));
        
        $defaults = [
            'user_id' => self::generateUuid(),
            'username' => $username,
            'email' => $username . '@test.safeshift.com',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'role' => $role,
            'primary_role' => $role,
            'display_role' => self::ROLE_DISPLAY_NAMES[$role] ?? ucfirst($role),
            'clinic_id' => self::generateUuid(),
            'status' => 'active',
            'two_factor_enabled' => false,
            'email_verified' => true,
            'phone' => self::generatePhone(),
            'last_login' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'password_expires_at' => date('Y-m-d H:i:s', strtotime('+90 days')),
            'failed_login_attempts' => 0,
            'lockout_until' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Create admin user
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeAdmin(array $overrides = []): array
    {
        return self::make('Admin', array_merge([
            'username' => 'admin_test_' . random_int(1, 999),
            'email' => 'admin_test@safeshift.com',
        ], $overrides));
    }

    /**
     * Create super admin user
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeSuperAdmin(array $overrides = []): array
    {
        return self::makeAdmin($overrides);
    }

    /**
     * Create manager user
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeManager(array $overrides = []): array
    {
        return self::make('Manager', $overrides);
    }

    /**
     * Create clinical provider (pclinician)
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeClinician(array $overrides = []): array
    {
        return self::make('pclinician', $overrides);
    }

    /**
     * Create drug screen technician (dclinician)
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeTechnician(array $overrides = []): array
    {
        return self::make('dclinician', $overrides);
    }

    /**
     * Create intake clinician (1clinician)
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeIntakeClinician(array $overrides = []): array
    {
        return self::make('1clinician', $overrides);
    }

    /**
     * Create QA user
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeQA(array $overrides = []): array
    {
        return self::make('QA', $overrides);
    }

    /**
     * Create clinic admin
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeClinicAdmin(array $overrides = []): array
    {
        return self::make('cadmin', $overrides);
    }

    /**
     * Create technical admin
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeTechnicalAdmin(array $overrides = []): array
    {
        return self::make('tadmin', $overrides);
    }

    /**
     * Create privacy officer
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makePrivacyOfficer(array $overrides = []): array
    {
        return self::make('PrivacyOfficer', $overrides);
    }

    /**
     * Create security officer
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeSecurityOfficer(array $overrides = []): array
    {
        return self::make('SecurityOfficer', $overrides);
    }

    /**
     * Create inactive user
     * 
     * @param string $role
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeInactive(string $role = 'pclinician', array $overrides = []): array
    {
        return self::make($role, array_merge([
            'status' => 'inactive',
        ], $overrides));
    }

    /**
     * Create locked user
     * 
     * @param string $role
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeLocked(string $role = 'pclinician', array $overrides = []): array
    {
        return self::make($role, array_merge([
            'status' => 'locked',
            'failed_login_attempts' => 5,
            'lockout_until' => date('Y-m-d H:i:s', strtotime('+30 minutes')),
        ], $overrides));
    }

    /**
     * Create user with 2FA enabled
     * 
     * @param string $role
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeWith2FA(string $role = 'pclinician', array $overrides = []): array
    {
        return self::make($role, array_merge([
            'two_factor_enabled' => true,
        ], $overrides));
    }

    /**
     * Create user with expired password
     * 
     * @param string $role
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeWithExpiredPassword(string $role = 'pclinician', array $overrides = []): array
    {
        return self::make($role, array_merge([
            'password_expires_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ], $overrides));
    }

    /**
     * Create user without email verification
     * 
     * @param string $role
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeUnverified(string $role = 'pclinician', array $overrides = []): array
    {
        return self::make($role, array_merge([
            'email_verified' => false,
        ], $overrides));
    }

    /**
     * Create multiple users
     * 
     * @param int $count
     * @param string $role
     * @param array<string, mixed> $overrides
     * @return array<int, array<string, mixed>>
     */
    public static function makeMany(int $count, string $role = 'pclinician', array $overrides = []): array
    {
        $users = [];
        for ($i = 0; $i < $count; $i++) {
            $users[] = self::make($role, $overrides);
        }
        return $users;
    }

    /**
     * Create users with all roles
     * 
     * @return array<string, array<string, mixed>>
     */
    public static function makeAllRoles(): array
    {
        $users = [];
        foreach (self::VALID_ROLES as $role) {
            $users[$role] = self::make($role);
        }
        return $users;
    }

    /**
     * Create session data for user
     * 
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public static function makeSession(array $user): array
    {
        return [
            'user' => $user,
            'last_activity' => time(),
            'csrf_token' => bin2hex(random_bytes(32)),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit Test Agent',
        ];
    }

    /**
     * Get valid roles
     * 
     * @return array<string>
     */
    public static function getValidRoles(): array
    {
        return self::VALID_ROLES;
    }

    /**
     * Generate phone number for testing
     * 
     * @return string
     */
    private static function generatePhone(): string
    {
        return sprintf(
            '(%03d) %03d-%04d',
            random_int(200, 999),
            random_int(200, 999),
            random_int(1000, 9999)
        );
    }

    /**
     * Generate UUID
     */
    private static function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}
