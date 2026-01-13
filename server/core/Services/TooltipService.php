<?php
/**
 * Tooltip Service
 * 
 * Manages contextual tooltips for form fields and UI elements
 * Feature 1.3: Tooltip-based Guided Interface
 */

namespace Core\Services;

use PDO;
use Exception;

class TooltipService extends BaseService
{    /**
     * Constructor
     * @param PDO $db Database connection
     */
    public function __construct(\PDO $db = null)
    {
        parent::__construct();
        $this->db = $db ?: \App\db\pdo();
    }
    
    /**
     * Get tooltips for a specific page
     * 
     * @param string $page_identifier Page or form identifier
     * @param string $user_id User ID for preferences and role filtering
     * @return array
     */
    public function getPageTooltips(string $page_identifier, string $user_id): array
    {
        try {
            // Get user preferences
            $preferences = $this->getUserPreferences($user_id);
            
            if (!$preferences['tooltips_enabled']) {
                return [];
            }
            
            // Get user role
            $userRole = $this->getUserRole($user_id);
            
            // Get tooltips for the page
            $sql = "SELECT tooltip_id, field_identifier, tooltip_text, tooltip_type
                    FROM ui_tooltips
                    WHERE status = 'active'
                    AND field_identifier LIKE :page_pattern
                    AND (role_filter = 'all' OR FIND_IN_SET(:user_role, role_filter) > 0)
                    ORDER BY field_identifier";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'page_pattern' => $page_identifier . '.%',
                'user_role' => $userRole
            ]);
            
            $tooltips = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Filter out dismissed tooltips
            $dismissed = json_decode($preferences['dismissed_tooltips'] ?? '[]', true) ?: [];
            $tooltips = array_filter($tooltips, function($tooltip) use ($dismissed) {
                return !in_array($tooltip['tooltip_id'], $dismissed);
            });
            
