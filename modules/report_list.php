<?php

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/normalize.php';
require_once __DIR__ . '/../core/response.php';

$raw = $_GET['number'] ?? '';
if (!$raw) {
    json(["status" => "error", "message" => "missing_number"], 400);
}

$number = normalize_number($raw);
$pdo = db();

/**
 * Ambil ID nomor
 */
$stmt = $pdo->prepare("SELECT id FROM numbers WHERE number = ?");
$stmt->execute([$number]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    json([
        "status"  => "ok",
        "number"  => $number,
        "reports" => []
    ]);
}

/**
 * Ambil semua laporan (SEMUA STATUS)
 */
$stmt = $pdo->prepare("
    SELECT category, description, status, created_at
    FROM reports
    WHERE number_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$row['id']]);

json([
    "status"  => "ok",
    "number"  => $number,
    "reports" => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);
