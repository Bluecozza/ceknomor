<?php

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/normalize.php';
require_once __DIR__ . '/../core/cache.php';
require_once __DIR__ . '/../core/response.php';

function batch_lookup(array $numbers)
{
    $pdo = db();

    $results = [];
    $missing = [];

    /**
     * 1) Normalisasi & cek cache
     */
    foreach ($numbers as $raw) {

        $num = normalize_number($raw);
        if (!$num) continue;

        $key = cache_key_number_summary($num);

        if ($cached = cache_get($key)) {
            $results[$num] = $cached;
        } else {
            $missing[] = $num;
        }
    }

    /**
     * Jika semua nomor sudah ada di cache → selesai
     */
    if (empty($missing)) {
        return array_values($results);
    }

    /**
     * 2) Query DB hanya untuk nomor yang belum ada dalam cache
     */
    $placeholders = implode(',', array_fill(0, count($missing), '?'));

    $stmt = $pdo->prepare("
        SELECT id, number
        FROM numbers
        WHERE number IN ($placeholders)
    ");
    $stmt->execute($missing);

    $found = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $foundMap = [];
    foreach ($found as $row) {
        $foundMap[$row['number']] = $row;
    }

    /**
     * 3) Process missing numbers
     */
    foreach ($missing as $num) {

        // nomor tidak ada di DB
        if (!isset($foundMap[$num])) {

            $notFound = [
                "status" => "not_found",
                "number" => $num
            ];

            // TTL pendek agar cepat di-refresh
            cache_set(cache_key_number_summary($num), $notFound, 120);

            $results[$num] = $notFound;
            continue;
        }

        /**
         * Ambil laporan terbaru per nomor
         */
        $stmt = $pdo->prepare("
            SELECT category, description, created_at
            FROM reports
            WHERE number_id = ?
              AND status = 'publish'
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$foundMap[$num]['id']]);
        $last = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($last) {
            $data = [
                "status" => "single",
                "number_id" => $foundMap[$num]['id'],
                "number"   => $num,
                "report"   => $last
            ];
        } else {
            $data = [
                "status" => "multiple",
                "number_id" => $foundMap[$num]['id'],
                "number"   => $num,
                "total"    => 0
            ];
        }

        // simpan cache
        cache_set(cache_key_number_summary($num), $data, 300);

        $results[$num] = $data;
    }

    /**
     * Return secara urut berdasarkan input awal
     */
    return array_values($results);
}
