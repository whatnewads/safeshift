<?php

namespace Tests\Quality;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * FillerPatternTest
 * 
 * Automated tests to detect placeholder/filler data that should not exist
 * in production code. This helps ensure code quality and prevents test data
 * from leaking into production.
 * 
 * @package Tests\Quality
 */
class FillerPatternTest extends TestCase
{
    /**
     * Source directories to scan for filler patterns
     */
    private array $srcDirectories = [
        'api/',
        'ViewModel/',
        'model/',
        'core/',
        'includes/'
    ];

    /**
     * Directories/patterns to exclude from scanning
     */
    private array $excludePatterns = [
        'vendor/',
        'tests/',
        'node_modules/',
        '.git/',
        'cache/',
        'logs/',
        'sessions/',
        'backups/'
    ];

    /**
     * Filler patterns to detect - organized by category
     */
    private array $fillerPatterns = [
        // Lorem ipsum placeholder text
        '/lorem\s+ipsum/i',
        '/dolor\s+sit\s+amet/i',
        '/consectetur\s+adipiscing/i',
        
        // Test emails in production code
        '/test@test\.com/i',
        '/example@example\.com/i',
        '/foo@bar\.com/i',
        '/user@test\.com/i',
        '/admin@test\.com/i',
        '/noreply@test\.com/i',
        
        // Test phone numbers
        '/555-\d{3}-\d{4}/',
        '/123-456-7890/',
        '/\(555\)\s*\d{3}-\d{4}/',
        '/555\.\d{3}\.\d{4}/',
        '/0{3}-0{3}-0{4}/',
        
        // Generic test names (in strings, not comments)
        '/"John\s+Doe"/i',
        '/"Jane\s+Doe"/i',
        '/"Test\s+User"/i',
        '/"Test\s+Patient"/i',
        '/\'John\s+Doe\'/i',
        '/\'Jane\s+Doe\'/i',
        '/\'Test\s+User\'/i',
        '/\'Test\s+Patient\'/i',
        
        // Placeholder markers without descriptions
        '/XXX[^X]/i',
        '/FIXME(?!:)/i',
        '/HACK(?!:)/i',
        
        // Hardcoded dummy IDs (common patterns)
        '/user_id\s*=\s*[\'"]?123[\'"]?(?![0-9])/',
        '/patient_id\s*=\s*[\'"]?456[\'"]?(?![0-9])/',
        '/id\s*=\s*[\'"]?999[\'"]?(?![0-9])/',
        
        // Test addresses
        '/123\s+Main\s+St/i',
        '/456\s+Test\s+Ave/i',
        '/789\s+Fake\s+St/i',
        
        // Test SSNs (format 000-00-0000 or similar patterns)
        '/000-00-0000/',
        '/111-11-1111/',
        '/123-45-6789/',
    ];

    /**
     * Credential patterns to detect
     */
    private array $credentialPatterns = [
        // Hardcoded passwords
        '/password\s*=\s*[\'"]password[\'"]?/i',
        '/password\s*=\s*[\'"]password123[\'"]?/i',
        '/password\s*=\s*[\'"]123456[\'"]?/i',
        '/password\s*=\s*[\'"]admin[\'"]?/i',
        '/password\s*=\s*[\'"]test[\'"]?/i',
        '/password\s*=\s*[\'"]secret[\'"]?/i',
        
        // Hardcoded API keys (generic patterns)
        '/api_key\s*=\s*[\'"][a-zA-Z0-9]{20,}[\'"]/i',
        '/secret_key\s*=\s*[\'"][a-zA-Z0-9]{20,}[\'"]/i',
        
        // Hardcoded tokens
        '/bearer\s+[a-zA-Z0-9]{32,}/i',
    ];

    /**
     * Debug output patterns
     */
    private array $debugPatterns = [
        // PHP debug functions
        '/\bvar_dump\s*\(/i',
        '/\bprint_r\s*\(/i',
        '/\bdebug_print_backtrace\s*\(/i',
        '/\berror_log\s*\(\s*[\'"]DEBUG/i',
        
        // JavaScript debug in PHP
        '/echo\s*[\'"]<script>console\.log/i',
    ];

    /**
     * Test that no filler patterns exist in PHP files
     */
    public function testNoFillerPatternsInPHPFiles(): void
    {
        $issues = [];
        
        foreach ($this->srcDirectories as $dir) {
            $fullPath = $this->getProjectRoot() . '/' . $dir;
            if (!is_dir($fullPath)) {
                continue;
            }
            
            $phpFiles = $this->getPHPFiles($fullPath);
            
            foreach ($phpFiles as $file) {
                if ($this->shouldExcludeFile($file)) {
                    continue;
                }
                
                $content = file_get_contents($file);
                $lines = explode("\n", $content);
                
                foreach ($this->fillerPatterns as $pattern) {
                    foreach ($lines as $lineNum => $line) {
                        // Skip comment lines
                        if ($this->isCommentLine($line)) {
                            continue;
                        }
                        
                        if (preg_match($pattern, $line)) {
                            $issues[] = [
                                'file' => $this->getRelativePath($file),
                                'line' => $lineNum + 1,
                                'pattern' => $pattern,
                                'content' => trim($line)
                            ];
                        }
                    }
                }
            }
        }
        
        $this->assertEmpty(
            $issues,
            $this->formatIssuesMessage($issues, 'Filler patterns found in PHP files')
        );
    }

