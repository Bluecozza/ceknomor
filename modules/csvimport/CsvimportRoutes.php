<?php
/**
 * modules/csvimport/CsvimportRoutes.php
 * API handlers untuk CSV import plugin
 */

class CsvimportRoutes
{
    private static $importService = null;
    private static $db = null;

    private static function init()
    {
        self::$db = Database::getInstance();
        $pm = PluginManager::getInstance();
        $plugin = $pm->getPlugin('csvimport');
        if ($plugin && method_exists($plugin, 'getImportService')) {
            self::$importService = $plugin->getImportService();
        }
    }

    public static function handleUpload()
    {
        self::init();
        
        $token = get_bearer_token();
        if (!$token) Response::unauthorized('Token required');
        
        $payload = verify_jwt($token);
        if (!$payload) Response::unauthorized('Invalid token');
        if (!in_array($payload['role'], ['superadmin', 'admin'], true)) {
            Response::forbidden('Admin role required');
        }

        if (!isset($_FILES['file'])) {
            Response::validationError(['file' => 'File harus diupload']);
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::error('Upload error', 422);
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            Response::error('File terlalu besar (max 5MB)', 422);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            Response::error('Hanya file CSV yang diizinkan', 422);
        }

        if (!self::$importService) {
            Response::error('Import service tidak tersedia', 500);
        }

        $parseResult = self::$importService->parseCSV($file['tmp_name']);
        if (isset($parseResult['error'])) {
            Response::error($parseResult['error'], 422);
        }

        $sessionId = self::$importService->saveImportSession($parseResult);

        Response::success([
            'session_id' => $sessionId,
            'preview' => [
                'total_records' => $parseResult['total_records'],
                'total_errors' => $parseResult['total_errors'],
                'records' => array_slice($parseResult['records'], 0, 5)
            ]
        ], 'CSV berhasil di-upload');
    }

    public static function handlePreview(...$params)
    {
        self::init();
        
        $sessionId = $params[0] ?? null;
        if (!$sessionId) Response::error('Session ID required', 400);

        $token = get_bearer_token();
        if (!$token) Response::unauthorized('Token required');
        
        $payload = verify_jwt($token);
        if (!in_array($payload['role'], ['superadmin', 'admin'], true)) {
            Response::forbidden('Admin role required');
        }

        if (!self::$importService) {
            Response::error('Import service tidak tersedia', 500);
        }

        $session = self::$importService->getImportSession($sessionId);
        if (!$session) Response::notFound('Import session tidak ditemukan');

        Response::success([
            'session_id' => $sessionId,
            'summary' => [
                'total_records' => $session['data']['total_records'] ?? 0,
                'total_errors' => $session['data']['total_errors'] ?? 0
            ],
            'records' => $session['data']['records'] ?? []
        ]);
    }

    public static function handleRecordUpdate(...$params)
    {
        self::init();
        
        list($sessionId, $lineNo) = [$params[0] ?? null, $params[1] ?? null];
        
        $token = get_bearer_token();
        if (!$token) Response::unauthorized('Token required');
        
        $payload = verify_jwt($token);
        if (!in*
