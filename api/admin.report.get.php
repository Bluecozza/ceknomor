<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/admin_auth.php';

admin_require_login();
$db = db();

$id = intval($_GET['id'] ?? 0);
if (!$id) json(['status'=>'error'],400);

// report
$stmt = $db->prepare("
  SELECT id, title, description, status
  FROM reports
  WHERE id = ?
");
$stmt->execute([$id]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$report) json(['status'=>'error'],404);

// phones
$stmt = $db->prepare("
  SELECT phone_number
  FROM report_phones
  WHERE report_id = ?
");
$stmt->execute([$id]);
$report['phones'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

// categories
$stmt = $db->prepare("
  SELECT c.id, c.name
  FROM report_categories rc
  JOIN categories c ON c.id = rc.category_id
  WHERE rc.report_id = ?
");
$stmt->execute([$id]);
$report['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

json(['status'=>'ok','report'=>$report]);
