<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/admin_auth.php';

admin_require_login();
$db = db();

// DataTables params
$draw   = intval($_GET['draw'] ?? 1);
$start  = intval($_GET['start'] ?? 0);
$length = intval($_GET['length'] ?? 10);
$search = $_GET['search']['value'] ?? '';
$status = $_GET['status'] ?? 'pending';

$allowed = ['pending','approved','rejected'];
if(!in_array($status,$allowed,true)){
  $status='pending';
}

// total
$stmt = $db->prepare("
  SELECT COUNT(*) 
  FROM reports 
  WHERE status = ?
");
$stmt->execute([$status]);
$recordsTotal = (int)$stmt->fetchColumn();

// filter
$where = "WHERE r.status = :status";
$params = ['status' => $status];

if ($search) {
  $where .= " AND (
    rp.phone_number LIKE :s OR
    r.description LIKE :s
  )";
  $params['s'] = "%$search%";
}


// filtered count
$stmt = $db->prepare("
  SELECT COUNT(DISTINCT r.id)
  FROM reports r
  JOIN report_phones rp ON rp.report_id = r.id
  $where
");
$stmt->execute($params);
$recordsFiltered = (int)$stmt->fetchColumn();


// data
$stmt = $db->prepare("
  SELECT
  r.id,
  GROUP_CONCAT(DISTINCT rp.phone_number SEPARATOR ', ') AS numbers,
  GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') AS category,
  r.description,
  r.created_at
FROM reports r
JOIN report_phones rp ON rp.report_id = r.id
LEFT JOIN report_categories rc ON rc.report_id = r.id
LEFT JOIN categories c ON c.id = rc.category_id
$where
GROUP BY r.id
ORDER BY r.created_at DESC
LIMIT :start, :len
");

foreach ($params as $k => $v) {
  $stmt->bindValue(":$k", $v);
}
$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':len', $length, PDO::PARAM_INT);
$stmt->execute();

json([
  "draw"=>$draw,
  "recordsTotal"=>$recordsTotal,
  "recordsFiltered"=>$recordsFiltered,
  "data"=>$stmt->fetchAll(PDO::FETCH_ASSOC)
]);
