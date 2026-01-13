<?php
/**
 * Base Repository Class
 * 
 * Provides common database operations for all repositories
 */

namespace App\Repositories;

use App\Helpers\UuidHelper;
use PDO;
use PDOException;

abstract class BaseRepository
{
    protected PDO $db;
    protected string $table;
    protected string $primaryKey = 'id';
    
    public function __construct(?PDO $db = null)
    {
        if ($db) {
            $this->db = $db;
        } else {
            $this->db = $this->getDatabase();
        }
    }
    
    /**
     * Get database connection
     * @return PDO
     */
    protected function getDatabase()
    {
        // Include config if not already loaded
        if (!defined('CONFIG_LOADED')) {
            require_once __DIR__ . '/../../includes/config.php';
        }
        
        try {
            // Use constants from config.php
            $host = defined('DB_HOST') ? DB_HOST : 'localhost';
            $dbname = defined('DB_NAME') ? DB_NAME : 'safeshift_ehr_001_0';
            $username = defined('DB_USER') ? DB_USER : 'safeshift_admin';
            $password = defined('DB_PASS') ? DB_PASS : '';
            $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
            
            $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            return new PDO($dsn, $username, $password, $options);
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new \Exception("Unable to connect to database");
        }
    }
    
    /**
     * Generate UUID
     * 
     * @return string
     */
    protected function generateUuid(): string
    {
        return UuidHelper::generate();
    }
    
    /**
     * Find record by ID
     * 
     * @param string $id
     * @return array|null
     */
    public function findById(string $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Find all records with optional conditions
     * 
     * @param array $where
     * @param array $orderBy
     * @param int|null $limit
     * @param int $offset
     * @return array
     */
    public function findAll(array $where = [], array $orderBy = [], ?int $limit = null, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        // Add WHERE conditions
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $column => $value) {
                if ($value === null) {
                    $conditions[] = "$column IS NULL";
                } else {
                    $conditions[] = "$column = :$column";
                    $params[$column] = $value;
                }
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        // Add ORDER BY
        if (!empty($orderBy)) {
            $orders = [];
            foreach ($orderBy as $column => $direction) {
                $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
                $orders[] = "$column $direction";
            }
            $sql .= " ORDER BY " . implode(', ', $orders);
        }
        
        // Add LIMIT and OFFSET
        if ($limit !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
            $params['limit'] = $limit;
            $params['offset'] = $offset;
        }
        
        $stmt = $this->db->prepare($sql);
        
        // Bind parameters with correct types
        foreach ($params as $key => $value) {
            if ($key === 'limit' || $key === 'offset') {
                $stmt->bindValue(":$key", $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(":$key", $value);
            }
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Find one record by conditions
     * 
     * @param array $where
     * @return array|null
     */
    public function findOne(array $where): ?array
    {
        $results = $this->findAll($where, [], 1);
        return $results[0] ?? null;
    }
    
    /**
     * Count records
     * 
     * @param array $where
     * @return int
     */
    public function count(array $where = []): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $params = [];
        
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $column => $value) {
                if ($value === null) {
                    $conditions[] = "$column IS NULL";
                } else {
                    $conditions[] = "$column = :$column";
                    $params[$column] = $value;
                }
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        return (int) ($result['count'] ?? 0);
    }
    
    /**
     * Check if record exists
     * 
     * @param array $where
     * @return bool
     */
    public function exists(array $where): bool
    {
        return $this->count($where) > 0;
    }
    
    /**
     * Insert record
     * 
     * @param array $data
     * @return string|false Last insert ID or false on failure
     */
    public function insert(array $data)
    {
        try {
            $columns = array_keys($data);
            $placeholders = array_map(fn($col) => ":$col", $columns);
            
            $sql = sprintf(
                "INSERT INTO %s (%s) VALUES (%s)",
                $this->table,
                implode(', ', $columns),
                implode(', ', $placeholders)
            );
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($data);
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Insert failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update record
     * 
     * @param string $id
     * @param array $data
     * @return bool
     */
    public function update(string $id, array $data): bool
    {
        try {
            $sets = [];
            foreach (array_keys($data) as $column) {
                $sets[] = "$column = :$column";
            }
            
            $sql = sprintf(
                "UPDATE %s SET %s WHERE %s = :id",
                $this->table,
                implode(', ', $sets),
                $this->primaryKey
            );
            
            $data['id'] = $id;
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute($data);
        } catch (PDOException $e) {
            error_log("Update failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete record
     * 
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool
    {
        try {
            $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            error_log("Delete failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Execute raw SQL query
     * 
     * @param string $sql
     * @param array $params
     * @return \PDOStatement
     */
    protected function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     * Begin transaction
     * 
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->db->beginTransaction();
    }
    
    /**
     * Commit transaction
     * 
     * @return bool
     */
    public function commit(): bool
    {
        return $this->db->commit();
    }
    
    /**
     * Rollback transaction
     * 
     * @return bool
     */
    public function rollback(): bool
    {
        return $this->db->rollBack();
    }
}