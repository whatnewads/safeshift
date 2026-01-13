<?php
/**
 * Migration Path Verification Script
 * Tests that all paths resolve correctly after the server/ migration
 */

echo "======================================\n";
echo "MIGRATION PATH VERIFICATION SCRIPT\n";
echo "======================================\n\n";

$errors = [];
$warnings = [];
$successes = [];

// 1. Test current working directory
echo "1. Current Working Directory:\n";
echo "   CWD: " . getcwd() . "\n";
echo "   __DIR__: " . __DIR__ . "\n\n";

// 2. Test .env file location
echo "2. Testing .env file location:\n";
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $successes[] = ".env file found at: $envPath";
    echo "   [OK] .env found at: $envPath\n";
} else {
    $errors[] = ".env file NOT found at: $envPath";
    echo "   [ERROR] .env NOT found at: $envPath\n";
}

// 3. Test config.php
echo "\n3. Testing config.php:\n";
$configPath = __DIR__ . '/../includes/config.php';
if (file_exists($configPath)) {
    $successes[] = "config.php found at: $configPath";
    echo "   [OK] config.php found at: $configPath\n";
    
    // Try to load config
    try {
        require_once $configPath;
        if (defined('DB_HOST')) {
            $successes[] = "Config constants loaded (DB_HOST defined)";
            echo "   [OK] Config constants loaded (DB_HOST: " . DB_HOST . ")\n";
        } else {
            $warnings[] = "Config loaded but DB_HOST not defined";
            echo "   [WARN] Config loaded but DB_HOST not defined\n";
        }
    } catch (Exception $e) {
        $errors[] = "Failed to load config.php: " . $e->getMessage();
        echo "   [ERROR] Failed to load config.php: " . $e->getMessage() . "\n";
    }
} else {
    $errors[] = "config.php NOT found at: $configPath";
    echo "   [ERROR] config.php NOT found at: $configPath\n";
}

// 4. Test core directory structure
echo "\n4. Testing core directory structure:\n";
$coreDirectories = [
    'Core/Core' => __DIR__ . '/../core/Core/',
    'Core/Infrastructure/Database' => __DIR__ . '/../core/Infrastructure/Database/',
    'Core/Infrastructure/ErrorHandling' => __DIR__ . '/../core/Infrastructure/ErrorHandling/',
    'Core/Infrastructure/Logging' => __DIR__ . '/../core/Infrastructure/Logging/',
    'Core/Services' => __DIR__ . '/../core/Services/',
    'Core/Repositories' => __DIR__ . '/../core/Repositories/',
];

foreach ($coreDirectories as $name => $path) {
    if (is_dir($path)) {
        $successes[] = "Directory exists: $name";
        echo "   [OK] $name exists\n";
    } else {
        $errors[] = "Directory missing: $name at $path";
        echo "   [ERROR] $name missing at: $path\n";
    }
}

// 5. Test ViewModel directory
echo "\n5. Testing ViewModel directory:\n";
$viewModelPath = __DIR__ . '/../ViewModel/';
if (is_dir($viewModelPath)) {
    $successes[] = "ViewModel directory exists";
    echo "   [OK] ViewModel directory exists\n";
    
    // Check for Core subdirectory
    if (is_dir($viewModelPath . 'Core/')) {
        $successes[] = "ViewModel/Core directory exists";
        echo "   [OK] ViewModel/Core directory exists\n";
    } else {
        $errors[] = "ViewModel/Core directory missing";
        echo "   [ERROR] ViewModel/Core directory missing\n";
    }
} else {
    $errors[] = "ViewModel directory missing at: $viewModelPath";
    echo "   [ERROR] ViewModel directory missing\n";
}

// 6. Test model directory
echo "\n6. Testing model directory:\n";
$modelPath = __DIR__ . '/../model/';
if (is_dir($modelPath)) {
    $successes[] = "model directory exists";
    echo "   [OK] model directory exists\n";
} else {
    $errors[] = "model directory missing at: $modelPath";
    echo "   [ERROR] model directory missing\n";
}

// 7. Test includes directory
echo "\n7. Testing includes directory:\n";
$includesFiles = [
    'bootstrap.php',
    'config.php',
    'db.php',
    'auth.php',
    'auth_global.php',
    'validation.php',
    'sanitization.php',
    'log_functions.php',
];

$includesPath = __DIR__ . '/../includes/';
foreach ($includesFiles as $file) {
    $filePath = $includesPath . $file;
    if (file_exists($filePath)) {
        $successes[] = "includes/$file exists";
        echo "   [OK] $file exists\n";
    } else {
        $warnings[] = "includes/$file missing";
        echo "   [WARN] $file missing\n";
    }
}

// 8. Test logs directory
echo "\n8. Testing logs directory:\n";
$logsPath = __DIR__ . '/../logs/';
if (is_dir($logsPath)) {
    $successes[] = "logs directory exists";
    echo "   [OK] logs directory exists\n";
    if (is_writable($logsPath)) {
        $successes[] = "logs directory is writable";
        echo "   [OK] logs directory is writable\n";
    } else {
        $warnings[] = "logs directory is not writable";
        echo "   [WARN] logs directory is not writable\n";
    }
} else {
    $warnings[] = "logs directory missing (will be created automatically)";
    echo "   [WARN] logs directory missing (will be created automatically)\n";
}

