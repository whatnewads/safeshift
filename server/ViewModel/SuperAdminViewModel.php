<?php

declare(strict_types=1);

namespace ViewModel;

use Model\Repositories\SuperAdminRepository;
use ViewModel\Core\ApiResponse;
use PDO;

/**
 * SuperAdmin ViewModel
 *
 * Coordinates between the View (API) and Model (Repository) layers
 * for super admin operations including user management, system config,
 * security incidents, and audit logs.
 *
 * @package ViewModel
 */
class SuperAdminViewModel
{
    private SuperAdminRepository $superAdminRepository;
    private ?string $currentUserId = null;

    public function __construct(PDO $pdo)
    {
        $this->superAdminRepository = new SuperAdminRepository($pdo);
    }

    /**
     * Set the current user context
     */
    public function setCurrentUser(string $userId): self
    {
        $this->currentUserId = $userId;
        return $this;
    }

    // =========================================================================
    // Dashboard
    // =========================================================================

    /**
     * Get SuperAdmin Dashboard data
     * Returns comprehensive dashboard data including stats, users, clinics,
     * security incidents, and override requests
     */
    public function getSuperAdminDashboard(): array
    {
        try {
            // Get system stats
            $stats = $this->superAdminRepository->getSuperAdminStats();

            // Format stats for frontend with expected keys
            $formattedStats = [
                'totalUsers' => $stats['totalUsers'] ?? 0,
                'activeUsers' => $stats['activeUsers'] ?? 0,
                'activeClinics' => $stats['activeClinics'] ?? 0,
                'dotTestsThisMonth' => $stats['dotTestsThisMonth'] ?? 0,
                'systemUptime' => $stats['systemUptime'] ?? 99.9,
                'openIncidents' => $stats['openIncidents'] ?? 0,
                'auditLogsToday' => $stats['auditLogsToday'] ?? 0,
            ];

            return ApiResponse::success([
                'stats' => $formattedStats,
            ]);
        } catch (\Exception $e) {
            error_log("SuperAdminViewModel::getSuperAdminDashboard error: " . $e->getMessage());
            return ApiResponse::error('Failed to retrieve super admin dashboard data', 500);
        }
    }

    /**
     * Get full dashboard data including all related entities
     * Used for comprehensive dashboard view
     */
    public function getFullDashboardData(): array
    {
        try {
            // Get system stats
            $stats = $this->superAdminRepository->getSuperAdminStats();
            
            // Get recent users (top 10)
            $users = $this->superAdminRepository->getAllUsers(10, 0);
            $formattedUsers = array_map(function($user) {
                return [
                    'id' => $user['user_id'],
                    'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['username'],
                    'email' => $user['email'],
                    'role' => $user['roles'] ? explode(',', $user['roles'])[0] : 'User',
                    'status' => $user['is_active'] ? 'active' : 'inactive',
                    'lastLogin' => $user['last_login'],
                ];
            }, $users);

            // Get clinics (top 10)
            $clinics = $this->superAdminRepository->getAllClinics();
            $formattedClinics = array_slice(array_map(function($clinic) {
                return [
                    'id' => $clinic['clinic_id'],
                    'name' => $clinic['clinic_name'],
                    'location' => trim(($clinic['address'] ?? '') . ', ' . ($clinic['city'] ?? '') . ', ' . ($clinic['state'] ?? '')),
                    'status' => $clinic['is_active'] ? 'active' : 'inactive',
                    'userCount' => (int)($clinic['employee_count'] ?? 0),
                ];
            }, $clinics), 0, 10);

            // Get security incidents (top 10)
            $incidents = $this->superAdminRepository->getSecurityIncidents(null, 10, 0);
            $formattedIncidents = array_map(function($incident) {
                return [
                    'id' => $incident['incident_id'],
                    'type' => $incident['incident_type'],
                    'severity' => $incident['severity'],
                    'status' => $incident['status'],
                    'timestamp' => $incident['created_at'],
                ];
            }, $incidents);

            // Get override requests (top 10)
            $overrides = $this->superAdminRepository->getOverrideRequests('pending', 10, 0);
            $formattedOverrides = array_map(function($request) {
                return [
                    'id' => $request['request_id'],
                    'type' => $this->formatOverrideType($request['request_type'] ?? 'clearance_override'),
                    'requestedBy' => $request['requested_by_name'] ?? $request['requested_by_username'] ?? 'Unknown',
                    'status' => $request['status'] ?? 'pending',
                    'timestamp' => $request['requested_at'],
                ];
            }, $overrides);

            // Format stats for frontend
            $formattedStats = [
                'totalUsers' => $stats['totalUsers'] ?? 0,
                'activeUsers' => $stats['activeUsers'] ?? 0,
                'activeClinics' => $stats['activeClinics'] ?? 0,
                'dotTestsThisMonth' => $stats['dotTestsThisMonth'] ?? 0,
                'systemUptime' => $stats['systemUptime'] ?? 99.9,
                'openIncidents' => $stats['openIncidents'] ?? 0,
                'auditLogsToday' => $stats['auditLogsToday'] ?? 0,
            ];

            return ApiResponse::success([
                'systemStats' => $formattedStats,
                'users' => $formattedUsers,
                'clinics' => $formattedClinics,
                'securityIncidents' => $formattedIncidents,
                'overrideRequests' => $formattedOverrides,
            ]);
        } catch (\Exception $e) {
            error_log("SuperAdminViewModel::getFullDashboardData error: " . $e->getMessage());
            return ApiResponse::error('Failed to retrieve dashboard data', 500);
        }
    }

