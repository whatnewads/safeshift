<?php
/**
 * DatabaseFillerTest.php - Database Filler Data Detection Tests
 * 
 * Tests that database tables don't contain placeholder/filler data
 * that should not exist in production databases.
 * 
 * @package    SafeShift\Tests\Integration
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Helpers\TestCase;
use PDO;

/**
 * Database Filler Data Detection Tests
 * 
 * Validates that database tables do not contain placeholder data,
 * test values, or filler content that shouldn't exist in production.
 */
class DatabaseFillerTest extends TestCase
{
    // Uses $pdo from parent TestCase class

    /**
     * Test patient names - patterns that shouldn't exist
     */
    private array $forbiddenPatientNames = [
        'John Doe',
        'Jane Doe',
        'Test User',
        'Test Patient',
        'Demo Patient',
        'Sample Patient',
        'Foo Bar',
        'Firstname Lastname',
        'Patient One',
        'Patient Two',
    ];

    /**
     * Test email patterns that shouldn't exist in production
     */
    private array $forbiddenEmailPatterns = [
        '@test.com',
        '@example.com',
        '@localhost',
        '@foo.bar',
        '@mailinator.com',
        '@tempmail.com',
        'test@',
        'demo@',
        'sample@',
        'fake@',
    ];

    /**
     * Test phone patterns that shouldn't exist in production
     */
    private array $forbiddenPhonePatterns = [
        '555-',
        '123-456-7890',
        '000-000-0000',
        '111-111-1111',
        '999-999-9999',
    ];

    /**
     * Test SSN patterns that shouldn't exist in production
     */
    private array $forbiddenSsnPatterns = [
        '000-00-0000',
        '111-11-1111',
        '123-45-6789',
        '999-99-9999',
        '000000000',
        '111111111',
        '123456789',
    ];

    /**
     * Test address patterns that shouldn't exist in production
     */
    private array $forbiddenAddressPatterns = [
        '123 Main St',
        '456 Test Ave',
        '789 Fake St',
        '123 Example Blvd',
        '100 Demo Way',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        
        // Skip database tests if no database connection is available
        if (!$this->getDatabaseConnection()) {
            $this->markTestSkipped('Database connection not available');
        }
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
        parent::tearDown();
    }

    /**
     * Test no patients named John Doe or similar test names
     */
    public function testNoPatientsNamedJohnDoe(): void
    {
        $pdo = $this->getDatabaseConnection();
        if (!$pdo) {
            $this->markTestSkipped('Database connection not available');
            return;
        }
        
        $issues = [];
        
        foreach ($this->forbiddenPatientNames as $name) {
            $parts = explode(' ', $name);
            $firstName = $parts[0];
            $lastName = $parts[1] ?? '';
            
            // Check for exact match on first and last name
            $stmt = $pdo->prepare("
                SELECT id, first_name, last_name 
                FROM patients 
                WHERE LOWER(first_name) = LOWER(:first_name) 
                  AND LOWER(last_name) = LOWER(:last_name)
                LIMIT 5
            ");
            
            $stmt->execute([
                'first_name' => $firstName,
                'last_name' => $lastName,
            ]);
            
            $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($matches)) {
                foreach ($matches as $match) {
                    $issues[] = [
                        'table' => 'patients',
                        'id' => $match['id'],
                        'field' => 'name',
                        'value' => "{$match['first_name']} {$match['last_name']}",
                        'pattern' => $name,
                    ];
                }
            }
        }
        
        $this->assertEmpty(
            $issues,
            $this->formatIssuesMessage($issues, 'Test patient names found in database')
        );
    }

    /**
     * Test no test emails in database
     */
    public function testNoTestEmailsInDatabase(): void
    {
        $pdo = $this->getDatabaseConnection();
        if (!$pdo) {
            $this->markTestSkipped('Database connection not available');
            return;
        }
        
        $issues = [];
        $tables = ['patients', 'users', 'contacts'];
        
        foreach ($tables as $table) {
            if (!$this->tableExists($pdo, $table)) {
                continue;
            }
            
            foreach ($this->forbiddenEmailPatterns as $pattern) {
                $stmt = $pdo->prepare("
                    SELECT id, email 
                    FROM {$table} 
                    WHERE LOWER(email) LIKE LOWER(:pattern)
                    LIMIT 5
                ");
                
                $stmt->execute(['pattern' => "%{$pattern}%"]);
                $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($matches)) {
                    foreach ($matches as $match) {
                        $issues[] = [
                            'table' => $table,
                            'id' => $match['id'],
                            'field' => 'email',
                            'value' => $match['email'],
                            'pattern' => $pattern,
                        ];
                    }
                }
            }
        }
        
        $this->assertEmpty(
            $issues,
            $this->formatIssuesMessage($issues, 'Test emails found in database')
        );
    }

