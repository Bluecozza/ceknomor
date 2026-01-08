<?php

require_once __DIR__ . "/../core/cache.php";

function reputation_for_number(array $reports) {
    $REPORT_WEIGHT = [
        'missed_call' => 1,
        'telemarketer' => 2,
        'spam' => 3,
        'scam' => 5,
        'penipuan' => 7,
        'robocall' => 4
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

    // ambil number_id
    $stmt = $db->prepare("SELECT id FROM numbers WHERE number = ?");
    $stmt->execute([$phone_number]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return [];
    }

    // ambil laporan yang relevan untuk reputasi
    $stmt = $db->prepare("
        SELECT 
            category AS type,
            created_at
        FROM reports
        WHERE number_id = ?
          AND status = 'approved'
    ");

    $stmt->execute([$row['id']]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//////////////////////////////
function get_number_reputation(string $phone_number) {

    // 1. Cache
    $cache_key = 'rep:number:' . md5($phone_number);

if ($cached = cache_get($cache_key)) {
    if (!empty($cached['last_calculated'])) {
        return $cached;
    }
}


    $db = db();

	// 2. DB
	$stmt = $db->prepare(
		"SELECT * FROM phone_reputation WHERE phone_number = ?"
	);
	$stmt->execute([$phone_number]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);

	// hitung jumlah laporan terbaru
	$current_reports = fetch_reports_for_number($phone_number);
	$current_count   = count($current_reports);

	if (
		$row &&
		$row['report_count'] == $current_count &&
		strtotime($row['last_calculated']) > time() - 3600
	) {
		cache_set($cache_key, $row, 3600);
		return $row;
	}

    // 3. Recalculate
    $reports = fetch_reports_for_number($phone_number);
    $rep = reputation_for_number($reports);

    $data = [
        'phone_number' => $phone_number,
        'score' => $rep['score'],
        'label' => $rep['label'],
        'confidence' => $rep['confidence'],
        'report_count' => count($reports),
        'last_calculated' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
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