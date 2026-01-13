<?php
/**
 * PSR-4 Autoloader
 * 
 * Handles automatic loading of classes based on namespace
 */

spl_autoload_register(function ($class) {
    // Base directories for namespaces
    $namespaces = [
        'Core\\' => __DIR__ . '/../core/',
        'App\\' => __DIR__ . '/../',
        'Model\\' => __DIR__ . '/../model/',
        'ViewModel\\' => __DIR__ . '/../ViewModel/',
        'View\\' => __DIR__ . '/../View/'
    ];
    
    // Go through each namespace
    foreach ($namespaces as $prefix => $baseDir) {
        // Does the class use this namespace prefix?
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        
        // Get the relative class name
        $relativeClass = substr($class, $len);
        
        // Replace namespace separators with directory separators
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        
        // If the file exists, require it
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
    
    // Special handling for App namespace subdirectories
    if (strpos($class, 'App\\') === 0) {
        // Remove App\ prefix
        $relativeClass = substr($class, 4);
        
        // Try various locations
        $locations = [
            __DIR__ . '/../' . str_replace('\\', '/', $relativeClass) . '.php',
            __DIR__ . '/../app/' . str_replace('\\', '/', $relativeClass) . '.php',
            __DIR__ . '/../core/' . str_replace('\\', '/', $relativeClass) . '.php'
        ];
        
        foreach ($locations as $file) {
            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }
});

// Include global functions
$globalFiles = [
    __DIR__ . '/functions.php',
    __DIR__ . '/../core/helpers.php'
];

foreach ($globalFiles as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}

// Define global logger function if not already defined
if (!function_exists('logger')) {
    function logger() {
        static $logger = null;
        if ($logger === null) {
            $logger = new \Core\Services\LogService();
        }
        return $logger;
    }
}