<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/cache.php';
require_once __DIR__ . '/reputation.php';

function update_report_status(int $id, string $status): bool
{
    if (!in_array($status, ['approved','rejected'])) {
        return false;
    }

    $db = db();
    $db->beginTransaction();

    try {
        // ambil semua nomor
        $stmt = $db->prepare("
            SELECT phone_number
            FROM report_phones
            WHERE report_id = ?
        ");
        $stmt->execute([$id]);
        $phones = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!$phones) throw new Exception('not_found');

        // update status
        $db->prepare("
            UPDATE reports SET status=? WHERE id=?
        ")->execute([$status,$id]);

        // reset cache reputasi
        foreach ($phones as $p) {
            cache_del('rep:number:' . md5($p));
            get_number_reputation($p);
        }

        $db->commit();
        return true;

    } catch (Throwable $e) {
        $db->rollBack();
        return false;
    }
}
