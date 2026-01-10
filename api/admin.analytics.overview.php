<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/admin_auth.php';
admin_require_login();

$db = db();

// total laporan
$total_reports = $db->query("
    SELECT COUNT(*) FROM reports
")->fetchColumn();

// pending
$pending = $db->query("
    SELECT COUNT(*) FROM reports WHERE status='pending'
")->fetchColumn();

// distribusi reputasi
$dist = $db->query("
    SELECT label, COUNT(*) cnt
    FROM phone_reputation
    GROUP BY label
")->fetchAll(PDO::FETCH_KEY_PAIR);

json([
    'status' => 'ok',
    'stats' => [
        'total_reports' => $total_reports,
        'pending_reports' => $pending,
        'reputation_dist' => $dist
    ]
]);
