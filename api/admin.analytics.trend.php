<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/admin_auth.php';
admin_require_login();

$db = db();

$stmt = $db->query("
    SELECT
        DATE(created_at) d,
        COUNT(*) c
    FROM reports
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY d
    ORDER BY d
");

json([
    'status'=>'ok',
    'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)
]);
