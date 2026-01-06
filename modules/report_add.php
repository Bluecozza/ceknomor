<?php

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/normalize.php';
require_once __DIR__ . '/../core/cache.php';
require_once __DIR__ . '/../core/rate_limit.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../modules/reputation.php';

/**
 * Rate limit:
 * max 3 laporan / 5 menit / client
 */
rate_limit("report_add", 3, 300);

/**
 * Ambil payload JSON
 */
$data = json_decode(file_get_contents("php://input"), true);

$rawNumber  = trim($data['number'] ?? '');
$category   = trim($data['category'] ?? 'unknown');
$description = trim($data['description'] ?? '');

if ($rawNumber === '' || $description === '') {
    json([
        "status" => "error",
        "message" => "number_and_description_required"
    ], 400);
}

/**
 * Normalisasi nomor
 */
$number = normalize_number($rawNumber);
cache_del(phone_cache_key($number));


$db = db();

try {

    $db->beginTransaction();

    /**
     * Pastikan nomor ada
     */
    $stmt = $db->prepare("
        SELECT id FROM numbers WHERE number = ? LIMIT 1
    ");
    $stmt->execute([$number]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $stmt = $db->prepare("
            INSERT INTO numbers (number, created_at)
            VALUES (?, NOW())
        ");
        $stmt->execute([$number]);
        $number_id = $db->lastInsertId();
    } else {
        $number_id = $row['id'];
    }

    /**
     * Hash reporter (anonim)
     */
    $reporter_hash = hash(
        'sha256',
        $_SERVER['REMOTE_ADDR'] . ($_SERVER['HTTP_USER_AGENT'] ?? '')
    );

    /**
     * Insert laporan (PENDING untuk moderasi)
     */
    $stmt = $db->prepare("
        INSERT INTO reports (
            number_id,
            category,
            description,
            reporter_hash,
            status,
            created_at
        )
        VALUES (?, ?, ?, ?, 'pending', NOW())
    ");

    $stmt->execute([
        $number_id,
        $category,
        $description,
        $reporter_hash
    ]);

    /**
     * Update last_report_at
     */
    $db->prepare("
        UPDATE numbers
        SET last_report_at = NOW()
        WHERE id = ?
    ")->execute([$number_id]);

    $db->commit();
	
} catch (Throwable $e) {

    $db->rollBack();

    json([
        "status" => "error",
        "message" => "failed_to_save_report"
    ], 500);
}

/**
 * Cache invalidation
 */
/**
 * Cache invalidation
 */
cache_del(phone_cache_key($number));
cache_del(cache_key_number_summary($number));
cache_del(cache_key_number_reports($number));
cache_del(cache_key_number_reputation($number));

json([
    "status"    => "ok",
    "number_id" => $number_id
]);