    /**
     * Test no test phone numbers in database
     */
    public function testNoTestPhoneNumbersInDatabase(): void
    {
        $pdo = $this->getDatabaseConnection();
        if (!$pdo) {
            $this->markTestSkipped('Database connection not available');
            return;
        }
        
        $issues = [];
        $tables = ['patients', 'users', 'contacts', 'clinics'];
        
        foreach ($tables as $table) {
            if (!$this->tableExists($pdo, $table)) {
                continue;
            }
            
            // Get phone column name (might vary by table)
            $phoneColumn = $this->getPhoneColumn($pdo, $table);
            if (!$phoneColumn) {
                continue;
            }
            
            foreach ($this->forbiddenPhonePatterns as $pattern) {
                $stmt = $pdo->prepare("
                    SELECT id, {$phoneColumn} as phone 
                    FROM {$table} 
                    WHERE {$phoneColumn} LIKE :pattern
                    LIMIT 5
                ");
                
                $stmt->execute(['pattern' => "%{$pattern}%"]);
                $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($matches)) {
                    foreach ($matches as $match) {
                        $issues[] = [
                            'table' => $table,
                            'id' => $match['id'],
                            'field' => 'phone',
                            'value' => $match['phone'],
                            'pattern' => $pattern,
                        ];
                    }
                }
            }
        }
        
        $this->assertEmpty(
            $issues,
            $this->formatIssuesMessage($issues, 'Test phone numbers found in database')
        );
    }

    /**
     * Test no test SSNs in database
     */
    public function testNoTestSsnsInDatabase(): void
    {
        $pdo = $this->getDatabaseConnection();
        if (!$pdo) {
            $this->markTestSkipped('Database connection not available');
            return;
        }
        
        $issues = [];
        
        if (!$this->tableExists($pdo, 'patients')) {
            $this->markTestSkipped('Patients table not found');
            return;
        }
        
        // Check if SSN column exists
        $ssnColumn = $this->getSsnColumn($pdo, 'patients');
        if (!$ssnColumn) {
            $this->markTestSkipped('SSN column not found in patients table');
            return;
        }
        
        foreach ($this->forbiddenSsnPatterns as $pattern) {
            // Normalize pattern for comparison (remove dashes)
            $normalizedPattern = str_replace('-', '', $pattern);
            
            $stmt = $pdo->prepare("
                SELECT id, {$ssnColumn} as ssn 
                FROM patients 
                WHERE REPLACE({$ssnColumn}, '-', '') = :pattern
                   OR {$ssnColumn} = :original_pattern
                LIMIT 5
            ");
            
            $stmt->execute([
                'pattern' => $normalizedPattern,
                'original_pattern' => $pattern,
            ]);
            
            $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($matches)) {
                foreach ($matches as $match) {
                    $issues[] = [
                        'table' => 'patients',
                        'id' => $match['id'],
                        'field' => 'ssn',
                        'value' => '[REDACTED]', // Don't expose SSN in test output
                        'pattern' => $pattern,
                    ];
                }
            }
        }
        
        $this->assertEmpty(
            $issues,
            $this->formatIssuesMessage($issues, 'Test SSNs found in database')
        );
    }

