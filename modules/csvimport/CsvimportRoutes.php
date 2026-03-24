<?php
/**
 * modules/csvimport/CsvimportRoutes.php
 * API handlers untuk CSV import plugin
 */

class CsvimportRoutes
{
    private $importService;
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        // Get import service from plugin
        $module = __PLUGINS->getInstance('csvimport');
        $this->importService = $module->getImportService();
    }

    /**
     * POST /api/v1/plugins/csvimport/upload
     * Handle CSV file upload
     */
    public static function handleUpload()
    {
        $self = new self();
        
        // Auth check
        $token = get_bearer_token();
        if (!$token) Response::unauthorized('Token required');
        
        $payload = verify_jwt($token);
        if (!$payload) Response::unauthorized('Invalid token');
        if (!in_array($payload['role'], ['superadmin', 'admin'], true)) {
            Response::forbidden('Admin role required');
        }

        // File validation
        if (!isset($_FILES['file'])) {
            Response::validationError(['file' => 'File harus diupload']);
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::error('Upload error: ' . $file['error'], 422);
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            Response::error('File terlalu besar (max 5MB)', 422);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            Response::error('Hanya file CSV yang diizinkan', 422);
        }

        // Parse CSV
        $parseResult = $self->importService->parseCSV($file['tmp_name']);
        if (isset($parseResult['error'])) {
            Response::error($parseResult['error'], 422);
        }

        // Save session
        $sessionId = $self->importService->saveImportSession($parseResult);

        Response::success([
            'session_id' => $sessionId,
            'preview' => [
                'total_records' => $parseResult['total_records'],
                'total_errors' => $parseResult['total_errors'],
                'records' => array_slice($parseResult['records'], 0, 5),
                'errors' => $parseResult['errors']
            ]
        ], 'CSV berhasil di-upload');
    }

    /**
     * GET /api/v1/plugins/csvimport/preview/{session_id}
     * Get full preview of import session
     */
    public static function handlePreview($sessionId = null)
    {
        $self = new self();
        
        $token = get_bearer_token();
        if (!$token || !verify_jwt($token)) Response::unauthorized('Token required');
        
        $payload = verify_jwt($token);
        if (!in_array($payload['role'], ['superadmin', 'admin'], true)) {
            Response::forbidden('Admin role required');
        }

        if (!$sessionId) Response::error('Session ID required', 400);

        $session = $self->importService->getImportSession($sessionId);
        if (!$session) Response::notFound('Import session tidak ditemukan');

        Response::success([
            'session_id' => $sessionId,
            'created_at' => $session['created_at'],
            'expires_at' => $session['expires_at'],
            'summary' => [
                'total_records' => $session['data']['total_records'],
                'total_errors' => $session['data']['total_errors'],
                'pending' => count(array_filter($session['data']['records'], fn($r) => $r['status'] === 'pending')),
                'approved' => count(array_filter($session['data']['records'], fn($r) => $r['status'] === 'approved')),
                'rejected' => count(array_filter($session['data']['records'], fn($r) => $r['status'] === 'rejected'))
            ],
            'records' => $session['data']['records'],
            'errors' => $session['data']['errors'] ?? []
        ]);
    }

    /**
     * PUT /api/v1/plugins/csvimport/record/{session_id}/{line_no}
     * Update single record status
     */
    public static function handleRecordUpdate($sessionId = null, $lineNo = null)
    {
        $self = new self();
        
        $token = get_bearer_token();
        if (!$token || !verify_jwt($token)) Response::unauthorized('Token required');
        
        $payload = verify_jwt($token);
        if (!in_array($payload['role'], ['superadmin', 'admin'], true)) {
            Response::forbidden('Admin role required');
        }

        if (!$sessionId || !$lineNo) Response::error('Session ID and Line No required', 400);

        $body = get_json_body();
        $status = $body['status'] ?? null;
        if (!in_array($status, ['approved', 'rejected'], true)) {
            Response::validationError(['status' => 'Invalid status']);
        }

        $result = $self->importService->updateRecordStatus($sessionId, (int)$lineNo, $status);
        if (!$result) {
            Response::error('Gagal update record', 422);
        }

        Response::success(null, 'Record updated');
    }

    /**
     * POST /api/v1/plugins/csvimport/bulk-action/{session_id}
     * Apply action to multiple records
     */
    public static function handleBulkAction($sessionId = null)
    {
        $self = new self();
        
        $token = get_bearer_token();
        if (!$token || !verify_jwt($token)) Response::unauthorized('Token required');
        
        $payload = verify_jwt($token);
        if (!in_array($payload['role'], ['superadmin', 'admin'], true)) {
            Response::forbidden('Admin role required');
        }

        if (!$sessionId) Response::error('Session ID required', 400);

        $body = get_json_body();
        $action = $body['action'] ?? null;
        $lineNos = $body['line_nos'] ?? [];

        if (!in_array($action, ['approve_all', 'reject_all', 'clear_all'], true)) {
            Response::validationError(['action' => 'Invalid action']);
        }

        if (empty($lineNos)) {
            Response::validationError(['line_nos' => 'At least one record required']);
        }

        $mapping = [
            'approve_all' => 'approved',
            'reject_all' => 'rejected',
            'clear_all' => 'pending'
        ];

        $status = $mapping[$action];
        $updated = 0;

        foreach ($lineNos as $lineNo) {
            if ($self->importService->updateRecordStatus($sessionId, (int)$lineNo, $status)) {
                $updated++;
            }
        }

        Response::success(['updated' => $updated], "{$updated} records updated");
    }

    /**
     * POST /api/v1/plugins/csvimport/submit/{session_id}
     * Submit import to database
     */
    public static function handleSubmit($sessionId = null)
    {
        $self = new self();
        
        $token = get_bearer_token();
        if (!$token || !verify_jwt($token)) Response::unauthorized('Token required');
        
        $payload = verify_jwt($token);
        if (!in_array($payload['role'], ['superadmin', 'admin'], true)) {
            Response::forbidden('Admin role required');
        }

        if (!$sessionId) Response::error('Session ID required', 400);

        $result = $self->importService->submitImport($sessionId, (int)$payload['admin_id']);
        
        if (!$result['success']) {
            Response::error($result['message'] ?? 'Import failed', 422);
        }

        Response::success([
            'created' => $result['created'] ?? 0,
            'failed' => $result['failed'] ?? 0,
            'summary' => $result['summary'] ?? []
        ], $result['message'] ?? 'Import completed');
    }

    /**
     * GET /api/v1/plugins/csvimport/status/{session_id}
     * Get import session status
     */
    public static function handleStatus($sessionId = null)
    {
        $self = new self();
        
        $token = get_bearer_token();
        if (!$token || !verify_jwt($token)) Response::unauthorized('Token required');
        
        $payload = verify_jwt($token);
        if (!in_array($payload['role'], ['superadmin', 'admin'], true)) {
            Response::forbidden('Admin role required');
        }

        if (!$sessionId) Response::error('Session ID required', 400);

        $session = $self->importService->getImportSession($sessionId);
        if (!$session) Response::notFound('Import session tidak ditemukan');

        // Check if expired
        $expiresAt = strtotime($session['expires_at']);
        $isExpired = time() > $expiresAt;

        Response::success([
            'session_id' => $sessionId,
            'status' => $isExpired ? 'expired' : 'active',
            'expires_at' => $session['expires_at'],
            'summary' => [
                'total' => $session['data']['total_records'] ?? 0,
                'errors' => $session['data']['total_errors'] ?? 0,
                'pending' => count(array_filter($session['data']['records'] ?? [], fn($r) => $r['status'] === 'pending')),
                'approved' => count(array_filter($session['data']['records'] ?? [], fn($r) => $r['status'] === 'approved')),
                'rejected' => count(array_filter($session['data']['records'] ?? [], fn($r) => $r['status'] === 'rejected'))
            ]
        ]);
    }
}