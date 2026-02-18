<?php
declare(strict_types=1);

namespace Mosaic\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Database Connection Manager
 * 
 * Provides PDO connection management with singleton pattern.
 * Supports both MySQL and MSSQL databases.
 * All queries must use prepared statements for security.
 * 
 * @package Mosaic\Core
 */
class Database
{
    private static ?Database $instance = null;
    private ?PDO $connection = null;
    private array $config;
    private string $driver;
    
    /**
     * Private constructor
     * 
     * @param array $config Database configuration
     */
    private function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }
    
    /**
     * Get singleton instance
     * 
     * @param array|null $config Database configuration (required on first call)
     * @return Database
     * @throws RuntimeException If config missing on first call
     */
    public static function getInstance(?array $config = null): Database
    {
        if (self::$instance === null) {
            if ($config === null) {
                throw new RuntimeException('Database config required on first instantiation');
            }
            self::$instance = new self($config);
        }
        
        return self::$instance;
    }
    
    /**
     * Establish database connection
     * 
     * @throws RuntimeException If connection fails
     */
    private function connect(): void
    {
        $this->driver = $this->config['driver'] ?? 'mysql';
        
        try {
            if ($this->driver === 'mssql' || $this->driver === 'sqlsrv') {
                // Microsoft SQL Server connection
                $dsn = sprintf(
                    'sqlsrv:Server=%s,%d;Database=%s',
                    $this->config['host'],
                    $this->config['port'] ?? 1433,
                    $this->config['name']
                );
            } else {
                // MySQL connection (default)
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    $this->config['host'],
                    $this->config['port'] ?? 3306,
                    $this->config['name'],
                    $this->config['charset'] ?? 'utf8mb4'
                );
            }
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->connection = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $options
            );
            
            // Set timezone to UTC for MySQL
            if ($this->driver === 'mysql') {
                $this->connection->exec("SET time_zone = '+00:00'");
            }
            
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Database connection failed: ' . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }
    
    /**
     * Get PDO connection
     * 
     * @return PDO
     * @throws RuntimeException If connection not established
     */
    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            throw new RuntimeException('Database connection not established');
        }
        
        return $this->connection;
    }
    
    /**
     * Get database driver name
     * 
     * @return string 'mysql' or 'mssql'
     */
    public function getDriver(): string
    {
        return $this->driver;
    }
    
    /**
     * Execute prepared statement with parameters
     * 
     * @param string $sql SQL query with ? placeholders
     * @param array $params Parameters to bind (positional, matching ? in query)
     * @param string $types Optional type hints parameter (ignored, kept for legacy compatibility)
     * @return PDOStatement|bool Query result statement or boolean for non-SELECT queries
     * @throws RuntimeException If query fails
     */
    public function query(string $sql, array $params = [], string $types = ''): mixed
    {
        $conn = $this->getConnection();
        
        try {
            // If no parameters, execute directly
            if (empty($params)) {
                return $conn->query($sql);
            }
            
            // Prepare and execute with parameters
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt;
            
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Query failed: ' . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }
    
    /**
     * Get last inserted ID
     * 
     * @return int
     */
    public function getInsertId(): int
    {
        return (int)$this->getConnection()->lastInsertId();
    }
    
    /**
     * Get number of affected rows from last statement
     * 
     * Note: With PDO, you need to call this on the PDOStatement:
     * $stmt = $db->query(...);
     * $affected = $stmt->rowCount();
     * 
     * This method is kept for compatibility but may not return expected results.
     * 
     * @return int
     * @deprecated Use PDOStatement::rowCount() instead
     */
    public function getAffectedRows(): int
    {
        // PDO doesn't have a connection-level affected rows
        // This is here for backwards compatibility but always returns 0
        return 0;
    }
    
    /**
     * Begin transaction
     * 
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->getConnection()->beginTransaction();
    }
    
    /**
     * Commit transaction
     * 
     * @return bool
     */
    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }
    
    /**
     * Rollback transaction
     * 
     * @return bool
     */
    public function rollback(): bool
    {
        return $this->getConnection()->rollBack();
    }
    
    /**
     * Escape string for SQL (use prepared statements instead when possible)
     * 
     * @param string $value Value to escape
     * @return string Escaped value
     */
    public function escape(string $value): string
    {
        // PDO quote returns value with quotes, so we strip them
        $quoted = $this->getConnection()->quote($value);
        return substr($quoted, 1, -1);
    }
    
    /**
     * Close connection
     */
    public function close(): void
    {
        $this->connection = null;
    }
    
    /**
     * Destructor - ensure connection is closed
     */
    public function __destruct()
    {
        $this->close();
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
}
