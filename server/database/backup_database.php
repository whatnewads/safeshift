<?php
/**
 * Database Backup Script
 * Creates a timestamped backup of the current database before migration
 */

require_once __DIR__ . '/../includes/config.php';

// Ensure script is run from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Create backup directory if it doesn't exist
$backupDir = __DIR__ . '/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Generate backup filename with timestamp
$timestamp = date('Y-m-d_H-i-s');
$backupFile = $backupDir . '/safeshift_backup_' . $timestamp . '.sql';

echo "Starting database backup...\n";
echo "Database: " . DB_NAME . "\n";
echo "Backup file: $backupFile\n\n";

// Build mysqldump command
$command = sprintf(
    'mysqldump --user=%s --password=%s --host=%s --port=%s --single-transaction --routines --triggers --events %s > %s 2>&1',
    escapeshellarg(DB_USER),
    escapeshellarg(DB_PASS),
    escapeshellarg(DB_HOST),
    escapeshellarg(DB_PORT),
    escapeshellarg(DB_NAME),
    escapeshellarg($backupFile)
);

// Execute backup
$output = [];
$returnCode = null;
exec($command, $output, $returnCode);

if ($returnCode === 0) {
    $fileSize = filesize($backupFile);
    $fileSizeMB = round($fileSize / 1024 / 1024, 2);
    echo "✓ Backup completed successfully!\n";
    echo "  File size: {$fileSizeMB} MB\n";
    echo "  Location: $backupFile\n";
    
    // Verify backup integrity
    if ($fileSize > 0) {
        echo "✓ Backup file verified.\n";
    } else {
        echo "⚠ Warning: Backup file is empty!\n";
        exit(1);
    }
} else {
    echo "✗ Backup failed!\n";
    if (!empty($output)) {
        echo "Error output:\n";
        echo implode("\n", $output) . "\n";
    }
    exit(1);
}

// Keep only last 5 backups
echo "\nCleaning old backups...\n";
$backups = glob($backupDir . '/safeshift_backup_*.sql');
if (count($backups) > 5) {
    // Sort by modification time
    usort($backups, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    
    // Remove oldest backups
    $toDelete = count($backups) - 5;
    for ($i = 0; $i < $toDelete; $i++) {
        if (unlink($backups[$i])) {
            echo "  Deleted: " . basename($backups[$i]) . "\n";
        }
    }
}

echo "\n✓ Backup process completed.\n";
echo "You can restore this backup using:\n";
echo "mysql -u " . DB_USER . " -p " . DB_NAME . " < $backupFile\n";