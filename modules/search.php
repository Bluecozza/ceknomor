<?php

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/normalize.php';
require_once __DIR__ . '/../core/cache.php';

function search_number(string $input): array
{
    $db = db();

    $number   = normalize_number($input);
    $cacheKey = phone_cache_key($number);

    // ===== CACHE =====
    if ($cached = cache_get($cacheKey)) {
        return $cached;
    }

    // ===== CEK APAKAH NOMOR ADA =====
    $stmt = $db->prepare("
        SELECT 1
        FROM report_phones
        WHERE phone_number = ?
        LIMIT 1
    ");
    $stmt->execute([$number]);

    if (!$stmt->fetchColumn()) {
        return [
            "number"  => $number,
            "exists"  => false,
            "total"   => 0,
            "summary" => [
                "approved" => 0,
                "pending"  => 0,
                "rejected" => 0
            ]
        ];
    }

    // ===== HITUNG LAPORAN PER STATUS =====
    $stmt = $db->prepare("
        SELECT
            r.status,
            COUNT(DISTINCT r.id) AS total
        FROM report_phones rp
        JOIN reports r ON r.id = rp.report_id
        WHERE rp.phone_number = ?
        GROUP BY r.status
    ");
    $stmt->execute([$number]);

    $summary = [
        "approved" => 0,
        "pending"  => 0,
        "rejected" => 0
    ];

    $total = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $summary[$row['status']] = (int)$row['total'];
        $total += (int)$row['total'];
    }

    $response = [
        "number"  => $number,
        "exists"  => true,
        "total"   => $total,
        "summary" => $summary
    ];

    cache_set($cacheKey, $response, 600);

    return $response;
}
