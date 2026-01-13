<?php
/**
 * Database.php - PDO Database Connection Manager for SafeShift EHR
 * 
 * Provides a secure, singleton PDO database connection with features:
 * - Connection pooling awareness
 * - Prepared statement enforcement
 * - Error handling without credential leaks
 * - Query logging for debugging
 * - Transaction support
 * 
 * @package    SafeShift\Model\Core
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Model\Core;

use Model\Config\DatabaseConfig;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Database connection manager
 * 
 * Implements singleton pattern for database connection management
 * with enhanced security and logging features.
 */
final class Database
{
    /** @var PDO|null Database connection instance */
    private ?PDO $connection = null;
    
    /** @var DatabaseConfig Configuration instance */
    private DatabaseConfig $config;
    
    /** @var bool Query logging enabled */
    private bool $loggingEnabled = false;
    
    /** @var array<int, array{query: string, params: array, time: float}> Query log */
    private array $queryLog = [];
    
    /** @var int Transaction depth counter */
    private int $transactionDepth = 0;
    
    /** @var self|null Singleton instance */
    private static ?self $instance = null;

    /**
     * Private constructor for singleton pattern
     */
    private function __construct()
    {
        $this->config = DatabaseConfig::getInstance();
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
     * Get PDO connection instance
     * 
     * @return PDO
     * @throws PDOException If connection fails
     */
    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }

    /**
     * Establish database connection
     * 
     * @throws PDOException If connection fails (sanitized message)
     */
    private function connect(): void
    {
        try {
            $this->connection = new PDO(
                $this->config->getDsn(),
                $this->config->getUsername(),
                $this->config->getPassword(),
                $this->config->getOptions()
            );
        } catch (PDOException $e) {
            // Log the actual error securely
            $this->logError('Database connection failed', $e);
            
            // Throw sanitized exception (no credentials)
            throw new PDOException(
                'Database connection failed. Please contact system administrator.',
                (int) $e->getCode()
            );
        }
    }

    /**
     * Execute a query with prepared statement
     * 
     * @param string $sql SQL query with placeholders
     * @param array<string|int, mixed> $params Query parameters
     * @return PDOStatement
     * @throws PDOException If query fails
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $startTime = microtime(true);
        
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            
            $this->logQuery($sql, $params, microtime(true) - $startTime);
            
            return $stmt;
        } catch (PDOException $e) {
            $this->logError('Query execution failed', $e, ['sql' => $sql]);
            throw $this->sanitizeException($e);
        }
    }

    /**
     * Execute a query and return all results
     * 
     * @param string $sql SQL query with placeholders
     * @param array<string|int, mixed> $params Query parameters
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Execute a query and return single row
     * 
     * @param string $sql SQL query with placeholders
     * @param array<string|int, mixed> $params Query parameters
     * @return array<string, mixed>|false
     */
    public function fetchOne(string $sql, array $params = []): array|false
    {
        return $this->query($sql, $params)->fetch();
    }

    /**
     * Execute a query and return single column value
     * 
     * @param string $sql SQL query with placeholders
     * @param array<string|int, mixed> $params Query parameters
     * @return mixed
     */
    public function fetchColumn(string $sql, array $params = []): mixed
    {
        return $this->query($sql, $params)->fetchColumn();
    }

