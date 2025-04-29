<?php
/**
 * Logger class for centralized error logging
 */
class Logger {
    private $logFile;
    private static $instance = null;
    
    /**
     * Constructor - initializes log file
     */
    private function __construct() {
        // Create logs directory if it doesn't exist
        if (!file_exists('logs')) {
            mkdir('logs', 0777, true);
        }
        
        $this->logFile = __DIR__ . '/logs/error.log';
    }
    
    /**
     * Get Logger instance (Singleton pattern)
     * @return Logger
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Logger();
        }
        
        return self::$instance;
    }
    
    /**
     * Log error message
     * @param string $message Error message
     * @param Exception|null $exception Optional exception for stack trace
     */
    public function error($message, $exception = null) {
        $this->writeLog('ERROR', $message, $exception);
    }
    
    /**
     * Log info message
     * @param string $message Info message
     */
    public function info($message) {
        $this->writeLog('INFO', $message);
    }
    
    /**
     * Log warning message
     * @param string $message Warning message
     */
    public function warning($message) {
        $this->writeLog('WARNING', $message);
    }
    
    /**
     * Write message to log file
     * @param string $level Log level (ERROR, INFO, WARNING)
     * @param string $message Log message
     * @param Exception|null $exception Optional exception for stack trace
     */
    private function writeLog($level, $message, $exception = null) {
        $timestamp = date('[Y-m-d H:i:s]');
        $logEntry = "$timestamp [$level] $message";
        
        if ($exception !== null) {
            $logEntry .= "\nStack trace: " . $exception->getTraceAsString();
        }
        
        file_put_contents($this->logFile, $logEntry . "\n", FILE_APPEND);
    }
}
?> 