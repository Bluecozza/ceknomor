<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/cache.php';
require_once __DIR__ . '/reputation.php';

function update_report_status(int $report_id, string $status): bool
{
    if (!in_array($status, ['approved', 'rejected'])) {
        return false;
    }

    $db = db();
    $db->beginTransaction();

    try {
        // ambil number_id
        $stmt = $db->prepare("
            SELECT r.number_id, n.number
            FROM reports r
            JOIN numbers n ON n.id = r.number_id
            WHERE r.id = ?
        ");
        $stmt->execute([$report_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception("report_not_found");
        }

        // update status laporan
        $db->prepare("
            UPDATE reports
            SET status = ?
            WHERE id = ?
        ")->execute([$status, $report_id]);

        // hapus cache reputasi
        $cache_key = 'rep:number:' . md5($row['number']);
        cache_del($cache_key);

        // force recalc reputasi
        get_number_reputation($row['number']);

        $db->commit();
        return true;

    } catch (Throwable $e) {
        $db->rollBack();
        return false;
    }
}
