<?php
declare(strict_types=1);

namespace Mosaic\Core;

use mysqli;
use mysqli_sql_exception;
use RuntimeException;

/**
 * Database Connection Manager
 * 
 * Provides mysqli connection management with singleton pattern.
 * All queries must use prepared statements for security.
 * 
 * @package Mosaic\Core
 */
class Database
{
    private static ?Database $instance = null;
    private ?mysqli $connection = null;
    private array $config;
    
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
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        
        try {
            $this->connection = new mysqli(
                $this->config['host'],
                $this->config['username'],
                $this->config['password'],
                $this->config['database'],
                $this->config['port'] ?? 3306
            );
            
            // Set UTF8MB4 charset
            $this->connection->set_charset('utf8mb4');
            
            // Set timezone to UTC
            $this->connection->query("SET time_zone = '+00:00'");
            
        } catch (mysqli_sql_exception $e) {
            throw new RuntimeException(
                'Database connection failed: ' . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }
    
    /**
     * Get mysqli connection
     * 
     * @return mysqli
     * @throws RuntimeException If connection not established
     */
    public function getConnection(): mysqli
    {
        if ($this->connection === null) {
            throw new RuntimeException('Database connection not established');
        }
        
        return $this->connection;
    }
    
    /**
     * Execute prepared statement with parameters
     * 
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @param string $types Parameter types (i=integer, d=double, s=string, b=blob)
     * @return mysqli_result|bool Query result
     * @throws RuntimeException If query fails
     */
    public function query(string $sql, array $params = [], string $types = ''): mixed
    {
        $conn = $this->getConnection();
        
        // If no parameters, execute directly
        if (empty($params)) {
            $result = $conn->query($sql);
            if ($result === false) {
                throw new RuntimeException('Query failed: ' . $conn->error);
            }
            return $result;
        }
        
        // Prepare statement
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Prepare failed: ' . $conn->error);
        }
        
        // Auto-detect types if not provided
        if (empty($types)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
        }
        
        // Bind parameters
        $stmt->bind_param($types, ...$params);
        
        // Execute
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Execute failed: ' . $error);
        }
        
        // Get result
        $result = $stmt->get_result();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Get last inserted ID
     * 
     * @return int
     */
    public function getInsertId(): int
    {
        return $this->getConnection()->insert_id;
    }
    
    /**
     * Get number of affected rows
     * 
     * @return int
     */
    public function getAffectedRows(): int
    {
        return $this->getConnection()->affected_rows;
    }
    
    /**
     * Begin transaction
     * 
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->getConnection()->begin_transaction();
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
        return $this->getConnection()->rollback();
    }
    
    /**
     * Escape string for SQL (use prepared statements instead when possible)
     * 
     * @param string $value Value to escape
     * @return string Escaped value
     */
    public function escape(string $value): string
    {
        return $this->getConnection()->real_escape_string($value);
    }
    
    /**
     * Close connection
     */
    public function close(): void
    {
        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }
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
