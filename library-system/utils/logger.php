<?php
/**
 * Logger Class
 * 
 * A simple logging utility for the Library Management System
 */
class Logger {
    private static $instance = null;
    private $logFile;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Use the log file defined in config.php if available
        $this->logFile = defined('LOG_FILE') ? LOG_FILE : __DIR__ . '/../logs/app.log';
        
        // Ensure log directory exists
        $logDir = dirname($this->logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Singleton pattern implementation
     * 
     * @return Logger
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Log a message to the log file
     * 
     * @param string $message Message to log
     * @param string $level Log level (INFO, WARNING, ERROR)
     * @return bool Success or failure
     */
    public function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
        
        try {
            return file_put_contents($this->logFile, $logEntry, FILE_APPEND);
        } catch (Exception $e) {
            error_log("Failed to write to log file: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log an error message
     * 
     * @param string $message Error message
     * @param Exception $exception Optional exception
     * @return bool Success or failure
     */
    public function error($message, $exception = null) {
        $logMessage = $message;
        
        if ($exception !== null) {
            $logMessage .= " | Exception: " . $exception->getMessage();
            $logMessage .= " | Trace: " . $exception->getTraceAsString();
        }
        
        return $this->log($logMessage, 'ERROR');
    }
    
    /**
     * Log a warning message
     * 
     * @param string $message Warning message
     * @return bool Success or failure
     */
    public function warning($message) {
        return $this->log($message, 'WARNING');
    }
    
    /**
     * Log an info message
     * 
     * @param string $message Info message
     * @return bool Success or failure
     */
    public function info($message) {
        return $this->log($message, 'INFO');
    }
}
?> 