    /**
     * Format override type for display
     */
    private function formatOverrideType(string $type): string
    {
        $types = [
            'clearance_override' => 'Clearance Override',
            'access_override' => 'Access Override',
            'role_elevation' => 'Role Elevation',
            'data_access' => 'Data Access Override',
        ];
        return $types[$type] ?? ucwords(str_replace('_', ' ', $type));
    }

    // =========================================================================
    // User Management
    // =========================================================================

    /**
     * Get all system users
     */
    public function getUsers(int $page = 1, int $perPage = 50): array
    {
        try {
            $offset = ($page - 1) * $perPage;
            $users = $this->superAdminRepository->getAllUsers($perPage, $offset);
            $totalCount = $this->superAdminRepository->countUsers();

            // Format for frontend
            $formattedUsers = array_map(function($user) {
                return [
                    'id' => $user['user_id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['username'],
                    'firstName' => $user['first_name'],
                    'lastName' => $user['last_name'],
                    'roles' => $user['roles'] ? explode(',', $user['roles']) : [],
                    'status' => $user['is_active'] ? 'active' : 'inactive',
                    'lastLogin' => $user['last_login'],
                    'createdAt' => $user['created_at'],
                ];
            }, $users);

            return ApiResponse::success([
                'users' => $formattedUsers,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $totalCount,
                    'total_pages' => (int) ceil($totalCount / $perPage),
                ],
            ]);
        } catch (\Exception $e) {
            error_log("SuperAdminViewModel::getUsers error: " . $e->getMessage());
            return ApiResponse::error('Failed to retrieve users', 500);
        }
    }