// 9. Test API directory structure
echo "\n9. Testing API directory structure:\n";
$apiFiles = [
    'api/v1/index.php' => __DIR__ . '/../api/v1/index.php',
    'api/v1/auth.php' => __DIR__ . '/../api/v1/auth.php',
    'api/v1/patients.php' => __DIR__ . '/../api/v1/patients.php',
    'api/v1/encounters.php' => __DIR__ . '/../api/v1/encounters.php',
    'api/v1/dashboard.php' => __DIR__ . '/../api/v1/dashboard.php',
    'api/v1/notifications.php' => __DIR__ . '/../api/v1/notifications.php',
];

foreach ($apiFiles as $name => $path) {
    if (file_exists($path)) {
        $successes[] = "$name exists";
        echo "   [OK] $name exists\n";
    } else {
        $warnings[] = "$name missing";
        echo "   [WARN] $name missing\n";
    }
}

// 10. Test public entry points
echo "\n10. Testing public entry points:\n";
$publicFiles = [
    'public/router.php' => __DIR__ . '/../public/router.php',
    'public/index.php' => __DIR__ . '/../public/index.php',
    'public/otp.php' => __DIR__ . '/../public/otp.php',
];

foreach ($publicFiles as $name => $path) {
    if (file_exists($path)) {
        $successes[] = "$name exists";
        echo "   [OK] $name exists\n";
    } else {
        $errors[] = "$name missing";
        echo "   [ERROR] $name missing\n";
    }
}

// 11. Test key class files exist
echo "\n11. Testing key class files:\n";
$classFiles = [
    'Core\\Infrastructure\\Database\\DatabaseConnection' => __DIR__ . '/../core/Infrastructure/Database/DatabaseConnection.php',
    'Core\\Infrastructure\\ErrorHandling\\ErrorHandler' => __DIR__ . '/../core/Infrastructure/ErrorHandling/ErrorHandler.php',
    'Core\\Infrastructure\\Logging\\FileLogger' => __DIR__ . '/../core/Infrastructure/Logging/FileLogger.php',
    'Core\\Infrastructure\\Logging\\SecureLogger' => __DIR__ . '/../core/Infrastructure/Logging/SecureLogger.php',
    'Core\\Services\\LogService' => __DIR__ . '/../core/Services/LogService.php',
    'ViewModel\\Core\\ApiResponse' => __DIR__ . '/../ViewModel/Core/ApiResponse.php',
    'ViewModel\\Core\\BaseViewModel' => __DIR__ . '/../ViewModel/Core/BaseViewModel.php',
];

foreach ($classFiles as $class => $path) {
    if (file_exists($path)) {
        $successes[] = "Class file $class exists";
        echo "   [OK] $class\n";
    } else {
        $errors[] = "Class file $class missing at: $path";
        echo "   [ERROR] $class missing at: $path\n";
    }
}

// 12. Test bootstrap loading
echo "\n12. Testing bootstrap loading:\n";
$bootstrapPath = __DIR__ . '/../includes/bootstrap.php';
if (file_exists($bootstrapPath)) {
    echo "   [OK] bootstrap.php exists\n";
    
    // Try to require bootstrap
    try {
        // Set up minimal environment
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HOST'] = 'localhost';
        
        require_once $bootstrapPath;
        
        $successes[] = "Bootstrap loaded successfully";
        echo "   [OK] Bootstrap loaded successfully\n";
        
        // Check if key components were initialized
        if (isset($GLOBALS['db']) && $GLOBALS['db'] !== null) {
            $successes[] = "Database connection established";
            echo "   [OK] Database connection established\n";
        } else if (isset($GLOBALS['db_available']) && !$GLOBALS['db_available']) {
            $warnings[] = "Database not available (expected in test environment)";
            echo "   [WARN] Database not available (expected in test environment)\n";
        }
        
        if (isset($GLOBALS['logger'])) {
            $successes[] = "Logger initialized";
            echo "   [OK] Logger initialized\n";
        }
        
        if (session_status() === PHP_SESSION_ACTIVE) {
            $successes[] = "Session started";
            echo "   [OK] Session started (ID: " . session_id() . ")\n";
        }
        
    } catch (Exception $e) {
        $errors[] = "Bootstrap loading failed: " . $e->getMessage();
        echo "   [ERROR] Bootstrap loading failed: " . $e->getMessage() . "\n";
    } catch (Error $e) {
        $errors[] = "Bootstrap loading error: " . $e->getMessage();
        echo "   [ERROR] Bootstrap loading error: " . $e->getMessage() . "\n";
    }
} else {
    $errors[] = "bootstrap.php missing";
    echo "   [ERROR] bootstrap.php missing\n";
}

// Summary
echo "\n======================================\n";
echo "SUMMARY\n";
echo "======================================\n";
echo "Successes: " . count($successes) . "\n";
echo "Warnings:  " . count($warnings) . "\n";
echo "Errors:    " . count($errors) . "\n";

if (count($errors) > 0) {
    echo "\nERRORS FOUND:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

if (count($warnings) > 0) {
    echo "\nWARNINGS:\n";
    foreach ($warnings as $warning) {
        echo "  - $warning\n";
    }
}

echo "\n";
if (count($errors) === 0) {
    echo "✓ Migration paths appear to be correctly configured!\n";
    exit(0);
} else {
    echo "✗ Migration has issues that need to be addressed.\n";
    exit(1);
}
