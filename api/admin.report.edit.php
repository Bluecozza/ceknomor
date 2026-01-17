<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/admin_auth.php';
require_once __DIR__ . '/../modules/reputation.php';

admin_require_login();
$db = db();

$data = json_decode(file_get_contents("php://input"), true);

$id          = intval($data['id'] ?? 0);
$title       = trim($data['title'] ?? '');
$description = trim($data['description'] ?? '');
$phones      = $data['phones'] ?? [];
$categories  = $data['categories'] ?? [];

if (!$id || !$description || empty($phones)) {
  json(['status'=>'error'],400);
}

$db->beginTransaction();

try {
    // update report
    $db->prepare("
      UPDATE reports
      SET title=?, description=?
      WHERE id=?
    ")->execute([$title,$description,$id]);

    // phones (reset)
    $db->prepare("DELETE FROM report_phones WHERE report_id=?")->execute([$id]);
    $stmt = $db->prepare("
      INSERT INTO report_phones (report_id, phone_number)
      VALUES (?,?)
    ");
    foreach ($phones as $p) {
        $stmt->execute([$id,$p]);
    }

    // categories (reset)
    $db->prepare("DELETE FROM report_categories WHERE report_id=?")->execute([$id]);
    if ($categories) {
        $stmt = $db->prepare("
          INSERT INTO report_categories (report_id, category_id)
          VALUES (?,?)
        ");
        foreach ($categories as $cid) {
            $stmt->execute([$id,$cid]);
        }
    }

    // refresh reputation cache
    foreach ($phones as $p) {
        cache_del('rep:number:' . md5($p));
        get_number_reputation($p);
    }

    $db->commit();
    json(['status'=>'ok']);

} catch (Throwable $e) {
    $db->rollBack();
    json(['status'=>'error'],500);
}
