<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';

$db = db();

try {
    $db->beginTransaction();

    // === VALIDASI ===
    $title = trim($_POST['title'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    if (!$title || !$desc) {
        throw new Exception('Judul dan deskripsi wajib diisi');
    }

    // === INSERT REPORT ===
    $stmt = $db->prepare("
      INSERT INTO reports
      (title, description, full_description, loss_amount,
       chronology_link, reporter_name, reporter_contact, status)
      VALUES (?,?,?,?,?,?,?,'pending')
    ");
    $stmt->execute([
      $title,
      $desc,
      $_POST['full_description'] ?? null,
      $_POST['loss_amount'] ?? null,
      $_POST['chronology_link'] ?? null,
      $_POST['reporter_name'] ?? null,
      $_POST['reporter_contact'] ?? null
    ]);

    $report_id = $db->lastInsertId();

    // === PHONES ===
    $rawPhones = $_POST['phones'] ?? '';
if (!is_array($rawPhones)) {
    $phones = [$rawPhones];
}

$phones = array_unique(array_filter($rawPhones));

    $stmtPhone = $db->prepare("
      INSERT INTO report_phones (report_id, phone_number)
      VALUES (?,?)
    ");
    $stmtNumber = $db->prepare("
      INSERT INTO numbers (number, last_report_at)
      VALUES (?, NOW())
      ON DUPLICATE KEY UPDATE last_report_at = NOW()
    ");

	foreach ($phones as $phone) {
		$phone = preg_replace('/[^0-9]/', '', $phone);
		if (strlen($phone) < 8) continue;

		$stmtPhone->execute([$report_id, $phone]);
		$stmtNumber->execute([$phone]);
	}

    // === CATEGORIES ===
    foreach ($_POST['categories'] ?? [] as $cid) {
        $db->prepare("
          INSERT INTO report_categories (report_id, category_id)
          VALUES (?,?)
        ")->execute([$report_id, (int)$cid]);
    }

    // === BANK ACCOUNT ===
    if (!empty($_POST['bank_name']) && !empty($_POST['account_number'])) {
        $db->prepare("
          INSERT INTO report_bank_accounts (report_id, bank_name, account_number)
          VALUES (?,?,?)
        ")->execute([
          $report_id,
          $_POST['bank_name'],
          $_POST['account_number']
        ]);
    }

    // === ATTACHMENTS ===
    foreach ($_FILES['attachments']['tmp_name'] ?? [] as $i => $tmp) {
        if (!$tmp) continue;
        if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($_FILES['attachments']['size'][$i] > 1024 * 1024) {
            throw new Exception('File terlalu besar (maks 1MB)');
        }

        $ext = pathinfo($_FILES['attachments']['name'][$i], PATHINFO_EXTENSION);
        $path = '/uploads/' . uniqid() . '.' . strtolower($ext);

        move_uploaded_file($tmp, __DIR__ . '/..' . $path);

        $db->prepare("
          INSERT INTO report_attachments
          (report_id, file_path, file_size, file_type)
          VALUES (?,?,?,?)
        ")->execute([
          $report_id,
          $path,
          $_FILES['attachments']['size'][$i],
          $_FILES['attachments']['type'][$i]
        ]);
    }

    $db->commit();
    json(['status'=>'ok']);

} catch (Exception $e) {
    $db->rollBack();
    json(['status'=>'error', 'message'=>$e->getMessage()], 400);
}
