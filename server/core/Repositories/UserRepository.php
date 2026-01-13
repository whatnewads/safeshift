<?php
/**
 * User Repository
 * 
 * Handles all user-related database operations
 */

namespace App\Repositories;

use PDO;

class UserRepository extends BaseRepository
{
    protected string $table = 'user';
    protected string $primaryKey = 'user_id';
    
    /**
     * Find user by username
     * 
     * @param string $username
     * @return array|null
     */
    public function findByUsername(string $username): ?array
    {
        $sql = "SELECT user_id, username, email, password_hash, mfa_enabled, status,
                       created_at, last_login, login_attempts, account_locked_until
                FROM {$this->table}
                WHERE username = :username
                AND status = 'active'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['username' => $username]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Find user by email
     * 
     * @param string $email
     * @return array|null
     */
    public function findByEmail(string $email): ?array
    {
        $sql = "SELECT user_id, username, email, password_hash, mfa_enabled, status,
                       created_at, last_login, login_attempts, account_locked_until
                FROM {$this->table}
                WHERE email = :email
                AND status = 'active'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['email' => $email]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Update last login time
     * 
     * @param string $userId
     * @return bool
     */
    public function updateLastLogin(string $userId): bool
    {
        $sql = "UPDATE {$this->table}
                SET last_login = NOW(),
                    login_attempts = 0,
                    account_locked_until = NULL
                WHERE user_id = :user_id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['user_id' => $userId]);
    }
    
    /**
     * Get user's primary role
     * 
     * @param string $userId
     * @return array|null Returns role information or null
     */
    public function getUserPrimaryRole(string $userId): ?array
    {
        $sql = "SELECT r.role_id, r.name, r.slug, r.description
                FROM userrole ur
                JOIN role r ON ur.role_id = r.role_id
                WHERE ur.user_id = :user_id
                ORDER BY ur.user_role_id ASC
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Get all user roles
     * 
     * @param string $userId
     * @return array
     */
    public function getUserRoles(string $userId): array
    {
        $sql = "SELECT r.role_id, r.name, r.slug, r.description,
                       ur.user_role_id
                FROM userrole ur
                JOIN role r ON ur.role_id = r.role_id
                WHERE ur.user_id = :user_id
                ORDER BY ur.user_role_id ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Check if user has a specific role
     * 
     * @param string $userId
     * @param string $roleName Role name or slug
     * @return bool
     */
    public function hasRole(string $userId, string $roleName): bool
    {
        $sql = "SELECT COUNT(*) as count
                FROM userrole ur
                JOIN role r ON ur.role_id = r.role_id
                WHERE ur.user_id = :user_id
                AND (r.name = :role_name OR r.slug = :role_name)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'role_name' => $roleName
        ]);
        
        $result = $stmt->fetch();
        return ($result['count'] ?? 0) > 0;
    }
    
    /**
     * Increment failed login attempts
     * 
     * @param string $userId
     * @return bool
     */
    public function incrementFailedAttempts(string $userId): bool
    {
        $sql = "UPDATE {$this->table}
                SET login_attempts = COALESCE(login_attempts, 0) + 1
                WHERE user_id = :user_id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['user_id' => $userId]);
    }
    
    /**
     * Lock user account
     * 
     * @param string $userId
     * @param int $minutes Lock duration in minutes
     * @return bool
     */
    public function lockAccount(string $userId, int $minutes = 30): bool
    {
        $sql = "UPDATE {$this->table}
                SET account_locked_until = DATE_ADD(NOW(), INTERVAL :minutes MINUTE)
                WHERE user_id = :user_id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'user_id' => $userId,
            'minutes' => $minutes
        ]);
    }
    
    /**
     * Check if user account is locked
     * 
     * @param string $userId
     * @return bool
     */
    public function isLocked(string $userId): bool
    {
        $sql = "SELECT account_locked_until
                FROM {$this->table}
                WHERE user_id = :user_id
                AND account_locked_until > NOW()";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        return $stmt->fetch() !== false;
    }
    
    /**
     * Get user's dashboard URL based on primary role
     * 
     * @param string $userId
     * @return string
     */
    public function getUserDashboardUrl(string $userId): string
    {
        $role = $this->getUserPrimaryRole($userId);
        
        if (!$role) {
            return '/dashboard';
        }
        
        // Map role slugs to dashboard URLs
        $dashboardMap = [
            '1clinician' => '/dashboards/1clinician/',
            'pclinician' => '/dashboards/pclinician/',
            'dclinician' => '/dashboards/dclinician/',
            'cadmin' => '/dashboards/cadmin/',
            'tadmin' => '/dashboards/tadmin/',
            'employee' => '/dashboards/employee/',
            'employer' => '/dashboards/employer/'
        ];
        
        return $dashboardMap[$role['slug']] ?? '/dashboard';
    }
    
    /**
     * Create new user
     * 
     * @param array $data
     * @return string|false User ID or false on failure
     */
    public function createUser(array $data)
    {
        $userId = $this->generateUuid();
        
        $userData = [
            'user_id' => $userId,
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => $data['password_hash'],
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'mfa_enabled' => $data['mfa_enabled'] ?? 1,
            'status' => $data['status'] ?? 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $data['created_by'] ?? null
        ];
        
        if ($this->insert($userData)) {
            return $userId;
        }
        
        return false;
    }
    
    /**
     * Update user password
     * 
     * @param string $userId
     * @param string $passwordHash
     * @return bool
     */
    public function updatePassword(string $userId, string $passwordHash): bool
    {
        return $this->update($userId, [
            'password_hash' => $passwordHash,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Get users with expiring passwords
     * 
     * @param int $daysBeforeExpiry
     * @return array
     */
    public function getUsersWithExpiringPasswords(int $daysBeforeExpiry = 7): array
    {
        $sql = "SELECT user_id, username, email, updated_at,
                       DATEDIFF(DATE_ADD(updated_at, INTERVAL :expiry_days DAY), NOW()) as days_remaining
                FROM {$this->table}
                WHERE status = 'active'
                AND updated_at IS NOT NULL
                AND DATEDIFF(DATE_ADD(updated_at, INTERVAL :expiry_days DAY), NOW()) BETWEEN 0 AND :warning_days
                ORDER BY days_remaining ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'expiry_days' => PASSWORD_EXPIRY_DAYS ?? 90,
            'warning_days' => $daysBeforeExpiry
        ]);
        
        return $stmt->fetchAll();
    }
}