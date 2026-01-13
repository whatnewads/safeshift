<?php
/**
 * Drug Screen Statistics API Endpoint
 * Provides statistics for the delegated clinician dashboard
 */

require_once __DIR__ . '/../includes/bootstrap.php';

use Core\Auth;
use Core\Security;
use Core\Database;

// Set JSON header
header('Content-Type: application/json');

// Check authentication
if (!Auth::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check role permissions (dclinician or higher)
$allowedRoles = ['dclinician', '1clinician', 'admin'];
if (!in_array($_SESSION['user_type'], $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Get user and employer info
$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'];
$employerId = $_SESSION['employer_id'] ?? null;

try {
    $db = Database::getInstance()->pdo();
    
    // Get drug screen statistics based on user role
    $stats = [];
    
    if ($userType === 'admin') {
        // Admin sees all drug screens across the system
        
        // Total tests today
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM drug_screens 
            WHERE DATE(created_at) = CURDATE()
        ");
        $stmt->execute();
        $stats['tests_today'] = $stmt->fetchColumn();
        
        // Positive results this week
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM drug_screens 
            WHERE result = 'positive' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute();
        $stats['positive_results'] = $stmt->fetchColumn();
        
        // Pending reviews
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM drug_screens 
            WHERE status = 'pending_review'
        ");
        $stmt->execute();
        $stats['pending_reviews'] = $stmt->fetchColumn();
        
        // MRO reviews needed
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM drug_screens 
            WHERE status = 'mro_review' 
            OR (result = 'positive' AND mro_reviewed = 0)
        ");
        $stmt->execute();
        $stats['mro_reviews'] = $stmt->fetchColumn();
        
    } else {
        // Dclinician and 1clinician see employer-specific data
        
        // Total tests today for employer
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM drug_screens ds
            JOIN patients p ON ds.patient_id = p.id
            WHERE p.employer_id = :employer_id
            AND DATE(ds.created_at) = CURDATE()
        ");
        $stmt->execute(['employer_id' => $employerId]);
        $stats['tests_today'] = $stmt->fetchColumn();
        
        // Positive results this week for employer
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM drug_screens ds
            JOIN patients p ON ds.patient_id = p.id
            WHERE p.employer_id = :employer_id
            AND ds.result = 'positive' 
            AND ds.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute(['employer_id' => $employerId]);
        $stats['positive_results'] = $stmt->fetchColumn();
        
        // Pending reviews for employer
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM drug_screens ds
            JOIN patients p ON ds.patient_id = p.id
            WHERE p.employer_id = :employer_id
            AND ds.status = 'pending_review'
        ");
        $stmt->execute(['employer_id' => $employerId]);
        $stats['pending_reviews'] = $stmt->fetchColumn();
        
        // MRO reviews needed for employer
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM drug_screens ds
            JOIN patients p ON ds.patient_id = p.id
            WHERE p.employer_id = :employer_id
            AND (ds.status = 'mro_review' OR (ds.result = 'positive' AND ds.mro_reviewed = 0))
        ");
        $stmt->execute(['employer_id' => $employerId]);
        $stats['mro_reviews'] = $stmt->fetchColumn();
    }
    
    // Get recent drug screens
    $recentTestsQuery = "
        SELECT 
            ds.id,
            ds.test_type,
            ds.collection_date,
            ds.result,
            ds.status,
            ds.chain_of_custody_number,
            p.first_name,
            p.last_name,
            p.patient_id as patient_number,
            e.company_name
        FROM drug_screens ds
        JOIN patients p ON ds.patient_id = p.id
        LEFT JOIN employers e ON p.employer_id = e.id
    ";
    
    if ($userType !== 'admin') {
        $recentTestsQuery .= " WHERE p.employer_id = :employer_id";
    }
    
    $recentTestsQuery .= " ORDER BY ds.created_at DESC LIMIT 10";
    
    $stmt = $db->prepare($recentTestsQuery);
    
    if ($userType !== 'admin') {
        $stmt->execute(['employer_id' => $employerId]);
    } else {
        $stmt->execute();
    }
    
    $recentTests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get test type breakdown for chart
    $testTypeQuery = "
        SELECT 
            test_type,
            COUNT(*) as count
        FROM drug_screens ds
    ";
    
    if ($userType !== 'admin') {
        $testTypeQuery .= " 
            JOIN patients p ON ds.patient_id = p.id
            WHERE p.employer_id = :employer_id
            AND ds.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ";
    } else {
        $testTypeQuery .= " WHERE ds.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }
    
    $testTypeQuery .= " GROUP BY test_type";
    
    $stmt = $db->prepare($testTypeQuery);
    
    if ($userType !== 'admin') {
        $stmt->execute(['employer_id' => $employerId]);
    } else {
        $stmt->execute();
    }
    
    $testTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format test type data for Chart.js
    $chartData = [
        'labels' => [],
        'data' => []
    ];
    
    foreach ($testTypes as $type) {
        $chartData['labels'][] = ucfirst(str_replace('_', ' ', $type['test_type']));
        $chartData['data'][] = $type['count'];
    }
    
    // Get compliance rate
    $complianceQuery = "
        SELECT 
            COUNT(CASE WHEN result = 'negative' THEN 1 END) as negative_count,
            COUNT(*) as total_count
        FROM drug_screens ds
    ";
    
    if ($userType !== 'admin') {
        $complianceQuery .= "
            JOIN patients p ON ds.patient_id = p.id
            WHERE p.employer_id = :employer_id
            AND ds.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        ";
    } else {
        $complianceQuery .= " WHERE ds.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
    }
    
    $stmt = $db->prepare($complianceQuery);
    
    if ($userType !== 'admin') {
        $stmt->execute(['employer_id' => $employerId]);
    } else {
        $stmt->execute();
    }
    
    $compliance = $stmt->fetch(PDO::FETCH_ASSOC);
    $complianceRate = $compliance['total_count'] > 0 
        ? round(($compliance['negative_count'] / $compliance['total_count']) * 100, 1)
        : 100;
    
    // Build response
    $response = [
        'success' => true,
        'data' => [
            'stats' => $stats,
            'recent_tests' => $recentTests,
            'chart_data' => $chartData,
            'compliance_rate' => $complianceRate,
            'last_updated' => date('Y-m-d H:i:s')
        ]
    ];
    
    // Apply security headers
    Security::applySecurityHeaders();
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log('Drug screen stats error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => 'Failed to retrieve drug screen statistics'
    ]);
}