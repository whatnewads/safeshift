#!/usr/bin/env php
<?php
/**
 * CI/CD Script: Filler Pattern Detection
 * 
 * Standalone script to detect placeholder/filler data in the codebase.
 * Can be run in CI/CD pipelines to prevent filler data from being committed.
 * 
 * Exit codes:
 *   0 = No issues found
 *   1 = Filler patterns detected
 *   2 = Script error
 * 
 * Usage:
 *   php scripts/check-filler-patterns.php [--verbose] [--json] [--strict]
 * 
 * Options:
 *   --verbose  Show detailed output including file contents
 *   --json     Output results as JSON (for CI integration)
 *   --strict   Treat warnings as errors
 * 
 * @package SafeShift/CI
 */

declare(strict_types=1);

// Parse command line options
$options = getopt('', ['verbose', 'json', 'strict', 'help']);
$verbose = isset($options['verbose']);
$jsonOutput = isset($options['json']);
$strictMode = isset($options['strict']);

if (isset($options['help'])) {
    printHelp();
    exit(0);
}

// Configuration
$config = [
    // Directories to scan
    'srcDirectories' => [
        'api/',
        'ViewModel/',
        'model/',
        'core/',
        'includes/',
        'src/app/',
    ],
    
    // Directories to exclude
    'excludePatterns' => [
        'vendor/',
        'tests/',
        'node_modules/',
        '.git/',
        'cache/',
        'logs/',
        'sessions/',
        'backups/',
        '__tests__/',
        '__mocks__/',
    ],
    
    // File extensions to scan
    'fileExtensions' => ['php', 'ts', 'tsx', 'js', 'jsx'],
];

// Pattern definitions with severity levels
$patterns = [
    'critical' => [
        // Hardcoded credentials
        [
            'name' => 'Hardcoded password',
            'pattern' => '/password\s*=\s*[\'"](?:password|123456|admin|test|secret)[\'"]?/i',
        ],
        [
            'name' => 'Hardcoded API key',
            'pattern' => '/api_key\s*=\s*[\'"][a-zA-Z0-9]{20,}[\'"]/i',
        ],
        [
            'name' => 'Hardcoded secret',
            'pattern' => '/secret_key\s*=\s*[\'"][a-zA-Z0-9]{20,}[\'"]/i',
        ],
        [
            'name' => 'Bearer token',
            'pattern' => '/bearer\s+[a-zA-Z0-9]{32,}/i',
        ],
    ],
    
    'error' => [
        // Lorem ipsum
        [
            'name' => 'Lorem ipsum',
            'pattern' => '/lorem\s+ipsum/i',
        ],
        [
            'name' => 'Dolor sit amet',
            'pattern' => '/dolor\s+sit\s+amet/i',
        ],
        
        // Test emails
        [
            'name' => 'Test email (test@test.com)',
            'pattern' => '/test@test\.com/i',
        ],
        [
            'name' => 'Example email',
            'pattern' => '/example@example\.com/i',
        ],
        [
            'name' => 'Foo bar email',
            'pattern' => '/foo@bar\.com/i',
        ],
        
        // Test phone numbers
        [
            'name' => '555 phone number',
            'pattern' => '/555-\d{3}-\d{4}/',
        ],
        [
            'name' => '123-456-7890 phone',
            'pattern' => '/123-456-7890/',
        ],
        
        // Test names in strings
        [
            'name' => 'John Doe test name',
            'pattern' => '/[\'"]John\s+Doe[\'"]/i',
        ],
        [
            'name' => 'Jane Doe test name',
            'pattern' => '/[\'"]Jane\s+Doe[\'"]/i',
        ],
        [
            'name' => 'Test User name',
            'pattern' => '/[\'"]Test\s+User[\'"]/i',
        ],
        [
            'name' => 'Test Patient name',
            'pattern' => '/[\'"]Test\s+Patient[\'"]/i',
        ],
        
        // Test SSNs
        [
            'name' => '000-00-0000 SSN',
            'pattern' => '/000-00-0000/',
        ],
        [
            'name' => '123-45-6789 SSN',
            'pattern' => '/123-45-6789/',
        ],
        
        // Hardcoded test IDs
        [
            'name' => 'Hardcoded user_id = 123',
            'pattern' => '/user_id\s*=\s*[\'"]?123[\'"]?(?![0-9])/',
        ],
        [
            'name' => 'Hardcoded patient_id = 456',
            'pattern' => '/patient_id\s*=\s*[\'"]?456[\'"]?(?![0-9])/',
        ],
    ],
    
    'warning' => [
        // Debug output (PHP)
        [
            'name' => 'var_dump()',
            'pattern' => '/\bvar_dump\s*\(/i',
        ],
        [
            'name' => 'print_r()',
            'pattern' => '/\bprint_r\s*\(/i',
        ],
        [
            'name' => 'debug_print_backtrace()',
            'pattern' => '/\bdebug_print_backtrace\s*\(/i',
        ],
        
        // Debug output (JavaScript)
        [
            'name' => 'console.log()',
            'pattern' => '/console\.log\s*\(/i',
        ],
        [
            'name' => 'console.debug()',
            'pattern' => '/console\.debug\s*\(/i',
        ],
        
        // Placeholder markers
        [
            'name' => 'XXX placeholder',
            'pattern' => '/XXX[^X]/i',
        ],
        [
            'name' => 'Empty TODO',
            'pattern' => '/\/\/\s*TODO\s*$/m',
        ],
        [
            'name' => 'Empty FIXME',
            'pattern' => '/\/\/\s*FIXME\s*$/m',
        ],
        
        // Test addresses
        [
            'name' => '123 Main St address',
            'pattern' => '/123\s+Main\s+St/i',
        ],
    ],
];