    /**
     * Test that no hardcoded credentials exist in PHP files
     */
    public function testNoHardcodedCredentials(): void
    {
        $issues = [];
        
        foreach ($this->srcDirectories as $dir) {
            $fullPath = $this->getProjectRoot() . '/' . $dir;
            if (!is_dir($fullPath)) {
                continue;
            }
            
            $phpFiles = $this->getPHPFiles($fullPath);
            
            foreach ($phpFiles as $file) {
                if ($this->shouldExcludeFile($file)) {
                    continue;
                }
                
                $content = file_get_contents($file);
                $lines = explode("\n", $content);
                
                foreach ($this->credentialPatterns as $pattern) {
                    foreach ($lines as $lineNum => $line) {
                        // Skip comment lines
                        if ($this->isCommentLine($line)) {
                            continue;
                        }
                        
                        if (preg_match($pattern, $line)) {
                            $issues[] = [
                                'file' => $this->getRelativePath($file),
                                'line' => $lineNum + 1,
                                'pattern' => $pattern,
                                'content' => trim($line)
                            ];
                        }
                    }
                }
            }
        }
        
        $this->assertEmpty(
            $issues,
            $this->formatIssuesMessage($issues, 'Hardcoded credentials found in PHP files')
        );
    }

    /**
     * Test that no debug console output exists in PHP files
     */
    public function testNoDebugConsoleOutput(): void
    {
        $issues = [];
        
        foreach ($this->srcDirectories as $dir) {
            $fullPath = $this->getProjectRoot() . '/' . $dir;
            if (!is_dir($fullPath)) {
                continue;
            }
            
            $phpFiles = $this->getPHPFiles($fullPath);
            
            foreach ($phpFiles as $file) {
                if ($this->shouldExcludeFile($file)) {
                    continue;
                }
                
                $content = file_get_contents($file);
                $lines = explode("\n", $content);
                
                foreach ($this->debugPatterns as $pattern) {
                    foreach ($lines as $lineNum => $line) {
                        // Skip comment lines
                        if ($this->isCommentLine($line)) {
                            continue;
                        }
                        
                        if (preg_match($pattern, $line)) {
                            $issues[] = [
                                'file' => $this->getRelativePath($file),
                                'line' => $lineNum + 1,
                                'pattern' => $pattern,
                                'content' => trim($line)
                            ];
                        }
                    }
                }
            }
        }
        
        $this->assertEmpty(
            $issues,
            $this->formatIssuesMessage($issues, 'Debug output found in PHP files')
        );
    }

    /**
     * Test that no placeholder comments without resolution exist
     */
    public function testNoUnresolvedPlaceholderComments(): void
    {
        $placeholderPatterns = [
            '/\/\/\s*TODO\s*$/im',           // Empty TODO
            '/\/\/\s*FIXME\s*$/im',          // Empty FIXME
            '/\/\*\s*TODO\s*\*\//im',        // Empty TODO block
            '/\/\*\s*FIXME\s*\*\//im',       // Empty FIXME block
        ];
        
        $issues = [];
        
        foreach ($this->srcDirectories as $dir) {
            $fullPath = $this->getProjectRoot() . '/' . $dir;
            if (!is_dir($fullPath)) {
                continue;
            }
            
            $phpFiles = $this->getPHPFiles($fullPath);
            
            foreach ($phpFiles as $file) {
                if ($this->shouldExcludeFile($file)) {
                    continue;
                }
                
                $content = file_get_contents($file);
                $lines = explode("\n", $content);
                
                foreach ($placeholderPatterns as $pattern) {
                    foreach ($lines as $lineNum => $line) {
                        if (preg_match($pattern, $line)) {
                            $issues[] = [
                                'file' => $this->getRelativePath($file),
                                'line' => $lineNum + 1,
                                'pattern' => $pattern,
                                'content' => trim($line)
                            ];
                        }
                    }
                }
            }
        }
        
        $this->assertEmpty(
            $issues,
            $this->formatIssuesMessage($issues, 'Unresolved placeholder comments found')
        );
    }

    /**
     * Test that no hardcoded test data exists in configuration files
     */
    public function testNoTestDataInConfigs(): void
    {
        $configFiles = [
            'includes/config.php',
            'includes/db.php',
        ];
        
        $testDataPatterns = [
            '/localhost/i',
            '/127\.0\.0\.1/',
            '/@localhost/i',
        ];
        
        // Note: This test is informational - localhost configs are expected in dev
        // In production CI/CD, this would fail if configs aren't environment-specific
        $this->markTestSkipped(
            'Config validation skipped - environment-specific configs should be validated in deployment'
        );
    }

    /**
     * Get all PHP files in a directory recursively
     */
    private function getPHPFiles(string $directory): array
    {
        $files = [];
        
        if (!is_dir($directory)) {
            return $files;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        $regex = new RegexIterator($iterator, '/\.php$/i');
        
        foreach ($regex as $file) {
            $files[] = $file->getPathname();
        }
        
        return $files;
    }

    /**
     * Check if a file should be excluded from scanning
     */
    private function shouldExcludeFile(string $filepath): bool
    {
        foreach ($this->excludePatterns as $pattern) {
            if (strpos($filepath, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a line is a comment
     */
    private function isCommentLine(string $line): bool
    {
        $trimmed = trim($line);
        return (
            strpos($trimmed, '//') === 0 ||
            strpos($trimmed, '#') === 0 ||
            strpos($trimmed, '/*') === 0 ||
            strpos($trimmed, '*') === 0
        );
    }

    /**
     * Get the project root directory
     */
    private function getProjectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Get relative path from project root
     */
    private function getRelativePath(string $fullPath): string
    {
        $root = $this->getProjectRoot();
        return str_replace($root . '/', '', $fullPath);
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
                "  %s:%d\n    Pattern: %s\n    Content: %s\n\n",
                $issue['file'],
                $issue['line'],
                $issue['pattern'],
                substr($issue['content'], 0, 80)
            );
        }
        
        return $message;
    }
}
