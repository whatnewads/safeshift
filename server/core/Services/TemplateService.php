<?php
/**
 * Template Service
 * 
 * Handles chart template management for common encounter types
 * Feature 1.2: Smart Template Loader
 */

namespace Core\Services;

use PDO;
use Exception;

class TemplateService extends BaseService
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
     * Create a new template
     * 
     * @param array $data Template data
     * @return string Template ID
     * @throws Exception
     */
    public function createTemplate(array $data): string
    {
        try {
            // Use MySQL UUID() function
            $sql = "INSERT INTO chart_templates
                    (template_id, template_name, description, encounter_type,
                     template_data, created_by, visibility, status, version)
                    VALUES (UUID(), :template_name, :description, :encounter_type,
                            :template_data, :created_by, :visibility, :status, :version)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'template_name' => $data['template_name'],
                'description' => $data['description'] ?? null,
                'encounter_type' => $data['encounter_type'] ?? null,
                'template_data' => json_encode($data['template_data']),
                'created_by' => $data['created_by'],
                'visibility' => $data['visibility'] ?? 'personal',
                'status' => $data['status'] ?? 'active',
                'version' => 1
            ]);
            
            // Get the last inserted template ID
            $stmt = $this->db->prepare("
                SELECT template_id FROM chart_templates
                WHERE created_by = :created_by
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute(['created_by' => $data['created_by']]);
            $template_id = $stmt->fetchColumn();
            
            // Log template action
            $this->logTemplateAction('create', $template_id, $data['created_by']);
            
            return $template_id;
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to create template',
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }
    
    /**
     * Get templates for a user
     * 
     * @param string $user_id
     * @param array $filters Optional filters
     * @return array
     */
    public function getUserTemplates(string $user_id, array $filters = []): array
    {
        try {
            $sql = "SELECT t.*, u.username as created_by_name
                    FROM chart_templates t
                    LEFT JOIN user u ON t.created_by = u.user_id
                    WHERE (t.created_by = :user_id OR (t.visibility = 'organization' AND t.status = 'active'))
                    AND t.status != 'archived'";
            
            $params = ['user_id' => $user_id];
            
            // Apply filters
            if (!empty($filters['encounter_type'])) {
                $sql .= " AND t.encounter_type = :encounter_type";
                $params['encounter_type'] = $filters['encounter_type'];
            }
            
            if (!empty($filters['search'])) {
                $sql .= " AND (t.template_name LIKE :search OR t.description LIKE :search)";
                $params['search'] = '%' . $filters['search'] . '%';
            }
            
            $sql .= " ORDER BY t.visibility DESC, t.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Parse JSON data
            foreach ($templates as &$template) {
                $template['template_data'] = json_decode($template['template_data'], true);
            }
            
            return $templates;
        } catch (Exception $e) {
            $this->logError('Failed to get user templates', [
                'error' => $e->getMessage(),
                'user_id' => $user_id
            ]);
            return [];
        }
    }
    
    /**
     * Get a specific template
     * 
     * @param string $template_id
     * @param string $user_id For permission check
     * @return array|null
     */
    public function getTemplate(string $template_id, string $user_id): ?array
    {
        try {
            $sql = "SELECT t.*, u.username as created_by_name
                    FROM chart_templates t
                    LEFT JOIN user u ON t.created_by = u.user_id
                    WHERE t.template_id = :template_id
                    AND (t.created_by = :user_id OR t.visibility = 'organization')";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'template_id' => $template_id,
                'user_id' => $user_id
            ]);
            
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($template) {
                $template['template_data'] = json_decode($template['template_data'], true);
                return $template;
            }
            
            return null;
        } catch (Exception $e) {
            $this->logError('Failed to get template', [
                'error' => $e->getMessage(),
                'template_id' => $template_id
            ]);
            return null;
        }
    }
    
    /**
     * Update a template
     * 
     * @param string $template_id
     * @param array $data
     * @param string $user_id For permission check
     * @return bool
     */
    public function updateTemplate(string $template_id, array $data, string $user_id): bool
    {
        try {
            // Check ownership
            $template = $this->getTemplate($template_id, $user_id);
            if (!$template || $template['created_by'] !== $user_id) {
                throw new Exception('Unauthorized to update this template');
            }
            
            // Increment version
            $new_version = $template['version'] + 1;
            
            $sql = "UPDATE chart_templates 
                    SET template_name = :template_name,
                        description = :description,
                        encounter_type = :encounter_type,
                        template_data = :template_data,
                        version = :version,
                        updated_at = NOW()
                    WHERE template_id = :template_id";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                'template_id' => $template_id,
                'template_name' => $data['template_name'] ?? $template['template_name'],
                'description' => $data['description'] ?? $template['description'],
                'encounter_type' => $data['encounter_type'] ?? $template['encounter_type'],
                'template_data' => json_encode($data['template_data'] ?? $template['template_data']),
                'version' => $new_version
            ]);
            
            if ($result) {
                $this->logTemplateAction('update', $template_id, $user_id, [
                    'version' => $new_version
                ]);
            }
            
            return $result;
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to update template',
                'error' => $e->getMessage(),
                'template_id' => $template_id
            ]);
            return false;
        }
    }
    
    /**
     * Archive a template (soft delete)
     * 
     * @param string $template_id
     * @param string $user_id For permission check
     * @return bool
     */
    public function archiveTemplate(string $template_id, string $user_id): bool
    {
        try {
            // Check ownership
            $template = $this->getTemplate($template_id, $user_id);
            if (!$template || $template['created_by'] !== $user_id) {
                throw new Exception('Unauthorized to archive this template');
            }
            
            $sql = "UPDATE chart_templates 
                    SET status = 'archived', updated_at = NOW()
                    WHERE template_id = :template_id";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute(['template_id' => $template_id]);
            
            if ($result) {
                $this->logTemplateAction('archive', $template_id, $user_id);
            }
            
            return $result;
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to archive template',
                'error' => $e->getMessage(),
                'template_id' => $template_id
            ]);
            return false;
        }
    }
    
    /**
     * Duplicate a template
     * 
     * @param string $template_id
     * @param string $user_id
     * @param string $new_name
     * @return string New template ID
     */
    public function duplicateTemplate(string $template_id, string $user_id, string $new_name): string
    {
        try {
            $template = $this->getTemplate($template_id, $user_id);
            if (!$template) {
                throw new Exception('Template not found');
            }
            
            return $this->createTemplate([
                'template_name' => $new_name,
                'description' => $template['description'] . ' (Copy)',
                'encounter_type' => $template['encounter_type'],
                'template_data' => $template['template_data'],
                'created_by' => $user_id,
                'visibility' => 'personal' // Always make copies personal
            ]);
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to duplicate template',
                'error' => $e->getMessage(),
                'template_id' => $template_id
            ]);
            throw $e;
        }
    }
    
    /**
     * Get organization templates pending approval
     * 
     * @return array
     */
    public function getPendingTemplates(): array
    {
        try {
            $sql = "SELECT t.*, u.username as created_by_name
                    FROM chart_templates t
                    LEFT JOIN user u ON t.created_by = u.user_id
                    WHERE t.visibility = 'organization' 
                    AND t.status = 'pending_approval'
                    ORDER BY t.created_at DESC";
            
            $stmt = $this->db->query($sql);
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($templates as &$template) {
                $template['template_data'] = json_decode($template['template_data'], true);
            }
            
            return $templates;
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to get pending templates',
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Approve an organization template
     * 
     * @param string $template_id
     * @param string $approved_by Admin user ID
     * @return bool
     */
    public function approveTemplate(string $template_id, string $approved_by): bool
    {
        try {
            $sql = "UPDATE chart_templates 
                    SET status = 'active', updated_at = NOW()
                    WHERE template_id = :template_id 
                    AND status = 'pending_approval'";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute(['template_id' => $template_id]);
            
            if ($result && $stmt->rowCount() > 0) {
                $this->logTemplateAction('approve', $template_id, $approved_by);
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to approve template',
                'error' => $e->getMessage(),
                'template_id' => $template_id
            ]);
            return false;
        }
    }
    
    /**
     * Load template data into an encounter form
     * 
     * @param string $template_id
     * @param string $user_id
     * @return array Template data ready for form population
     */
    public function loadTemplateForEncounter(string $template_id, string $user_id): array
    {
        try {
            $template = $this->getTemplate($template_id, $user_id);
            if (!$template) {
                throw new Exception('Template not found or access denied');
            }
            
            // Log template usage
            $this->logTemplateAction('load', $template_id, $user_id);
            
            // Return template data formatted for encounter form
            return [
                'template_id' => $template_id,
                'template_name' => $template['template_name'],
                'encounter_type' => $template['encounter_type'],
                'form_data' => $template['template_data']
            ];
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to load template for encounter',
                'error' => $e->getMessage(),
                'template_id' => $template_id
            ]);
            throw $e;
        }
    }
    
    /**
     * Log template-related actions
     *
     * @param string $action
     * @param string $template_id
     * @param string $user_id
     * @param array $extra_data
     */
    private function logTemplateAction(string $action, string $template_id, string $user_id, array $extra_data = []): void
    {
        try {
            \App\log\file_log('audit', array_merge([
                'action' => 'template_' . $action,
                'template_id' => $template_id,
                'user_id' => $user_id,
                'timestamp' => date('Y-m-d H:i:s')
            ], $extra_data));
        } catch (\Exception $e) {
            // Silent fail for logging
        }
    }
}