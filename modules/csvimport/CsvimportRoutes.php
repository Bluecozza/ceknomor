<?php
/**
 * modules/csvimport/CsvimportRoutes.php
 * API handlers untuk CSV import plugin dengan logging
 */

class CsvimportRoutes
{
    private static $importService = null;
    private static $db = null;

    private static function init()
    {
        try {
            self::$db = Database::getInstance();
            $pm = PluginManager::getInstance();
            $plugin = $pm->getPlugin('csvimport');
            
            if (!$plugin) {
                CsvimportLogger::error('Plugin not loaded', ['method' => __METHOD__]);
                return false;
            }
            
            if (!method_exists($plugin, 'getImportService')) {
                CsvimportLogger::error('getImportService method not found', ['plugin' => get_class($plugin)]);
                return false;
            }
            
            self::$importService = $plugin->getImportService();
            
            if (!self::$importService) {
                CsvimportLogger::error('ImportService is null', ['plugin_class' => get_class($plugin)]);
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            CsvimportLogger::error('Init error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return false;
        }
    }

    public static function handleUpload()
    {
        CsvimportLogger::debug('handleUpload called');
        
        if (!self::init()) {
            Response::error('Service initialization failed', 500);
        }
        
        try {
            $token = get_bearer_token();
            if (!$token) {
                CsvimportLogger::warning('No token provided');
                Response::unauthorized('Token required');
            }
            
            $payload = verify_jwt($token);
            if (!$payload) {
                CsvimportLogger::warning('Invalid token');
                Response::unauthorized('Invalid token');
            }
            
            if (!in_array($payload['role'], ['superadmin', 'admin'], true)) {
                CsvimportLogger::warning('Insufficient role', ['role' => $payload['role']]);
                Response::forbidden('Admin role required');
            }

            if (!isset($_FILES['file'])) {
                CsvimportLogger::warning('No file uploaded');
                Response::validationError(['file' => 'File harus diupload']);
            }

            $file = $_FILES['file'];
            
            CsvimportLogger::debug('File received', [
                'name' => $file['name'],
                'size' => $file['size'],
                'error' => $file['error']
            ]);
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                CsvimportLogger::warning('Upload error', ['error_code' => $file['error']]);
                Response::error('Upload error: ' . $file['error'], 422);
            }

            if ($file['size'] > 5 * 1024 * 1024) {
                CsvimportLogger::warning('File too large', ['size' => $file['size']]);
                Response::error('File terlalu besar (max 5MB)', 422);
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'csv') {
                CsvimportLogger::warning('Invalid file type', ['ext' => $ext]);
                Response::error('Hanya file CSV yang diizinkan', 422);
            }

            CsvimportLogger::debug('Parsing CSV', ['filename' => $file['name']]);
            $parseResult = self::$importService->parseCSV($file['tmp_name']);
            
            if (isset($parseResult['error'])) {
                CsvimportLogger::error('Parse error', ['error' => $parseResult['error']]);
                Response::error($parseResult['error'], 422);
            }

            CsvimportLogger::debug('Parse success', [
                'total_records' => $parseResult['total_records'],
                'total_errors' => $parseResult['total_errors']
            ]);
            
            $sessionId = self::$importService->saveImportSession($parseResult);
            
            CsvimportLogger::logSessionUpload($sessionId, $file['name'], $file['size'], $parseResult);

            Response::success([
                'session_id' => $sessionId,
                'preview' => [
                    'total_records' => $parseResult['total_records'],
                    'total_errors' => $parseResult['total_errors'],
                    'records' => array_slice($parseResult['records'], 0, 5),
                    'errors' => array_slice($parseResult['errors'], 0, 5)
                ]
            ], 'CSV berhasil di-upload');
        } catch (Exception $e) {
            CsvimportLogger::error('handleUpload exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            Response::error('Upload failed: ' . $e->getMessage(), 500);
        }
    }

    public static function handlePreview(...$params)
    {
        CsvimportLogger::debug('handlePreview called', ['params_count' => count($params)]);
        
        if (!self::init()) {
            Response::error('Service initialization failed', 500);
        }
        
        try {
            $sessionId = $params[0] ?? null;
            if (!$sessionId) {
                CsvimportLogger::warning('No session ID provided');
                Response::error('Session ID required', 400);
            }

            $token = get_bearer_token();
            if (!$token) {
                Response::unauthorized('Token required');
            }
            
            $payload = verify_jwt($token);
            if (!in_array($payload['role'], ['superadmin', 'admin'], true)) {
                Response::forbidden('Admin role required');
            }

            CsvimportLogger::debug('Getting session', ['session_id' => $sessionId]);
            $session = self::$importService->getImportSession($sessionId);
            
            if (!$session) {
                CsvimportLogger::warning('Session not found', ['session_id' => $sessionId]);
                Response::notFound('Import session tidak ditemukan');
            }

            Response::success([
                'session_id' => $sessionId,
                'summary' => [
                    'total_records' => $session['data']['total_records'] ?? 0,
                    'total_errors' => $session['data']['total_errors'] ?? 0
                ],
                'records' => $session['data']['records'] ?? []
            ]);
        } catch (Exception $e) {
            CsvimportLogger::error('handlePreview exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            Response::error('Preview failed', 500);
        }
    }

    public static function handleRecordUpdate(...$params)
    {
        CsvimportLogger::debug('handleRecordUpdate called', ['params' => $params]);
        
        if (!self::init()) {
            Response::error('Service initialization failed', 500);
        }
        
        try {
            list($sessionId, $lineNo) = [$params[0] ?? null, $params[1] ?? null];
            
            if (!$sessionId || !$lineNo) {
                CsvimportLogger::warning('Missing params', ['session_id' => $sessionId, 'line_no' => $lineNo]);
                Response::error('Invalid parameters', 400);
            }
            
            $token = get_bearer_token();
            if (!$token) Response::unauthorized('Token required');
            
            $payload = verify_jwt($token);
            if (!in_array($payload['role'], ['superadmin', 'admin'], true)) {
                Response::forbidden('Admin role required');
            }

            $body = get_json_body();
            $status = $body['status'] ?? null;
            
            if (!in_array($status, ['approved', 'rejected'], true)) {
                CsvimportLogger::warning('Invalid status', ['status' => $status]);
                Response::validationError(['status' => 'Invalid status']);
            }

            $result = self::$importService->updateRecordStatus($sessionId, (int)$lineNo, $status);
            
            if (!$result) {
                CsvimportLogger::warning('Update failed', ['session_id' => $sessionId, 'line_no' => $lineNo]);
                Response::error('Gagal update record', 422);
            }

            CsvimportLogger::debug('Record updated', ['line_no' => $lineNo, 'status' => $status]);
            Response::success(null, 'Record updated');
        } catch (Exception $e) {
            CsvimportLogger::error('handleRecordUpdate exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            Response::error('Update failed', 500);
        }
    }

    public static function handleBulkAction(...$params)
    {
        CsvimportLogger::debug('handleBulkAction called');
        
        if (!self::init()) {
            Response::error('Service initialization failed', 500);
        }
        
        try {
            $sessionId = $params[0] ?? null;
            $body = get_json_body();
            $action = $body['action'] ?? null;

            $token = get_bearer_token();
            if (!$token) Response::unauthorized('Token required');
            
            $payload = verify_jwt($token);
            if (!in_array($payload['role'], ['superadmin', 'admin'], true)) {
                Response::forbidden('Admin role required');
            }

            if (!in_array($action, ['approve_all', 'reject_all'], true)) {
                CsvimportLogger::warning('Invalid bulk action', ['action' => $action]);
                Response::validationError(['action' => 'Invalid action']);
            }

            CsvimportLogger::debug('Bulk action executed', ['action' => $action, 'session_id' => $sessionId]);
            Response::success(null, 'Bulk action processed');
        } catch (Exception $e) {
            CsvimportLogger::error('handleBulkAction exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            Response::error('Bulk action failed', 500);
        }
    }

    public static function handleSubmit(...$params)
    {
        CsvimportLogger::debug('handleSubmit called');
        
        if (!self::init()) {
            Response::error('Service initialization failed', 500);
        }
        
        try {
            $sessionId = $params[0] ?? null;
            
            $token = get_bearer_token();
            if (!$token) Response::unauthorized('Token required');
            
            $payload = verify_jwt($token);
            if (!in_array($payload['role'], ['superadmin', 'admin'], true)) {
                Response::forbidden('Admin role required');
            }

            CsvimportLogger::debug('Submitting import', ['session_id' => $sessionId]);
            $result = self::$importService->submitImport($sessionId, (int)$payload['admin_id']);
            
            if (!$result['success']) {
                CsvimportLogger::error('Submit failed', ['session_id' => $sessionId, 'message' => $result['message'] ?? 'Unknown']);
                Response::error($result['message'] ?? 'Import failed', 422);
            }

            CsvimportLogger::info('Import submitted successfully', [
                'session_id' => $sessionId,
                'created' => $result['created'] ?? 0,
                'failed' => $result['failed'] ?? 0
            ]);

            Response::success([
                'created' => $result['created'] ?? 0,
                'failed' => $result['failed'] ?? 0
            ], 'Import completed');
        } catch (Exception $e) {
            CsvimportLogger::error('handleSubmit exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            Response::error('Submit failed: ' . $e->getMessage(), 500);
        }
    }

    public static function handleStatus(...$params)
    {
        CsvimportLogger::debug('handleStatus called');
        
        if (!self::init()) {
            Response::error('Service initialization failed', 500);
        }
        
        try {
            $sessionId = $params[0] ?? null;
            
            $token = get_bearer_token();
            if (!$token) Response::unauthorized('Token required');
            
            $payload = verify_jwt($token);
            if (!in_array($payload['role'], ['superadmin', 'admin'], true)) {
                Response::forbidden('Admin role required');
            }

            $session = self::$importService->getImportSession($sessionId);
            if (!$session) {
                CsvimportLogger::warning('Session not found', ['session_id' => $sessionId]);
                Response::notFound('Import session tidak ditemukan');
            }

            Response::success([
                'session_id' => $sessionId,
                'status' => time() > strtotime($session['expires_at']) ? 'expired' : 'active'
            ]);
        } catch (Exception $e) {
            CsvimportLogger::error('handleStatus exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            Response::error('Status check failed', 500);
        }
    }
}