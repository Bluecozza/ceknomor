<?php
/**
 * modules/csvimport/routes.php
 * Routes untuk CSV import functionality
 */

return [
    // ── POST /import/upload - Upload dan preview CSV
    [
        'method'  => 'POST',
        'path'    => '/import/upload',
        'handler' => function($path, $method, $params, $db, $hooks) {
            // Auth check
            $token = get_bearer_token();
            if (!$token || !verify_jwt($token)) {
                Response::unauthorized('Token required');
            }

            $payload = verify_jwt($token);
            if (!in_array($payload['role'], ['superadmin', 'admin'], true)) {
                Response::forbidden('Admin role required');
            }

            // Get module
            $loader = __MODULES['loader'];
            $importService = $loader->getRoutes('csvimport');

            if (!isset($_FILES['file'])) {
                Response::validationError(['file' => 'File harus diupload']);
            }

            $file = $_FILES['file'];
            $errors = [];

            // Validation
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

            // Get import service dari module instance
            $module = __MODULES['registry']->getInstance('csvimport');
            $importService = $module->getImportService();

            // Parse CSV
            $parseResult = $importService->parseCSV($file['tmp_name']);

            // Save session
            $sessionId = $importService->saveImportSession($parseResult);

            Response::success([
                'session_id' => $sessionId,
                'preview' => [
                    'total_records' => $parseResult['total_records'],
                    'total_errors' => $parseResult['total_errors'],
                    'records' => $parseResult['records'],
                    'errors' => array_slice($parseResult['errors'], 0, 10), // First 10 errors
                    'warnings' => array_slice($parseResult['warnings'], 0, 10)
                ]
            ], 'CSV berhasil diparse', 201);
        }
    ],

    // ── GET /import/preview/:session_id - Get detailed preview
    [
        'method'  => 'GET',
        'path'    => '/import/preview/([a-z0-9_\.]+)$',
        'pattern' => true,
        'handler' => function($path, $method, $params, $db, $hooks) {
            $token = get_bearer_token();
            if (!$token || !verify_jwt($token)) {
                Response::unauthorized('Token required');
            }

            $payload = verify_jwt($token);
            if (!in_array($payload['role'], ['superadmin', 'admin'], true)) {
                Response::forbidden('Admin role required');
            }

            $sessionId = $params[1] ?? null;
            if (!$sessionId) {
                Response::error('Session ID required', 400);
            }

            $module = __MODULES['registry']->getInstance('csvimport');
            $importService = $module->getImportService();

            $session = $importService->getImportSession($sessionId);
            if (!$session) {
                Response::notFound('Import session tidak ditemukan');
            }

            Response::success([
                'session_id' => $sessionId,
                'created_at' => $session['created_at'],
                'expires_at' => $session['expires_at'],
                'summary' => [
                    'total_records' => $session['data']['total_records'],
                    'total_errors' => $session['data']['total_errors'],
                    'pending' => count(array_filter($session['data']['records'], fn($r) => $r['status'] === 'pending')),
                    'approved' => count(array_filter($session['data']['records'], fn($r) => $r['status'] === 'approved')),
                    'rejected' => count(array_filter($session['data']['records'], fn($r) => $r['status'] === 'rejected')),
                ],
                'records' => $session['data']['records'],
                'errors' => $session['data']['errors'],
                'warnings' => $session['data']['warnings']
            ]);
        }
    ],

    // ── PUT /import/record/:session_id/:line_no - Update record status
    [
        'method'  => 'PUT',
        'path'    => '/import/record/([a-z0-9_\.]+)/(\d+)$',
        'pattern' => true,
        'handler' => function($path, $method, $params, $db, $hooks) {
            $token = get_bearer_token();
            if (!$token || !verify_jwt($token)) {
                Response::unauthorized('Token required');
            }

            $payload = verify_jwt($token);
            if (!in_array($payload['role'], ['superadmin', 'admin'], true)) {
                Response::forbidden('Admin role required');
            }

            $sessionId = $params[1] ?? null;
            $lineNo = (int)($params[2] ?? 0);
            $body = get_json_body();

            if (!$sessionId || !$lineNo) {
                Response::error('Invalid parameters', 400);
            }

            $status = $body['status'] ?? 'pending';
            if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
                Response::validationError(['status' => 'Status tidak valid']);
            }

            $module = __MODULES['registry']->getInstance('csvimport');
            $importService = $module->getImportService();

            $ok = $importService->updateRecordStatus(
                $sessionId,
                $lineNo,
                $status,
                $body['note'] ?? null
            );

            if (!$ok) {
                Response::error('Gagal update record', 422);
            }

            Response::success(null, 'Record status updated');
        }
    ],

    // ── POST /import/submit/:session_id - Submit import
    [
        'method'  => 'POST',
        'path'    => '/import/submit/([a-z0-9_\.]+)$',
        'pattern' => true,
        'handler' => function($path, $method, $params, $db, $hooks) {
            $token = get_bearer_token();
            if (!$token || !verify_jwt($token)) {
                Response::unauthorized('Token required');
            }

            $payload = verify_jwt($token);
            if (!in_array($payload['role'], ['superadmin', 'admin'], true)) {
                Response::forbidden('Admin role required');
            }

            $sessionId = $params[1] ?? null;
            if (!$sessionId) {
                Response::error('Session ID required', 400);
            }

            $module = __MODULES['registry']->getInstance('csvimport');
            $importService = $module->getImportService();

            $result = $importService->submitImport($sessionId, (int)$payload['admin_id']);

            if (!$result['success']) {
                Response::error($result['error'] ?? 'Import failed', 422);
            }

            // Trigger hook
            $hooks->trigger('import.completed', [
                'session_id' => $sessionId,
                'result' => $result
            ]);

            Response::success($result, 'Import selesai', 201);
        }
    ],

    // ── GET /import/status/:session_id - Get import status
    [
        'method'  => 'GET',
        'path'    => '/import/status/([a-z0-9_\.]+)$',
        'pattern' => true,
        'handler' => function($path, $method, $params, $db, $hooks) {
            $token = get_bearer_token();
            if (!$token || !verify_jwt($token)) {
                Response::unauthorized('Token required');
            }

            $payload = verify_jwt($token);
            if (!in_array($payload['role'], ['superadmin', 'admin'], true)) {
                Response::forbidden('Admin role required');
            }

            $sessionId = $params[1] ?? null;
            if (!$sessionId) {
                Response::error('Session ID required', 400);
            }

            $module = __MODULES['registry']->getInstance('csvimport');
            $importService = $module->getImportService();

            $session = $importService->getImportSession($sessionId);
            if (!$session) {
                Response::notFound('Session tidak ditemukan');
            }

            $pending = count(array_filter($session['data']['records'], fn($r) => $r['status'] === 'pending'));
            $approved = count(array_filter($session['data']['records'], fn($r) => $r['status'] === 'approved'));
            $rejected = count(array_filter($session['data']['records'], fn($r) => $r['status'] === 'rejected'));

            Response::success([
                'session_id' => $sessionId,
                'status_summary' => [
                    'pending' => $pending,
                    'approved' => $approved,
                    'rejected' => $rejected,
                    'total' => $pending + $approved + $rejected
                ],
                'approval_percentage' => $pending + $approved + $rejected > 0 
                    ? round(($approved / ($pending + $approved + $rejected)) * 100, 2)
                    : 0
            ]);
        }
    ],

    // ── POST /import/bulk-action/:session_id - Bulk update records
    [
        'method'  => 'POST',
        'path'    => '/import/bulk-action/([a-z0-9_\.]+)$',
        'pattern' => true,
        'handler' => function($path, $method, $params, $db, $hooks) {
            $token = get_bearer_token();
            if (!$token || !verify_jwt($token)) {
                Response::unauthorized('Token required');
            }

            $payload = verify_jwt($token);
            if (!in_array($payload['role'], ['superadmin', 'admin'], true)) {
                Response::forbidden('Admin role required');
            }

            $sessionId = $params[1] ?? null;
            $body = get_json_body();

            if (!$sessionId) {
                Response::error('Session ID required', 400);
            }

            $action = $body['action'] ?? null; // 'approve_all', 'reject_all', 'approve_valid'
            $lineNos = $body['line_nos'] ?? []; // specific lines

            if (!$action) {
                Response::validationError(['action' => 'Action required']);
            }

            $module = __MODULES['registry']->getInstance('csvimport');
            $importService = $module->getImportService();

            $session = $importService->getImportSession($sessionId);
            if (!$session) {
                Response::notFound('Session tidak ditemukan');
            }

            $updated = 0;

            foreach ($session['data']['records'] as $record) {
                $shouldUpdate = false;

                switch ($action) {
                    case 'approve_all':
                        $shouldUpdate = true;
                        break;
                    case 'reject_all':
                        $shouldUpdate = true;
                        break;
                    case 'approve_valid':
                        $shouldUpdate = empty($record['error']);
                        break;
                    case 'approve_specific':
                        $shouldUpdate = in_array($record['line_no'], $lineNos);
                        break;
                }

                if ($shouldUpdate) {
                    $newStatus = ($action === 'reject_all') ? 'rejected' : 'approved';
                    if ($action === 'approve_valid' && !empty($record['error'])) {
                        $newStatus = 'rejected';
                    }

                    $importService->updateRecordStatus($sessionId, $record['line_no'], $newStatus);
                    $updated++;
                }
            }

            Response::success(['updated' => $updated], 'Bulk action selesai');
        }
    ]
];