    /**
     * Test no test addresses in database
     */
    public function testNoTestAddressesInDatabase(): void
    {
        $pdo = $this->getDatabaseConnection();
        if (!$pdo) {
            $this->markTestSkipped('Database connection not available');
            return;
        }
        
        $issues = [];
        $tables = ['patients', 'clinics', 'users'];
        
        foreach ($tables as $table) {
            if (!$this->tableExists($pdo, $table)) {
                continue;
            }
            
            $addressColumn = $this->getAddressColumn($pdo, $table);
            if (!$addressColumn) {
                continue;
            }
            
            foreach ($this->forbiddenAddressPatterns as $pattern) {
                $stmt = $pdo->prepare("
                    SELECT id, {$addressColumn} as address 
                    FROM {$table} 
                    WHERE LOWER({$addressColumn}) LIKE LOWER(:pattern)
                    LIMIT 5
                ");
                
                $stmt->execute(['pattern' => "%{$pattern}%"]);
                $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($matches)) {
                    foreach ($matches as $match) {
                        $issues[] = [
                            'table' => $table,
                            'id' => $match['id'],
                            'field' => 'address',
                            'value' => $match['address'],
                            'pattern' => $pattern,
                        ];
                    }
                }
            }
        }
        
        $this->assertEmpty(
            $issues,
            $this->formatIssuesMessage($issues, 'Test addresses found in database')
        );
    }

    /**
     * Test no placeholder text in notes/comments fields
     */
    public function testNoPlaceholderTextInNotes(): void
    {
        $pdo = $this->getDatabaseConnection();
        if (!$pdo) {
            $this->markTestSkipped('Database connection not available');
            return;
        }
        
        $issues = [];
        $placeholderPatterns = [
            'Lorem ipsum',
            'Dolor sit amet',
            'TODO:',
            'FIXME:',
            'XXX',
            'TBD',
            'Insert notes here',
            'Add description',
            'Sample text',
            'Test notes',
        ];
        
        // Tables with notes/comments fields
        $noteFields = [
            'encounters' => ['chief_complaint', 'notes', 'assessment'],
            'patients' => ['notes', 'comments'],
        ];
        
        foreach ($noteFields as $table => $fields) {
            if (!$this->tableExists($pdo, $table)) {
                continue;
            }
            
            foreach ($fields as $field) {
                if (!$this->columnExists($pdo, $table, $field)) {
                    continue;
                }
                
                foreach ($placeholderPatterns as $pattern) {
                    $stmt = $pdo->prepare("
                        SELECT id, {$field} as content 
                        FROM {$table} 
                        WHERE LOWER({$field}) LIKE LOWER(:pattern)
                        LIMIT 5
                    ");
                    
                    $stmt->execute(['pattern' => "%{$pattern}%"]);
                    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($matches)) {
                        foreach ($matches as $match) {
                            $issues[] = [
                                'table' => $table,
                                'id' => $match['id'],
                                'field' => $field,
                                'value' => substr($match['content'] ?? '', 0, 50),
                                'pattern' => $pattern,
                            ];
                        }
                    }
                }
            }
        }
        
        $this->assertEmpty(
            $issues,
            $this->formatIssuesMessage($issues, 'Placeholder text found in database notes')
        );
    }

    /**
     * Test no test users in database (by common test usernames)
     */
    public function testNoTestUsersInDatabase(): void
    {
        $pdo = $this->getDatabaseConnection();
        if (!$pdo) {
            $this->markTestSkipped('Database connection not available');
            return;
        }
        
        if (!$this->tableExists($pdo, 'users')) {
            $this->markTestSkipped('Users table not found');
            return;
        }
        
        $issues = [];
        $testUsernames = [
            'testuser',
            'testadmin',
            'testclinician',
            'demo',
            'demouser',
            'admin', // Generic admin without proper naming
            'sample',
            'test',
            'user1',
            'user2',
        ];
        
        foreach ($testUsernames as $username) {
            $stmt = $pdo->prepare("
                SELECT id, username, email 
                FROM users 
                WHERE LOWER(username) = LOWER(:username)
                LIMIT 5
            ");
            
            $stmt->execute(['username' => $username]);
            $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($matches)) {
                foreach ($matches as $match) {
                    $issues[] = [
                        'table' => 'users',
                        'id' => $match['id'],
                        'field' => 'username',
                        'value' => $match['username'],
                        'pattern' => $username,
                    ];
                }
            }
        }
        
        $this->assertEmpty(
            $issues,
            $this->formatIssuesMessage($issues, 'Test users found in database')
        );
    }