    /**
     * Get a single user by ID
     */
    public function getUser(string $userId): array
    {
        try {
            $user = $this->superAdminRepository->getUserById($userId);

            if (!$user) {
                return ApiResponse::notFound('User not found');
            }

            return ApiResponse::success([
                'user' => [
                    'id' => $user['user_id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'firstName' => $user['first_name'],
                    'lastName' => $user['last_name'],
                    'status' => $user['is_active'] ? 'active' : 'inactive',
                    'lastLogin' => $user['last_login'],
                    'createdAt' => $user['created_at'],
                    'roles' => array_map(function($role) {
                        return [
                            'id' => $role['role_id'],
                            'name' => $role['role_name'],
                            'description' => $role['description'],
                        ];
                    }, $user['roles'] ?? []),
                ],
            ]);
        } catch (\Exception $e) {
            error_log("SuperAdminViewModel::getUser error: " . $e->getMessage());
            return ApiResponse::error('Failed to retrieve user', 500);
        }
    }

    /**
     * Create a new user
     */
    public function createUser(array $userData): array
    {
        try {
            // Validate required fields
            if (empty($userData['username']) || empty($userData['email'])) {
                return ApiResponse::badRequest('Username and email are required');
            }

            $userId = $this->superAdminRepository->createUser($userData);

            if (!$userId) {
                return ApiResponse::error('Failed to create user', 500);
            }

            // Assign roles if provided
            if (!empty($userData['roles']) && is_array($userData['roles'])) {
                foreach ($userData['roles'] as $roleId) {
                    $this->superAdminRepository->assignRole($userId, $roleId);
                }
            }

            return ApiResponse::success([
                'message' => 'User created successfully',
                'userId' => $userId,
            ], 201);
        } catch (\Exception $e) {
            error_log("SuperAdminViewModel::createUser error: " . $e->getMessage());
            return ApiResponse::error('Failed to create user', 500);
        }
    }

    /**
     * Update user status (activate/deactivate)
     */
    public function updateUserStatus(string $userId, bool $isActive): array
    {
        try {
            $success = $this->superAdminRepository->updateUserStatus($userId, $isActive);

            if ($success) {
                return ApiResponse::success([
                    'message' => $isActive ? 'User activated' : 'User deactivated',
                ]);
            }

            return ApiResponse::error('Failed to update user status', 500);
        } catch (\Exception $e) {
            error_log("SuperAdminViewModel::updateUserStatus error: " . $e->getMessage());
            return ApiResponse::error('Failed to update user status', 500);
        }
    }

    /**
     * Assign a role to a user
     */
    public function assignRole(string $userId, string $roleId): array
    {
        try {
            $success = $this->superAdminRepository->assignRole($userId, $roleId);

            if ($success) {
                return ApiResponse::success([
                    'message' => 'Role assigned successfully',
                ]);
            }

            return ApiResponse::error('Failed to assign role', 500);
        } catch (\Exception $e) {
            error_log("SuperAdminViewModel::assignRole error: " . $e->getMessage());
            return ApiResponse::error('Failed to assign role', 500);
        }
    }

    /**
     * Remove a role from a user
     */
    public function removeRole(string $userId, string $roleId): array
    {
        try {
            $success = $this->superAdminRepository->removeRole($userId, $roleId);

            if ($success) {
                return ApiResponse::success([
                    'message' => 'Role removed successfully',
                ]);
            }

            return ApiResponse::error('Failed to remove role', 500);
        } catch (\Exception $e) {
            error_log("SuperAdminViewModel::removeRole error: " . $e->getMessage());
            return ApiResponse::error('Failed to remove role', 500);
        }
    }

    /**
     * Get all available roles
     */
    public function getRoles(): array
    {
        try {
            $roles = $this->superAdminRepository->getAllRoles();

            return ApiResponse::success([
                'roles' => array_map(function($role) {
                    return [
                        'id' => $role['role_id'],
                        'name' => $role['role_name'],
                        'description' => $role['description'],
                    ];
                }, $roles),
            ]);
        } catch (\Exception $e) {
            error_log("SuperAdminViewModel::getRoles error: " . $e->getMessage());
            return ApiResponse::error('Failed to retrieve roles', 500);
        }
    }

    // =========================================================================
    // Clinic Management
    // =========================================================================

    /**
     * Get all clinics
     */
    public function getClinics(): array
    {
        try {
            $clinics = $this->superAdminRepository->getAllClinics();

            return ApiResponse::success([
                'clinics' => array_map(function($clinic) {
                    return [
                        'id' => $clinic['clinic_id'],
                        'name' => $clinic['clinic_name'],
                        'address' => $clinic['address'],
                        'city' => $clinic['city'],
                        'state' => $clinic['state'],
                        'zipCode' => $clinic['zip_code'],
                        'phone' => $clinic['phone'],
                        'status' => $clinic['is_active'] ? 'active' : 'inactive',
                        'employeeCount' => (int)$clinic['employee_count'],
                        'createdAt' => $clinic['created_at'],
                    ];
                }, $clinics),
            ]);
        } catch (\Exception $e) {
            error_log("SuperAdminViewModel::getClinics error: " . $e->getMessage());
            return ApiResponse::error('Failed to retrieve clinics', 500);
        }
    }

    /**
     * Create a new clinic
     */
    public function createClinic(array $clinicData): array
    {
        try {
            if (empty($clinicData['clinic_name'])) {
                return ApiResponse::badRequest('Clinic name is required');
            }

            $clinicId = $this->superAdminRepository->createClinic($clinicData);

            if ($clinicId) {
                return ApiResponse::success([
                    'message' => 'Clinic created successfully',
                    'clinicId' => $clinicId,
                ], 201);
            }

            return ApiResponse::error('Failed to create clinic', 500);
        } catch (\Exception $e) {
            error_log("SuperAdminViewModel::createClinic error: " . $e->getMessage());
            return ApiResponse::error('Failed to create clinic', 500);
        }
    }

    // =========================================================================
    // Audit Logs
    // =========================================================================

    /**
     * Get audit logs
     */
    public function getAuditLogs(array $filters = [], int $page = 1, int $perPage = 100): array
    {
        try {
            $offset = ($page - 1) * $perPage;

            $logs = $this->superAdminRepository->getAuditLogs(
                $filters['userId'] ?? null,
                $filters['action'] ?? null,
                $filters['startDate'] ?? null,
                $filters['endDate'] ?? null,
                $perPage,
                $offset
            );

            // Format for frontend
            $formattedLogs = array_map(function($log) {
                return [
                    'id' => $log['log_id'],
                    'user' => $log['user_name'] ?? $log['username'] ?? 'System',
                    'userId' => $log['user_id'],
                    'action' => $log['action'],
                    'resourceType' => $log['resource_type'],
                    'resourceId' => $log['resource_id'],
                    'ipAddress' => $log['ip_address'],
                    'timestamp' => $log['created_at'],
                    'details' => $log['details'],
                ];
            }, $logs);

            return ApiResponse::success([
                'logs' => $formattedLogs,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                ],
            ]);
        } catch (\Exception $e) {
            error_log("SuperAdminViewModel::getAuditLogs error: " . $e->getMessage());
            return ApiResponse::error('Failed to retrieve audit logs', 500);
        }
    }

