<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/admin_auth.php';

admin_require_login();
$db = db();

$data = json_decode(file_get_contents("php://input"), true);

$title       = trim($data['title'] ?? '');
$description = trim($data['description'] ?? '');
$phones      = $data['phones'] ?? [];
$categories  = $data['categories'] ?? [];

if (!$description || empty($phones)) {
    json(['status'=>'error','message'=>'invalid_input'],400);
}

$db->beginTransaction();

try {
    // laporan
    $stmt = $db->prepare("
        INSERT INTO reports (title, description, status, created_at)
        VALUES (?, ?, 'pending', NOW())
    ");
    $stmt->execute([$title, $description]);
    $report_id = $db->lastInsertId();

    // phones
    $stmt = $db->prepare("
        INSERT INTO report_phones (report_id, phone_number)
        VALUES (?, ?)
    ");
    foreach ($phones as $p) {
        $stmt->execute([$report_id, $p]);
    }

    // categories
    if ($categories) {
        $stmt = $db->prepare("
            INSERT INTO report_categories (report_id, category_id)
            VALUES (?, ?)
        ");
        foreach ($categories as $cid) {
            $stmt->execute([$report_id, $cid]);
        }
    }

    $db->commit();
    json(['status'=>'ok']);

} catch (Throwable $e) {
    $db->rollBack();
    json(['status'=>'error'],500);
}
