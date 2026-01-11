<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . "/../core/cache.php";

function reputation_for_number(array $reports) {
    $REPORT_WEIGHT = [
		'penipuan' => 7,
		'fitnah' => 3,
		'rasis' => 3,
		'pencemaran' => 3,
		'kekerasan' => 3,
		'perdagangan' => 7,
		'narkoba' => 7,
		'pencurian' => 1,
		'perampokan' => 7,
		'spam' => 3,
		'bully' => 3,
		'safe' => 0
    ];

    $total_score = 0;
    $total_reports = count($reports);

    foreach ($reports as $r) {
        $weight = $REPORT_WEIGHT[$r['type']] ?? 1;
        $days = (time() - strtotime($r['created_at'])) / 86400;

        $decay = match (true) {
            $days <= 30 => 1.0,
            $days <= 90 => 0.8,
            $days <= 180 => 0.6,
            $days <= 360 => 0.4,
            default => 0.2
        };

        $total_score += $weight * $decay;
    }

    $score = round(log($total_score + 1) * 20);

    $label = $score < 5 ? 'safe'
           : ($score < 30 ? 'suspicious' : 'high_risk');

    $confidence = min(100, round((1 - exp(-$total_reports / 10)) * 100));

    return compact('score', 'label', 'confidence');
}
////////////////////////////
function fetch_reports_for_number(string $phone_number): array
{
    $db = db();

    $stmt = $db->prepare("
        SELECT
            c.name AS type,
            r.created_at
        FROM report_phones rp
        JOIN reports r ON r.id = rp.report_id
        LEFT JOIN report_categories rc ON rc.report_id = r.id
        LEFT JOIN categories c ON c.id = rc.category_id
        WHERE rp.phone_number = ?
          AND r.status = 'approved'
    ");

    $stmt->execute([$phone_number]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


//////////////////////////////
function get_number_reputation(string $phone_number) {

    $cache_key = 'rep:number:' . md5($phone_number);

    // 1. Cache
    if ($cached = cache_get($cache_key)) {
        return $cached;
    }

    $db = db();

    // ambil laporan approved TERBARU
    $reports = fetch_reports_for_number($phone_number);
    $report_count = count($reports);

    // 2. DB
    $stmt = $db->prepare(
        "SELECT * FROM phone_reputation WHERE phone_number = ?"
    );
    $stmt->execute([$phone_number]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // validasi DB cache
    if (
        $row &&
        $row['report_count'] == $report_count &&
        strtotime($row['last_calculated']) > time() - 3600
    ) {
        cache_set($cache_key, $row, 3600);
        return $row;
    }

    // 3. Recalculate
    $rep = reputation_for_number($reports);

    $data = [
        'phone_number'    => $phone_number,
        'score'           => $rep['score'],
        'label'           => $rep['label'],
        'confidence'      => $rep['confidence'],
        'report_count'    => $report_count,
        'last_calculated' => date('Y-m-d H:i:s'),
        'updated_at'      => date('Y-m-d H:i:s')
    ];

    // 4. Upsert
    $stmt = $db->prepare(
        "INSERT INTO phone_reputation
        (phone_number, score, label, confidence, report_count, last_calculated, updated_at)
        VALUES (:phone_number, :score, :label, :confidence, :report_count, :last_calculated, :updated_at)
        ON DUPLICATE KEY UPDATE
            score = VALUES(score),
            label = VALUES(label),
            confidence = VALUES(confidence),
            report_count = VALUES(report_count),
            last_calculated = VALUES(last_calculated),
            updated_at = VALUES(updated_at)"
    );
    $stmt->execute($data);

    // 5. Cache
    cache_set($cache_key, $data, 3600);

    return $data;
}
/////////////////////////////