    /**
     * Get audit statistics
     */
    public function getAuditStats(?string $date = null): array
    {
        try {
            $stats = $this->superAdminRepository->getAuditStats($date);

            return ApiResponse::success([
                'stats' => [
                    'totalEvents' => (int)$stats['total_events'],
                    'flaggedEvents' => (int)$stats['flagged_events'],
                    'uniqueUsers' => (int)$stats['unique_users'],
                    'systemsAccessed' => (int)$stats['systems_accessed'],
                ],
            ]);
        } catch (\Exception $e) {
            error_log("SuperAdminViewModel::getAuditStats error: " . $e->getMessage());
            return ApiResponse::error('Failed to retrieve audit stats', 500);
        }
    }

    // =========================================================================
    // Security Incidents
    // =========================================================================

    /**
     * Get security incidents
     */
    public function getSecurityIncidents(?string $status = null, int $page = 1, int $perPage = 50): array
    {
        try {
            $offset = ($page - 1) * $perPage;
            $incidents = $this->superAdminRepository->getSecurityIncidents($status, $perPage, $offset);

            // Format for frontend
            $formattedIncidents = array_map(function($incident) {
                return [
                    'id' => $incident['incident_id'],
                    'type' => $incident['incident_type'],
                    'severity' => $incident['severity'],
                    'status' => $incident['status'],
                    'description' => $incident['description'],
                    'reportedBy' => $incident['reported_by_name'] ?? $incident['reported_by_username'] ?? 'System',
                    'timestamp' => $incident['created_at'],
                    'resolvedAt' => $incident['resolved_at'],
                    'resolutionNotes' => $incident['resolution_notes'],
                ];
            }, $incidents);

            return ApiResponse::success([
                'incidents' => $formattedIncidents,
            ]);
        } catch (\Exception $e) {
            error_log("SuperAdminViewModel::getSecurityIncidents error: " . $e->getMessage());
            return ApiResponse::error('Failed to retrieve security incidents', 500);
        }
    }

    /**
     * Create a security incident
     */
    public function createSecurityIncident(array $incidentData): array
    {
        try {
            if (empty($incidentData['incident_type'])) {
                return ApiResponse::badRequest('Incident type is required');
            }

            $incidentId = $this->superAdminRepository->createSecurityIncident($incidentData);

            if ($incidentId) {
                return ApiResponse::success([
                    'message' => 'Security incident created',
                    'incidentId' => $incidentId,
                ], 201);
            }

            return ApiResponse::error('Failed to create security incident', 500);
        } catch (\Exception $e) {
            error_log("SuperAdminViewModel::createSecurityIncident error: " . $e->getMessage());
            return ApiResponse::error('Failed to create security incident', 500);
        }
    }