    /**
     * Test no placeholder dates (like 1900-01-01, 2099-12-31)
     */
    public function testNoPlaceholderDatesInDatabase(): void
    {
        $pdo = $this->getDatabaseConnection();
        if (!$pdo) {
            $this->markTestSkipped('Database connection not available');
            return;
        }
        
        $issues = [];
        $placeholderDates = [
            '1900-01-01',
            '1970-01-01',
            '2000-01-01',
            '2099-12-31',
            '9999-12-31',
        ];
        
        // Tables with date of birth fields
        if ($this->tableExists($pdo, 'patients') && $this->columnExists($pdo, 'patients', 'date_of_birth')) {
            foreach ($placeholderDates as $date) {
                $stmt = $pdo->prepare("
                    SELECT id, first_name, last_name, date_of_birth 
                    FROM patients 
                    WHERE DATE(date_of_birth) = :date
                    LIMIT 5
                ");
                
                $stmt->execute(['date' => $date]);
                $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($matches)) {
                    foreach ($matches as $match) {
                        $issues[] = [
                            'table' => 'patients',
                            'id' => $match['id'],
                            'field' => 'date_of_birth',
                            'value' => $match['date_of_birth'],
                            'pattern' => $date,
                        ];
                    }
                }
            }
        }
        
        $this->assertEmpty(
            $issues,
            $this->formatIssuesMessage($issues, 'Placeholder dates found in database')
        );
    }

    /**
     * Get database connection
     */
    private function getDatabaseConnection(): ?PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }
        
        // Try to get database configuration
        $configFile = dirname(__DIR__, 2) . '/includes/config.php';
        
        if (!file_exists($configFile)) {
            return null;
        }
        
        // Extract database configuration
        $config = [];
        try {
            // We need to safely extract config without executing the whole file
            $configContent = file_get_contents($configFile);
            
            // Look for database constants
            if (preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $configContent, $matches)) {
                $config['host'] = $matches[1];
            }
            if (preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $configContent, $matches)) {
                $config['dbname'] = $matches[1];
            }
            if (preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $configContent, $matches)) {
                $config['user'] = $matches[1];
            }
            if (preg_match("/define\s*\(\s*['\"]DB_PASS['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $configContent, $matches)) {
                $config['pass'] = $matches[1];
            }
            
            if (empty($config['host']) || empty($config['dbname'])) {
                return null;
            }
            
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
            $this->pdo = new PDO(
                $dsn,
                $config['user'] ?? 'root',
                $config['pass'] ?? '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
            
            return $this->pdo;
        } catch (\PDOException $e) {
            // Database connection failed - tests will be skipped
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if a table exists
     */
    private function tableExists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Check if a column exists in a table
     */
    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Get phone column name for a table
     */
    private function getPhoneColumn(PDO $pdo, string $table): ?string
    {
        $possibleColumns = ['phone', 'phone_number', 'mobile', 'cell_phone', 'contact_phone'];
        
        foreach ($possibleColumns as $column) {
            if ($this->columnExists($pdo, $table, $column)) {
                return $column;
            }
        }
        
        return null;
    }

    /**
     * Get SSN column name for a table
     */
    private function getSsnColumn(PDO $pdo, string $table): ?string
    {
        $possibleColumns = ['ssn', 'social_security_number', 'ss_number'];
        
        foreach ($possibleColumns as $column) {
            if ($this->columnExists($pdo, $table, $column)) {
                return $column;
            }
        }
        
        return null;
    }

    /**
     * Get address column name for a table
     */
    private function getAddressColumn(PDO $pdo, string $table): ?string
    {
        $possibleColumns = ['address', 'street_address', 'address_line1', 'address1'];
        
        foreach ($possibleColumns as $column) {
            if ($this->columnExists($pdo, $table, $column)) {
                return $column;
            }
        }
        
        return null;
    }

    /**
     * Format issues into a readable message
     */
    private function formatIssuesMessage(array $issues, string $title): string
    {
        if (empty($issues)) {
            return '';
        }
        
        $message = "\n{$title}:\n";
        $message .= str_repeat('-', 60) . "\n";
        
        foreach ($issues as $issue) {
            $message .= sprintf(
                "  Table: %s, ID: %s\n    Field: %s\n    Value: %s\n    Pattern: %s\n\n",
                $issue['table'],
                $issue['id'],
                $issue['field'],
                $issue['value'],
                $issue['pattern']
            );
        }
        
        return $message;
    }
}
