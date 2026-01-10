<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/admin_auth.php';

admin_require_login();

$status = $_GET['status'] ?? 'pending';
$allowed = ['pending', 'approved', 'rejected'];

if (!in_array($status, $allowed, true)) {
    $status = 'pending';
}

$db = db();

$stmt = $db->prepare("
    SELECT 
        r.id,
        n.number,
        r.category,
        r.description,
        r.status,
        r.created_at
    FROM reports r
    JOIN numbers n ON n.id = r.number_id
    WHERE r.status = :status
    ORDER BY r.created_at DESC
    LIMIT 100
");

$stmt->execute(['status' => $status]);

json([
    'reports' => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);