    /**
     * Resolve a security incident
     */
    public function resolveSecurityIncident(string $incidentId, string $resolutionNotes): array
    {
        if (!$this->currentUserId) {
            return ApiResponse::error('User not authenticated', 401);
        }

        try {
            $success = $this->superAdminRepository->resolveSecurityIncident(
                $incidentId,
                $this->currentUserId,
                $resolutionNotes
            );

            if ($success) {
                return ApiResponse::success([
                    'message' => 'Security incident resolved',
                ]);
            }

            return ApiResponse::error('Failed to resolve security incident', 500);
        } catch (\Exception $e) {
            error_log("SuperAdminViewModel::resolveSecurityIncident error: " . $e->getMessage());
            return ApiResponse::error('Failed to resolve security incident', 500);
        }
    }

    // =========================================================================
    // Override Requests
    // =========================================================================

    /**
     * Get override requests
     */
    public function getOverrideRequests(?string $status = null, int $page = 1, int $perPage = 50): array
    {
        try {
            $offset = ($page - 1) * $perPage;
            $requests = $this->superAdminRepository->getOverrideRequests($status, $perPage, $offset);
            $totalCount = $this->superAdminRepository->countOverrideRequests($status);

            // Format for frontend
            $formattedRequests = array_map(function($request) {
                return [
                    'id' => $request['request_id'],
                    'type' => $this->formatOverrideType($request['request_type'] ?? 'clearance_override'),
                    'entityType' => $request['entity_type'] ?? 'encounter',
                    'entityId' => $request['entity_id'] ?? null,
                    'requestedBy' => $request['requested_by_name'] ?? $request['requested_by_username'] ?? 'Unknown',
                    'requestedById' => $request['requested_by'] ?? null,
                    'reason' => $request['reason'] ?? '',
                    'status' => $request['status'] ?? 'pending',
                    'timestamp' => $request['requested_at'],
                    'approvedBy' => $request['approved_by'] ?? null,
                    'approvedAt' => $request['approved_at'] ?? null,
                    'resolutionNotes' => $request['resolution_notes'] ?? null,
                    // Include patient info if available
                    'patientName' => isset($request['patient_first_name'])
                        ? trim($request['patient_first_name'] . ' ' . ($request['patient_last_name'] ?? ''))
                        : null,
                ];
            }, $requests);

            return ApiResponse::success([
                'overrideRequests' => $formattedRequests,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $totalCount,
                    'total_pages' => (int) ceil($totalCount / max(1, $perPage)),
                ],
            ]);
        } catch (\Exception $e) {
            error_log("SuperAdminViewModel::getOverrideRequests error: " . $e->getMessage());
            return ApiResponse::error('Failed to retrieve override requests', 500);
        }
    }

    /**
     * Approve an override request
     */
    public function approveOverrideRequest(string $requestId, string $notes = ''): array
    {
        if (!$this->currentUserId) {
            return ApiResponse::error('User not authenticated', 401);
        }

        try {
            $success = $this->superAdminRepository->approveOverrideRequest(
                $requestId,
                $this->currentUserId,
                $notes
            );

            if ($success) {
                return ApiResponse::success([
                    'message' => 'Override request approved',
                ]);
            }

            return ApiResponse::error('Failed to approve override request', 500);
        } catch (\Exception $e) {
            error_log("SuperAdminViewModel::approveOverrideRequest error: " . $e->getMessage());
            return ApiResponse::error('Failed to approve override request', 500);
        }
    }

    /**
     * Deny an override request
     */
    public function denyOverrideRequest(string $requestId, string $reason = ''): array
    {
        if (!$this->currentUserId) {
            return ApiResponse::error('User not authenticated', 401);
        }

        try {
            $success = $this->superAdminRepository->denyOverrideRequest(
                $requestId,
                $this->currentUserId,
                $reason
            );

            if ($success) {
                return ApiResponse::success([
                    'message' => 'Override request denied',
                ]);
            }

            return ApiResponse::error('Failed to deny override request', 500);
        } catch (\Exception $e) {
            error_log("SuperAdminViewModel::denyOverrideRequest error: " . $e->getMessage());
            return ApiResponse::error('Failed to deny override request', 500);
        }
    }
}
