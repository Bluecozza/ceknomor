<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/admin_auth.php';
admin_require_login();

$db = db();

$stmt = $db->query("
    SELECT
        phone_number,
        score,
        label,
        confidence,
        report_count,
        last_calculated
    FROM phone_reputation
    ORDER BY
        FIELD(label,'high_risk','suspicious','safe'),
        score DESC
    LIMIT 100
");

json([
    'status' => 'ok',
    'numbers' => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);