            return array_values($tooltips);
            
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to get page tooltips',
                'error' => $e->getMessage(),
                'page' => $page_identifier
            ]);
            return [];
        }
    }
    
    /**
     * Get user tooltip preferences
     * 
     * @param string $user_id
     * @return array
     */
    public function getUserPreferences(string $user_id): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT tooltips_enabled, dismissed_tooltips
                FROM user_tooltip_preferences
                WHERE user_id = :user_id
            ");
            $stmt->execute(['user_id' => $user_id]);
            $preferences = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$preferences) {
                // Create default preferences
                $this->createDefaultPreferences($user_id);
                return [
                    'tooltips_enabled' => true,
                    'dismissed_tooltips' => '[]'
                ];
            }
            
            return $preferences;
            
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to get user tooltip preferences',
                'error' => $e->getMessage(),
                'user_id' => $user_id
            ]);
            return [
                'tooltips_enabled' => true,
                'dismissed_tooltips' => '[]'
            ];
        }
    }
    
    /**
     * Update user tooltip preferences
     * 
     * @param string $user_id
     * @param array $preferences
     * @return bool
     */
    public function updateUserPreferences(string $user_id, array $preferences): bool
    {
        try {
            $sql = "INSERT INTO user_tooltip_preferences (user_id, tooltips_enabled, dismissed_tooltips)
                    VALUES (:user_id, :tooltips_enabled, :dismissed_tooltips)
                    ON DUPLICATE KEY UPDATE 
                    tooltips_enabled = VALUES(tooltips_enabled),
                    dismissed_tooltips = VALUES(dismissed_tooltips)";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'user_id' => $user_id,
                'tooltips_enabled' => $preferences['tooltips_enabled'] ?? true,
                'dismissed_tooltips' => json_encode($preferences['dismissed_tooltips'] ?? [])
            ]);
            
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to update tooltip preferences',
                'error' => $e->getMessage(),
                'user_id' => $user_id
            ]);
            return false;
        }
    }
    
    /**
     * Dismiss a tooltip for a user
     * 
     * @param string $user_id
     * @param string $tooltip_id
     * @return bool
     */
    public function dismissTooltip(string $user_id, string $tooltip_id): bool
    {
        try {
            $preferences = $this->getUserPreferences($user_id);
            $dismissed = json_decode($preferences['dismissed_tooltips'] ?? '[]', true) ?: [];
            
            if (!in_array($tooltip_id, $dismissed)) {
                $dismissed[] = $tooltip_id;
                $preferences['dismissed_tooltips'] = $dismissed;
                
                // Track dismissal for analytics
                $this->trackTooltipDismissal($tooltip_id, $user_id);
                
                return $this->updateUserPreferences($user_id, $preferences);
            }
            
            return true;
            
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to dismiss tooltip',
                'error' => $e->getMessage(),
                'tooltip_id' => $tooltip_id
            ]);
            return false;
        }
    }
    
    /**
     * Create or update a tooltip (admin function)
     * 
     * @param array $data
     * @return string|bool Tooltip ID on success, false on failure
     */
    public function upsertTooltip(array $data)
    {
        try {
            if (isset($data['tooltip_id'])) {
                // Update existing
                $sql = "UPDATE ui_tooltips SET
                        field_identifier = :field_identifier,
                        tooltip_text = :tooltip_text,
                        tooltip_type = :tooltip_type,
                        role_filter = :role_filter,
                        status = :status,
                        updated_at = NOW()
                        WHERE tooltip_id = :tooltip_id";
                
                $stmt = $this->db->prepare($sql);
                $success = $stmt->execute([
                    'tooltip_id' => $data['tooltip_id'],
                    'field_identifier' => $data['field_identifier'],
                    'tooltip_text' => $data['tooltip_text'],
                    'tooltip_type' => $data['tooltip_type'] ?? 'info',
                    'role_filter' => $data['role_filter'] ?? 'all',
                    'status' => $data['status'] ?? 'active'
                ]);
                
                return $success ? $data['tooltip_id'] : false;
            } else {
                // Create new
                $sql = "INSERT INTO ui_tooltips 
                        (tooltip_id, field_identifier, tooltip_text, tooltip_type, role_filter, status)
                        VALUES (UUID(), :field_identifier, :tooltip_text, :tooltip_type, :role_filter, :status)";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'field_identifier' => $data['field_identifier'],
                    'tooltip_text' => $data['tooltip_text'],
                    'tooltip_type' => $data['tooltip_type'] ?? 'info',
                    'role_filter' => $data['role_filter'] ?? 'all',
                    'status' => $data['status'] ?? 'active'
                ]);
                
                // Get the new ID
                $stmt = $this->db->prepare("
                    SELECT tooltip_id FROM ui_tooltips 
                    WHERE field_identifier = :field_identifier
                    ORDER BY created_at DESC LIMIT 1
                ");
                $stmt->execute(['field_identifier' => $data['field_identifier']]);
                return $stmt->fetchColumn();
            }
            
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to upsert tooltip',
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return false;
        }
    }
    
    /**
     * Get all tooltips (admin view)
     * 
     * @param array $filters
     * @return array
     */
    public function getAllTooltips(array $filters = []): array
    {
        try {
            $sql = "SELECT * FROM ui_tooltips WHERE 1=1";
            $params = [];
            
            if (!empty($filters['status'])) {
                $sql .= " AND status = :status";
                $params['status'] = $filters['status'];
            }
            
            if (!empty($filters['type'])) {
                $sql .= " AND tooltip_type = :type";
                $params['type'] = $filters['type'];
            }
            
            if (!empty($filters['search'])) {
                $sql .= " AND (field_identifier LIKE :search OR tooltip_text LIKE :search)";
                $params['search'] = '%' . $filters['search'] . '%';
            }
            
            $sql .= " ORDER BY field_identifier";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to get all tooltips',
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Get tooltip analytics
     * 
     * @return array
     */
    public function getTooltipAnalytics(): array
    {
        try {
            // Most dismissed tooltips
            $sql = "SELECT t.field_identifier, t.tooltip_text, 
                           COUNT(DISTINCT utp.user_id) as dismissal_count
                    FROM ui_tooltips t
                    JOIN user_tooltip_preferences utp ON JSON_CONTAINS(utp.dismissed_tooltips, JSON_QUOTE(t.tooltip_id))
                    GROUP BY t.tooltip_id
                    ORDER BY dismissal_count DESC
                    LIMIT 10";
            
            $stmt = $this->db->query($sql);
            $mostDismissed = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // User engagement stats
            $sql = "SELECT 
                    COUNT(DISTINCT CASE WHEN tooltips_enabled = 1 THEN user_id END) as enabled_count,
                    COUNT(DISTINCT CASE WHEN tooltips_enabled = 0 THEN user_id END) as disabled_count,
                    COUNT(DISTINCT user_id) as total_users
                    FROM user_tooltip_preferences";
            
            $stmt = $this->db->query($sql);
            $engagement = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'most_dismissed' => $mostDismissed,
                'engagement' => $engagement,
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to get tooltip analytics',
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Create default preferences for a user
     * 
     * @param string $user_id
     * @return void
     */
    private function createDefaultPreferences(string $user_id): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT IGNORE INTO user_tooltip_preferences (user_id, tooltips_enabled)
                VALUES (:user_id, 1)
            ");
            $stmt->execute(['user_id' => $user_id]);
        } catch (Exception $e) {
            // Silent fail
        }
    }
    
    /**
     * Get user role
     * 
     * @param string $user_id
     * @return string
     */
    private function getUserRole(string $user_id): string
    {
        try {
            $stmt = $this->db->prepare("
                SELECT r.slug
                FROM user u
                JOIN userrole ur ON u.user_id = ur.user_id
                JOIN role r ON ur.role_id = r.role_id
                WHERE u.user_id = :user_id
                LIMIT 1
            ");
            $stmt->execute(['user_id' => $user_id]);
            return $stmt->fetchColumn() ?: 'default';
        } catch (Exception $e) {
            return 'default';
        }
    }
    
    /**
     * Track tooltip dismissal for analytics
     * 
     * @param string $tooltip_id
     * @param string $user_id
     * @return void
     */
    private function trackTooltipDismissal(string $tooltip_id, string $user_id): void
    {
        try {
            \App\log\file_log('audit', [
                'action' => 'tooltip_dismissed',
                'tooltip_id' => $tooltip_id,
                'user_id' => $user_id,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            // Silent fail
        }
    }
}