    /**
     * Execute an INSERT, UPDATE, or DELETE query
     * 
     * @param string $sql SQL query with placeholders
     * @param array<string|int, mixed> $params Query parameters
     * @return int Number of affected rows
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Insert a row and return the last insert ID
     * 
     * @param string $table Table name
     * @param array<string, mixed> $data Column => value pairs
     * @return string Last insert ID
     */
    public function insert(string $table, array $data): string
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":$col", $columns);
        
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($table),
            implode(', ', array_map([$this, 'quoteIdentifier'], $columns)),
            implode(', ', $placeholders)
        );
        
        $this->query($sql, $data);
        
        return $this->getConnection()->lastInsertId();
    }

    /**
     * Update rows in a table
     * 
     * @param string $table Table name
     * @param array<string, mixed> $data Column => value pairs to update
     * @param string $where WHERE clause (without WHERE keyword)
     * @param array<string|int, mixed> $whereParams Parameters for WHERE clause
     * @return int Number of affected rows
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $setClauses = [];
        foreach ($data as $column => $value) {
            $setClauses[] = $this->quoteIdentifier($column) . ' = :set_' . $column;
        }
        
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $this->quoteIdentifier($table),
            implode(', ', $setClauses),
            $where
        );
        
        // Prefix data params to avoid collision with where params
        $params = [];
        foreach ($data as $column => $value) {
            $params['set_' . $column] = $value;
        }
        $params = array_merge($params, $whereParams);
        
        return $this->execute($sql, $params);
    }

    /**
     * Delete rows from a table
     * 
     * @param string $table Table name
     * @param string $where WHERE clause (without WHERE keyword)
     * @param array<string|int, mixed> $params Parameters for WHERE clause
     * @return int Number of affected rows
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $this->quoteIdentifier($table),
            $where
        );
        
        return $this->execute($sql, $params);
    }

    /**
     * Begin a transaction
     * 
     * Supports nested transactions via savepoints
     * 
     * @return bool
     */
    public function beginTransaction(): bool
    {
        if ($this->transactionDepth === 0) {
            $this->getConnection()->beginTransaction();
        } else {
            $this->getConnection()->exec("SAVEPOINT trans_{$this->transactionDepth}");
        }
        
        $this->transactionDepth++;
        return true;
    }

    /**
     * Commit a transaction
     * 
     * @return bool
     */
    public function commit(): bool
    {
        if ($this->transactionDepth <= 0) {
            return false;
        }
        
        $this->transactionDepth--;
        
        if ($this->transactionDepth === 0) {
            return $this->getConnection()->commit();
        } else {
            $this->getConnection()->exec("RELEASE SAVEPOINT trans_{$this->transactionDepth}");
            return true;
        }
    }

    /**
     * Rollback a transaction
     * 
     * @return bool
     */
    public function rollback(): bool
    {
        if ($this->transactionDepth <= 0) {
            return false;
        }
        
        $this->transactionDepth--;
        
        if ($this->transactionDepth === 0) {
            return $this->getConnection()->rollBack();
        } else {
            $this->getConnection()->exec("ROLLBACK TO SAVEPOINT trans_{$this->transactionDepth}");
            return true;
        }
    }

    /**
     * Execute callback within a transaction
     * 
     * @template T
     * @param callable(): T $callback Callback to execute
     * @return T Result of callback
     * @throws \Throwable If callback throws, transaction is rolled back
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        
        try {
            $result = $callback();
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Check if currently in a transaction
     * 
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->transactionDepth > 0;
    }

    /**
     * Get last insert ID
     * 
     * @return string
     */
    public function lastInsertId(): string
    {
        return $this->getConnection()->lastInsertId();
    }

    /**
     * Quote an identifier (table or column name)
     * 
     * @param string $identifier
     * @return string
     */
    public function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * Enable query logging
     * 
     * @param bool $enabled
     * @return self
     */
    public function enableLogging(bool $enabled = true): self
    {
        $this->loggingEnabled = $enabled;
        return $this;
    }

    /**
     * Get query log
     * 
     * @return array<int, array{query: string, params: array, time: float}>
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * Clear query log
     * 
     * @return self
     */
    public function clearQueryLog(): self
    {
        $this->queryLog = [];
        return $this;
    }

    /**
     * Log a query
     * 
     * @param string $sql SQL query
     * @param array<string|int, mixed> $params Query parameters
     * @param float $time Execution time in seconds
     */
    private function logQuery(string $sql, array $params, float $time): void
    {
        if (!$this->loggingEnabled) {
            return;
        }
        
        $this->queryLog[] = [
            'query' => $sql,
            'params' => $params,
            'time' => $time,
        ];
    }

    /**
     * Log an error securely (no PHI or credentials)
     * 
     * @param string $message Error message
     * @param \Throwable $exception Original exception
     * @param array<string, mixed> $context Additional context
     */
    private function logError(string $message, \Throwable $exception, array $context = []): void
    {
        // Remove any sensitive data from context
        unset($context['password'], $context['ssn'], $context['phi']);
        
        error_log(sprintf(
            '[Database Error] %s: %s | Code: %s | Context: %s',
            $message,
            $exception->getMessage(),
            $exception->getCode(),
            json_encode($context)
        ));
    }

    /**
     * Sanitize exception to prevent credential leaks
     * 
     * @param PDOException $e Original exception
     * @return PDOException Sanitized exception
     */
    private function sanitizeException(PDOException $e): PDOException
    {
        $message = $e->getMessage();
        
        // Remove potential credential leaks from message
        $message = preg_replace(
            '/using password: (YES|NO)/i',
            'using password: ***',
            $message
        );
        
        // Remove potential connection string details
        $message = preg_replace(
            '/host=[\w\.\-]+/i',
            'host=***',
            $message
        );
        
        return new PDOException($message, (int) $e->getCode());
    }

    /**
     * Check database connection health
     * 
     * @return bool
     */
    public function isConnected(): bool
    {
        try {
            $this->getConnection()->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Disconnect from database
     */
    public function disconnect(): void
    {
        $this->connection = null;
        $this->transactionDepth = 0;
    }

    /**
     * Reconnect to database
     * 
     * @return self
     */
    public function reconnect(): self
    {
        $this->disconnect();
        $this->connect();
        return $this;
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
