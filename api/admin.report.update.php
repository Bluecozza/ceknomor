<?php
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../modules/moderation.php';
require_once __DIR__ . '/../core/admin_auth.php';
admin_require_login();

$data = json_decode(file_get_contents("php://input"), true);

$id     = intval($data['id'] ?? 0);
$status = $data['status'] ?? '';

if (!$id || !in_array($status, ['approved', 'rejected'])) {
    json(['status' => 'error'], 400);
}

if (!update_report_status($id, $status)) {
    json(['status' => 'error'], 500);
}

json(['status' => 'ok']);
