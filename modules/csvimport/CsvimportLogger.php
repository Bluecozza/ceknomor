<?php
/**
 * modules/csvimport/CsvimportLogger.php
 * Comprehensive logging untuk CSV import operations
 */

class CsvimportLogger
{
    private static $logFile;
    private static $sessionLogDir;

    public static function init()
    {
        self::$logFile = STORAGE_PATH . '/logs/csvimport.log';
        self::$sessionLogDir = STORAGE_PATH . '/logs/csvimport-sessions';
        
        // Create directories if not exist
        foreach ([dirname(self::$logFile), self::$sessionLogDir] as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Log event
     */
    public static function log(string $level, string $message, ?array $context = null)
    {
        self::init();
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}";
        
        if ($context) {
            $logMessage .= "\nContext: " . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
        
        $logMessage .= "\n---\n";
        
        error_log($logMessage, 3, self::$logFile);
    }

    public static function info(string $msg, ?array $ctx = null) { self::log('INFO', $msg, $ctx); }
    public static function error(string $msg, ?array $ctx = null) { self::log('ERROR', $msg, $ctx); }
    public static function warning(string $msg, ?array $ctx = null) { self::log('WARN', $msg, $ctx); }
    public static function debug(string $msg, ?array $ctx = null) { self::log('DEBUG', $msg, $ctx); }

    /**
     * Log session upload event
     */
    public static function logSessionUpload(string $sessionId, string $filename, int $fileSize, array $parseResult)
    {
        $logFile = self::$sessionLogDir . '/' . $sessionId . '.log';
        $content = json_encode([
            'timestamp' => date('Y-m-d H:i:s'),
            'filename' => $filename,
            'file_size' => $fileSize,
            'total_records' => $parseResult['total_records'] ?? 0,
            'total_errors' => $parseResult['total_errors'] ?? 0,
            'errors_sample' => array_slice($parseResult['errors'] ?? [], 0, 5),
        ], JSON_PRETTY_PRINT);
        
        file_put_contents($logFile, $content);
        self::info("Session created: {$sessionId}");
    }

    /**
     * Get all session logs
     */
    public static function getSessionLogs(string $sessionId): ?array
    {
        $logFile = self::$sessionLogDir . '/' . $sessionId . '.log';
        if (!file_exists($logFile)) return null;
        
        return json_decode(file_get_contents($logFile), true);
    }
}