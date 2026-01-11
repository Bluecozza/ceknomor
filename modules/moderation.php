<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/cache.php';
require_once __DIR__ . '/reputation.php';

function update_report_status(int $report_id, string $status): bool
{
    if (!in_array($status, ['approved', 'rejected'], true)) {
        return false;
    }

    $db = db();
    $db->beginTransaction();

    try {
        // pastikan report ada
        $stmt = $db->prepare("SELECT id FROM reports WHERE id = ?");
        $stmt->execute([$report_id]);
        if (!$stmt->fetch()) {
            throw new Exception('report_not_found');
        }

        // update status laporan
        $stmt = $db->prepare("
            UPDATE reports
            SET status = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $report_id]);

        // ambil semua nomor pada laporan
        $stmt = $db->prepare("
            SELECT phone_number
            FROM report_phones
            WHERE report_id = ?
        ");
        $stmt->execute([$report_id]);
        $phones = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // recalc reputasi per nomor
        foreach ($phones as $phone) {
            // hapus cache
            $cache_key = 'rep:number:' . md5($phone);
            cache_del($cache_key);

            // hitung ulang reputasi
            get_number_reputation($phone);
        }

        $db->commit();
        return true;

    } catch (Throwable $e) {
        $db->rollBack();
        error_log('[update_report_status] ' . $e->getMessage());
        return false;
    }
}
