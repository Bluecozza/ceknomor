<?php

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/normalize.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../modules/reputation.php';

$raw = $_GET['number'] ?? '';
if (!$raw) {
    json(["status" => "error", "message" => "missing_number"], 400);
}

$number = normalize_number($raw);
$db = db();
$rep=get_number_reputation($number);

/**
 * Ambil semua laporan (SEMUA STATUS)
 */
$stmt = $db->prepare("
    SELECT
        r.id,
        r.title,
        r.description,
        r.created_at,
        r.status,
        COALESCE(
          GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ','),
          'unknown'
        ) AS category
    FROM report_phones rp
    JOIN reports r ON r.id = rp.report_id
    LEFT JOIN report_categories rc ON rc.report_id = r.id
    LEFT JOIN categories c ON c.id = rc.category_id
    WHERE rp.phone_number = ?
    #  AND r.status = 'approved'
    GROUP BY r.id, r.status, r.created_at
    ORDER BY r.created_at DESC
    LIMIT 50
");

$stmt->execute([$number]);


json([
    "status"     => "ok",
    "number"     => $number,
    "risk"       => $rep['label'],
    "confidence" => $rep['confidence'],
    "reports"    => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);

