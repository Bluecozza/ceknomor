<?php

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/normalize.php';
require_once __DIR__ . '/../core/cache.php';

function search_number(string $input): array
{
    $pdo = db();

    $number = normalize_number($input);
    $cacheKey = phone_cache_key($number);

    if ($cached = cache_get($cacheKey)) {
        return $cached;
    }

    /**
     * Cari nomor
     */
    $stmt = $pdo->prepare("
        SELECT id
        FROM numbers
        WHERE number = ?
        LIMIT 1
    ");
    $stmt->execute([$number]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
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

    /**
     * Hitung laporan per status
     */
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) AS total
        FROM reports
        WHERE number_id = ?
        GROUP BY status
    ");
    $stmt->execute([$row['id']]);

    $summary = [
        "approved" => 0,
        "pending"  => 0,
        "rejected" => 0
    ];

    $total = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $summary[$r['status']] = (int) $r['total'];
        $total += (int) $r['total'];
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
