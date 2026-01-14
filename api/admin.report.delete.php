<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/admin_auth.php';

admin_require_login();
$db = db();

$data = json_decode(file_get_contents("php://input"), true);
$id = intval($data['id'] ?? 0);

if (!$id) json(['status'=>'error'],400);

$db->beginTransaction();

try {
    $db->prepare("DELETE FROM report_categories WHERE report_id=?")->execute([$id]);
    $db->prepare("DELETE FROM report_phones WHERE report_id=?")->execute([$id]);
    $db->prepare("DELETE FROM reports WHERE id=?")->execute([$id]);

    $db->commit();
    json(['status'=>'ok']);

} catch (Throwable $e) {
    $db->rollBack();
    json(['status'=>'error'],500);
}
