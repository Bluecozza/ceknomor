<?php
/**
 * modules/csvimport/CsvimportRoutes.php
 */

// Log immediately on file load
error_log("=== CsvimportRoutes.php loaded at " . date('Y-m-d H:i:s') . " ===", 3, STORAGE_PATH . '/logs/csvimport_routes.log');

class CsvimportRoutes
{
    private static $importService = null;
    private static $db = null;

    private static function init()
    {
        try {
            error_log("[INIT] Starting init...", 3, STORAGE_PATH . '/logs/csvimport_routes.log');
            
            self::$db = Database::getInstance();
            error_log("[INIT] Database loaded", 3, STORAGE_PATH . '/logs/csvimport_routes.log');
            
            $pm = PluginManager::getInstance();
            error_log("[INIT] PluginManager obtained", 3, STORAGE_PATH . '/logs/csvimport_routes.log');
            
            $plugin = $pm->getPlugin('csvimport');
            error_log("[INIT] Plugin loaded: " . ($plugin ? 'YES' : 'NO'), 3, STORAGE_PATH . '/logs/csvimport_routes.log');
            
            if (!$plugin) {
                error_log("[INIT] ERROR: Plugin is null", 3, STORAGE_PATH . '/logs/csvimport_routes.log');
                return false;
            }
            
            if (!method_exists($plugin, 'getImportService')) {
                error_log("[INIT] ERROR: getImportService method not found", 3, STORAGE_PATH . '/logs/csvimport_routes.log');
                return false;
            }
            
            self::$importService = $plugin->getImportService();
            error_log("[INIT] ImportService obtained: " . (self::$importService ? 'YES' : 'NO'), 3, STORAGE_PATH . '/logs/csvimport_routes.log');
            
            if (!self::$importService) {
                error_log("[INIT] ERROR: ImportService is null", 3, STORAGE_PATH . '/logs/csvimport_routes.log');
                return false;
            }
            
            error_log("[INIT] SUCCESS", 3, STORAGE_PATH . '/logs/csvimport_routes.log');
            return true;
        } catch (Exception $e) {
            error_log("[INIT] EXCEPTION: " . $e->getMessage(), 3, STORAGE_PATH . '/logs/csvimport_routes.log');
            error_log("[INIT] TRACE: " . $e->getTraceAsString(), 3, STORAGE_PATH . '/logs/csvimport_routes.log');
            return false;
        }
    }

public static function handleUpload()
{
    error_log("[UPLOAD] Handler called", 3, STORAGE_PATH . '/logs/csvimport_routes.log');
    
    if (!self::init()) {
        error_log("[UPLOAD] Init failed", 3, STORAGE_PATH . '/logs/csvimport_routes.log');
        Response::error('Service initialization failed', 500);
    }
    
    try {
        error_log("[UPLOAD] Checking token", 3, STORAGE_PATH . '/logs/csvimport_routes.log');
        
        $token = get_bearer_token();
        error_log("[UPLOAD] Token received: " . ($token ? 'YES (len=' . strlen($token) . ')' : 'NO'), 3, STORAGE_PATH . '/logs/csvimport_routes.log');
        
        if (!$token) {
            error_log("[UPLOAD] No token", 3, STORAGE_PATH . '/logs/csvimport_routes.log');
            Response::unauthorized('Token required');
        }
        
        error_log("[UPLOAD] Token: " . substr($token, 0, 50) . "...", 3, STORAGE_PATH . '/logs/csvimport_routes.log');
        error_log("[UPLOAD] JWT_SECRET defined: " . (defined('JWT_SECRET') ? 'YES' : 'NO'), 3, STORAGE_PATH . '/logs/csvimport_routes.log');
        
        $payload = verify_jwt($token);
        error_log("[UPLOAD] verify_jwt result: " . ($payload ? 'VALID' : 'INVALID'), 3, STORAGE_PATH . '/logs/csvimport_routes.log');
        
        if ($payload) {
            error_log("[UPLOAD] Payload: " . json_encode($payload), 3, STORAGE_PATH . '/logs/csvimport_routes.log');
        }
        
        if (!$payload) {
            error_log("[UPLOAD] Token invalid", 3, STORAGE_PATH . '/logs/csvimport_routes.log');
            Response::unauthorized('Invalid token');
        }
        
        if (!in_array($payload['role'], ['superadmin', 'admin'], true)) {
            error_log("[UPLOAD] Insufficient role: " . $payload['role'], 3, STORAGE_PATH . '/logs/csvimport_routes.log');
            Response::forbidden('Admin role required');
        }

        error_log("[UPLOAD] Checking file", 3, STORAGE_PATH . '/logs/csvimport_routes.log');
        
        if (!isset($_FILES['file'])) {
            error_log("[UPLOAD] No file in _FILES", 3, STORAGE_PATH . '/logs/csvimport_routes.log');
            Response::validationError(['file' => 'File harus diupload']);
        }

        $file = $_FILES['file'];
        
        error_log("[UPLOAD] File info: name=" . $file['name'] . ", size=" . $file['size'] . ", error=" . $file['error'], 3, STORAGE_PATH . '/logs/csvimport_routes.log');
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            error_log("[UPLOAD] Upload error: " . $file['error'], 3, STORAGE_PATH . '/logs/csvimport_routes.log');
            Response::error('Upload error: ' . $file['error'], 422);
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            error_log("[UPLOAD] File too large", 3, STORAGE_PATH . '/logs/csvimport_routes.log');
            Response::error('File terlalu besar (max 5MB)', 422);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            error_log("[UPLOAD] Invalid extension: " . $ext, 3, STORAGE_PATH . '/logs/csvimport_routes.log');
            Response::error('Hanya file CSV yang diizinkan', 422);
        }

        error_log("[UPLOAD] Parsing CSV from: " . $file['tmp_name'], 3, STORAGE_PATH . '/logs/csvimport_routes.log');
        
        $parseResult = self::$importService->parseCSV($file['tmp_name']);
        
        error_log("[UPLOAD] Parse result: " . json_encode([
            'has_error' => isset($parseResult['error']),
            'total_records' => $parseResult['total_records'] ?? 0,
            'total_errors' => $parseResult['total_errors'] ?? 0
        ]), 3, STORAGE_PATH . '/logs/csvimport_routes.log');
        
        if (isset($parseResult['error'])) {
            error_log("[UPLOAD] Parse error: " . $parseResult['error'], 3, STORAGE_PATH . '/logs/csvimport_routes.log');
            Response::error($parseResult['error'], 422);
        }

        $sessionId = self::$importService->saveImportSession($parseResult);
        
        error_log("[UPLOAD] SUCCESS - Session: " . $sessionId, 3, STORAGE_PATH . '/logs/csvimport_routes.log');

        Response::success([
            'session_id' => $sessionId,
            'preview' => [
                'total_records' => $parseResult['total_records'],
                'total_errors' => $parseResult['total_errors'],
                'records' => array_slice($parseResult['records'] ?? [], 0, 5),
                'errors' => array_slice($parseResult['errors'] ?? [], 0, 5)
            ]
        ], 'CSV berhasil di-upload');
    } catch (Exception $e) {
        error_log("[UPLOAD] EXCEPTION: " . $e->getMessage(), 3, STORAGE_PATH . '/logs/csvimport_routes.log');
        error_log("[UPLOAD] TRACE: " . $e->getTraceAsString(), 3, STORAGE_PATH . '/logs/csvimport_routes.log');
        Response::error('Upload failed: ' . $e->getMessage(), 500);
    }
}
	
	
	
    public static function handlePreview(...$params)
    {
        error_log("[PREVIEW] Called with params: " . json_encode($params), 3, STORAGE_PATH . '/logs/csvimport_routes.log');
        if (!self::init()) Response::error('Service initialization failed', 500);
        
        try {
            $sessionId = $params[0] ?? null;
            if (!$sessionId) Response::error('Session ID required', 400);
            
            $token = get_bearer_token();
            if (!$token) Response::unauthorized('Token required');
            $payload = verify_jwt($token);
            if (!in_array($payload['role'], ['superadmin', 'admin'], true)) Response::forbidden('Admin role required');
            
            $session = self::$importService->getImportSession($sessionId);
            if (!$session) Response::notFound('Import session tidak ditemukan');
            
                        $summary = [
                'total_records' => $session['data']['total_records'] ?? 0,
                'total_errors' => $session['data']['total_errors'] ?? 0,
            ];

            Response::success([
                'session_id' => $sessionId, 
                'records' => $session['data']['records'] ?? [],
                'summary' => $summary
            ]);
        } catch (Exception $e) {
            error_log("[PREVIEW] EXCEPTION: " . $e->getMessage(), 3, STORAGE_PATH . '/logs/csvimport_routes.log');
            Response::error('Preview failed', 500);
        }
    }

    public static function handleRecordUpdate(...$params) { if (!self::init()) Response::error('Init failed', 500); }
        public static function handleBulkAction(...$params)
    {
        if (!self::init()) Response::error('Init failed', 500);

        try {
            $sessionId = $params[0] ?? null;
            if (!$sessionId) Response::error('Session ID required', 400);

            $token = get_bearer_token();
            if (!$token) Response::unauthorized('Token required');
            $payload = verify_jwt($token);
            if (!in_array($payload['role'], ['superadmin', 'admin'], true)) Response::forbidden('Admin role required');

            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? null;

            if (!in_array($action, ['approve_all', 'approve_valid', 'reject_all'])) {
                Response::error('Invalid action', 400);
            }

            $success = self::$importService->bulkUpdateStatus($sessionId, $action);

            if ($success) {
                Response::success(['status' => 'ok']);
            } else {
                Response::error('Bulk action failed', 500);
            }
        } catch (Exception $e) {
            error_log("[BULK] EXCEPTION: " . $e->getMessage(), 3, STORAGE_PATH . '/logs/csvimport_routes.log');
            Response::error('Bulk action failed', 500);
        }
    }
        public static function handleSubmit(...$params)
    {
        if (!self::init()) Response::error('Init failed', 500);

        try {
            $sessionId = $params[0] ?? null;
            if (!$sessionId) Response::error('Session ID required', 400);

            $token = get_bearer_token();
            if (!$token) Response::unauthorized('Token required');

            $payload = verify_jwt($token);
            if (!$payload || !in_array($payload['role'], ['superadmin', 'admin'], true)) {
                Response::forbidden('Admin role required');
            }
            
            $adminId = $payload['user_id'] ?? 0;

            $result = self::$importService->submitImport($sessionId, $adminId);

            if ($result['success']) {
                Response::success([
                    'created' => $result['successful'],
                    'failed' => $result['failed'],
                    'errors' => $result['errors']
                ]);
            } else {
                Response::error($result['error'] ?? 'Submit failed', 500);
            }
        } catch (Exception $e) {
            error_log("[SUBMIT] EXCEPTION: " . $e->getMessage(), 3, STORAGE_PATH . '/logs/csvimport_routes.log');
            Response::error('Submit failed: ' . $e->getMessage(), 500);
        }
    }
    public static function handleStatus(...$params) { if (!self::init()) Response::error('Init failed', 500); }
}

error_log("=== CsvimportRoutes.php loaded successfully ===", 3, STORAGE_PATH . '/logs/csvimport_routes.log');