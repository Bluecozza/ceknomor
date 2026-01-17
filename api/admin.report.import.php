<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/admin_auth.php';
require_once __DIR__ . '/../modules/reputation.php';

admin_require_login();
$db = db();

if (!isset($_FILES['file'])) {
    json(['status'=>'error','message'=>'no_file'],400);
}

$file = fopen($_FILES['file']['tmp_name'], 'r');
$header = fgetcsv($file);
//['title','description','status','created_at','phones','categories']);
$expected = ['title','description','status','created_at','phones','categories'];
if ($header !== $expected) {
    json(['status'=>'error','message'=>'invalid_header'],400);
}

$db->beginTransaction();

try {
    while (($row = fgetcsv($file)) !== false) {
        [$title,$desc,$status,$created,$phones,$cats] = $row;

        $status = in_array($status,['pending','approved','rejected']) ? $status : 'pending';

        $stmt = $db->prepare("
          INSERT INTO reports (title,description,status,created_at)
          VALUES (?,?,?,?)
        ");
        $stmt->execute([
          $title,
          $desc,
          $status,
          $created ?: date('Y-m-d H:i:s')
        ]);

        $rid = $db->lastInsertId();

        // phones
        $stmtP = $db->prepare("
          INSERT INTO report_phones (report_id,phone_number)
          VALUES (?,?)
        ");
        foreach (explode('|',$phones) as $p) {
            $p = trim($p);
            if ($p) $stmtP->execute([$rid,$p]);
        }

        // categories
        if ($cats) {
            $stmtC = $db->prepare("SELECT id FROM categories WHERE name=?");
            $stmtI = $db->prepare("
              INSERT INTO report_categories (report_id,category_id)
              VALUES (?,?)
            ");

            foreach (explode('|',$cats) as $c) {
                $stmtC->execute([trim($c)]);
                if ($cid = $stmtC->fetchColumn()) {
                    $stmtI->execute([$rid,$cid]);
                }
            }
        }
    }

    $db->commit();
    json(['status'=>'ok']);

} catch (Throwable $e) {
    $db->rollBack();
    json(['status'=>'error'],500);
}