// Track issues
$issues = [
    'critical' => [],
    'error' => [],
    'warning' => [],
];

// Get project root
$projectRoot = realpath(__DIR__ . '/..');
if (!$projectRoot) {
    outputError('Could not determine project root directory');
    exit(2);
}

// Run the scan
outputInfo('SafeShift EHR - Filler Pattern Detection');
outputInfo('=' . str_repeat('=', 45));
outputInfo('Scanning directories...');

$filesScanned = 0;
$startTime = microtime(true);

foreach ($config['srcDirectories'] as $dir) {
    $fullPath = $projectRoot . '/' . $dir;
    if (!is_dir($fullPath)) {
        continue;
    }
    
    $files = getSourceFiles($fullPath, $config['fileExtensions'], $config['excludePatterns']);
    $filesScanned += count($files);
    
    foreach ($files as $file) {
        scanFile($file, $patterns, $issues, $projectRoot);
    }
}

$endTime = microtime(true);
$duration = round(($endTime - $startTime) * 1000);

// Output results
if ($jsonOutput) {
    outputJson($issues, $filesScanned, $duration);
} else {
    outputResults($issues, $filesScanned, $duration, $verbose);
}

// Determine exit code
$exitCode = 0;

if (count($issues['critical']) > 0) {
    $exitCode = 1;
} elseif (count($issues['error']) > 0) {
    $exitCode = 1;
} elseif ($strictMode && count($issues['warning']) > 0) {
    $exitCode = 1;
}

exit($exitCode);

// ============================================================================
// Functions
// ============================================================================

/**
 * Get all source files in a directory recursively
 */
function getSourceFiles(string $directory, array $extensions, array $excludePatterns): array
{
    $files = [];
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        
        $path = $file->getPathname();
        
        // Check exclusions
        $excluded = false;
        foreach ($excludePatterns as $pattern) {
            if (strpos($path, $pattern) !== false) {
                $excluded = true;
                break;
            }
        }
        
        if ($excluded) {
            continue;
        }
        
        // Check extension
        $ext = strtolower($file->getExtension());
        if (in_array($ext, $extensions)) {
            $files[] = $path;
        }
    }
    
    return $files;
}

/**
 * Scan a file for patterns
 */
function scanFile(string $filepath, array $patterns, array &$issues, string $projectRoot): void
{
    $content = file_get_contents($filepath);
    if ($content === false) {
        return;
    }
    
    $lines = explode("\n", $content);
    $relativePath = str_replace($projectRoot . '/', '', $filepath);
    
    foreach (['critical', 'error', 'warning'] as $severity) {
        foreach ($patterns[$severity] as $patternDef) {
            foreach ($lines as $lineNum => $line) {
                // Skip comment lines for non-critical issues
                if ($severity !== 'critical' && isCommentLine($line)) {
                    continue;
                }
                
                if (preg_match($patternDef['pattern'], $line)) {
                    $issues[$severity][] = [
                        'file' => $relativePath,
                        'line' => $lineNum + 1,
                        'pattern' => $patternDef['name'],
                        'content' => trim($line),
                    ];
                }
            }
        }
    }
}

/**
 * Check if a line is a comment
 */
function isCommentLine(string $line): bool
{
    $trimmed = trim($line);
    return (
        strpos($trimmed, '//') === 0 ||
        strpos($trimmed, '#') === 0 ||
        strpos($trimmed, '/*') === 0 ||
        strpos($trimmed, '*') === 0 ||
        strpos($trimmed, '<!--') === 0
    );
}

/**
 * Output results to console
 */
