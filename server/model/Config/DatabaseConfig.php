<?php
/**
 * DatabaseConfig.php - Database Configuration for SafeShift EHR
 * 
 * Centralizes database configuration extracted from includes/config.php
 * Uses environment variables for security - never hardcode credentials
 * 
 * @package    SafeShift\Model\Config
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Model\Config;

/**
 * Database configuration class
 * 
 * Provides centralized database configuration management with
 * environment variable support and secure credential handling.
 */
final class DatabaseConfig
{
    /** @var string Database host */
    private string $host;
    
    /** @var string Database name */
    private string $database;
    
    /** @var string Database username */
    private string $username;
    
    /** @var string Database password */
    private string $password;
    
    /** @var string Database character set */
    private string $charset;
    
    /** @var int Database port */
    private int $port;
    
    /** @var string Database driver */
    private string $driver;
    
    /** @var array<string, mixed> PDO connection options */
    private array $options;
    
    /** @var self|null Singleton instance */
    private static ?self $instance = null;

    /**
     * Private constructor for singleton pattern
     */
    private function __construct()
    {
        $this->loadFromEnvironment();
        $this->setDefaultOptions();
    }

    /**
     * Get singleton instance
     * 
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load configuration from environment variables
     * 
     * @throws \RuntimeException If required environment variables are missing
     */
    private function loadFromEnvironment(): void
    {
        $this->host = $this->getEnv('DB_HOST', '127.0.0.1');
        $this->database = $this->getEnv('DB_NAME', '');
        $this->username = $this->getEnv('DB_USER', '');
        $this->password = $this->getEnv('DB_PASS', '');
        $this->charset = $this->getEnv('DB_CHARSET', 'utf8mb4');
        $this->port = (int) $this->getEnv('DB_PORT', '3306');
        $this->driver = $this->getEnv('DB_DRIVER', 'mysql');

        // Validate required configuration
        if (empty($this->database) || empty($this->username)) {
            throw new \RuntimeException(
                'Database configuration incomplete. Check DB_NAME and DB_USER environment variables.'
            );
        }
    }

    /**
     * Set default PDO connection options
     */
    private function setDefaultOptions(): void
    {
        $this->options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_PERSISTENT => false,
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}",
        ];
    }

    /**
     * Get environment variable with fallback
     * 
     * @param string $key Environment variable name
     * @param string $default Default value if not set
     * @return string
     */
    private function getEnv(string $key, string $default = ''): string
    {
        // Check for defined constants first (legacy support)
        if (defined($key)) {
            return (string) constant($key);
        }
        
        // Then check environment variables
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        // Check $_ENV
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        // Check $_SERVER (for Apache SetEnv)
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        
        return $default;
    }

    /**
     * Build PDO DSN string
     * 
     * @return string
     */
    public function getDsn(): string
    {
        return sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $this->driver,
            $this->host,
            $this->port,
            $this->database,
            $this->charset
        );
    }

    /**
     * Get database host
     * 
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Get database name
     * 
     * @return string
     */
    public function getDatabase(): string
    {
        return $this->database;
    }

    /**
     * Get database username
     * 
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Get database password
     * 
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * Get database character set
     * 
     * @return string
     */
    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * Get database port
     * 
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Get PDO connection options
     * 
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Set additional PDO option
     * 
     * @param int $key PDO option constant
     * @param mixed $value Option value
     * @return self
     */
    public function setOption(int $key, mixed $value): self
    {
        $this->options[$key] = $value;
        return $this;
    }

    /**
     * Get full configuration array (without password)
     * 
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return [
            'driver' => $this->driver,
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'charset' => $this->charset,
            // Password intentionally omitted for security
        ];
    }

    /**
     * Prevent cloning of singleton
     */
    private function __clone(): void
    {
    }

    /**
     * Prevent unserialization of singleton
     * 
     * @throws \RuntimeException
     */
    public function __wakeup(): void
    {
        throw new \RuntimeException('Cannot unserialize singleton');
    }
}
