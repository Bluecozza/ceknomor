<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/admin_auth.php';

admin_require_login();
$db = db();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="reports_export.csv"');

$out = fopen('php://output', 'w');

// header         [$title,$desc,$phones,$cats,$status,$created]
fputcsv($out, ['title','description','status','created_at','phones','categories']);

$stmt = $db->query("
  SELECT
    r.title,
    r.description,
    r.status,
    r.created_at,
    GROUP_CONCAT(DISTINCT rp.phone_number SEPARATOR '|') AS phones,
    GROUP_CONCAT(DISTINCT c.name SEPARATOR '|') AS categories
  FROM reports r
  LEFT JOIN report_phones rp ON rp.report_id = r.id
  LEFT JOIN report_categories rc ON rc.report_id = r.id
  LEFT JOIN categories c ON c.id = rc.category_id
  GROUP BY r.id
  ORDER BY r.created_at DESC
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, $row);
}
fclose($out);
exit;