function outputResults(array $issues, int $filesScanned, int $duration, bool $verbose): void
{
    global $strictMode;
    
    echo "\n";
    echo "Scan completed in {$duration}ms\n";
    echo "Files scanned: {$filesScanned}\n";
    echo "\n";
    
    $totalIssues = count($issues['critical']) + count($issues['error']) + count($issues['warning']);
    
    if ($totalIssues === 0) {
        echo "\033[32m✓ No filler patterns detected\033[0m\n";
        return;
    }
    
    // Critical issues
    if (count($issues['critical']) > 0) {
        echo "\033[31m✗ CRITICAL ISSUES (" . count($issues['critical']) . "):\033[0m\n";
        echo str_repeat('-', 60) . "\n";
        foreach ($issues['critical'] as $issue) {
            echo "  \033[31mCRITICAL:\033[0m {$issue['file']}:{$issue['line']}\n";
            echo "    Pattern: {$issue['pattern']}\n";
            if ($verbose) {
                echo "    Content: " . substr($issue['content'], 0, 60) . "\n";
            }
        }
        echo "\n";
    }
    
    // Error issues
    if (count($issues['error']) > 0) {
        echo "\033[31m✗ ERRORS (" . count($issues['error']) . "):\033[0m\n";
        echo str_repeat('-', 60) . "\n";
        foreach ($issues['error'] as $issue) {
            echo "  \033[31mERROR:\033[0m {$issue['file']}:{$issue['line']}\n";
            echo "    Pattern: {$issue['pattern']}\n";
            if ($verbose) {
                echo "    Content: " . substr($issue['content'], 0, 60) . "\n";
            }
        }
        echo "\n";
    }
    
    // Warning issues
    if (count($issues['warning']) > 0) {
        $warningColor = $strictMode ? "\033[31m" : "\033[33m";
        $warningIcon = $strictMode ? "✗" : "⚠";
        echo "{$warningColor}{$warningIcon} WARNINGS (" . count($issues['warning']) . "):\033[0m\n";
        echo str_repeat('-', 60) . "\n";
        foreach ($issues['warning'] as $issue) {
            echo "  {$warningColor}WARNING:\033[0m {$issue['file']}:{$issue['line']}\n";
            echo "    Pattern: {$issue['pattern']}\n";
            if ($verbose) {
                echo "    Content: " . substr($issue['content'], 0, 60) . "\n";
            }
        }
        echo "\n";
    }
    
    // Summary
    echo str_repeat('=', 60) . "\n";
    echo "Summary:\n";
    echo "  Critical: " . count($issues['critical']) . "\n";
    echo "  Errors:   " . count($issues['error']) . "\n";
    echo "  Warnings: " . count($issues['warning']) . "\n";
    echo "  Total:    {$totalIssues}\n";
    echo "\n";
    
    if (count($issues['critical']) > 0 || count($issues['error']) > 0) {
        echo "\033[31m✗ Build FAILED: Filler patterns detected\033[0m\n";
    } elseif ($strictMode && count($issues['warning']) > 0) {
        echo "\033[31m✗ Build FAILED: Warnings treated as errors (--strict mode)\033[0m\n";
    } else {
        echo "\033[33m⚠ Build PASSED with warnings\033[0m\n";
    }
}

/**
 * Output results as JSON
 */
function outputJson(array $issues, int $filesScanned, int $duration): void
{
    global $strictMode;
    
    $totalIssues = count($issues['critical']) + count($issues['error']) + count($issues['warning']);
    $passed = count($issues['critical']) === 0 && count($issues['error']) === 0;
    
    if ($strictMode) {
        $passed = $passed && count($issues['warning']) === 0;
    }
    
    $result = [
        'passed' => $passed,
        'duration_ms' => $duration,
        'files_scanned' => $filesScanned,
        'summary' => [
            'critical' => count($issues['critical']),
            'errors' => count($issues['error']),
            'warnings' => count($issues['warning']),
            'total' => $totalIssues,
        ],
        'issues' => $issues,
    ];
    
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
}

/**
 * Output info message (only in non-JSON mode)
 */
function outputInfo(string $message): void
{
    global $jsonOutput;
    if (!$jsonOutput) {
        echo $message . "\n";
    }
}

/**
 * Output error message
 */
function outputError(string $message): void
{
    global $jsonOutput;
    if ($jsonOutput) {
        echo json_encode(['error' => $message]) . "\n";
    } else {
        echo "\033[31mError: {$message}\033[0m\n";
    }
}

/**
 * Print help message
 */
function printHelp(): void
{
    echo <<<HELP
SafeShift EHR - Filler Pattern Detection Script

Usage:
  php scripts/check-filler-patterns.php [options]

Options:
  --verbose     Show detailed output including file contents
  --json        Output results as JSON (for CI integration)
  --strict      Treat warnings as errors
  --help        Show this help message

Exit Codes:
  0  No issues found (or only warnings in non-strict mode)
  1  Filler patterns or errors detected
  2  Script error

Examples:
  php scripts/check-filler-patterns.php
  php scripts/check-filler-patterns.php --verbose
  php scripts/check-filler-patterns.php --json --strict

HELP;
}
