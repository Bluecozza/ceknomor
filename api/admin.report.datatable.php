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
$total = $db->prepare("
  SELECT COUNT(*) 
  FROM reports r
  WHERE r.status = ?
");
$total->execute([$status]);
$recordsTotal = $total->fetchColumn();

// filter
$where = "WHERE r.status = :status";
$params = ['status'=>$status];

if($search){
  $where .= " AND (
    n.number LIKE :s OR
    r.category LIKE :s OR
    r.description LIKE :s
  )";
  $params['s'] = "%$search%";
}

// filtered count
$count = $db->prepare("
  SELECT COUNT(*) 
  FROM reports r
  JOIN numbers n ON n.id=r.number_id
  $where
");
$count->execute($params);
$recordsFiltered = $count->fetchColumn();

// data
$stmt = $db->prepare("
  SELECT 
    r.id,
    n.number,
    r.category,
    r.description,
    r.created_at
  FROM reports r
  JOIN numbers n ON n.id=r.number_id
  $where
  ORDER BY r.created_at DESC
  LIMIT :start,:len
");

foreach($params as $k=>$v){
  $stmt->bindValue(":$k",$v);
}
$stmt->bindValue(':start',$start,PDO::PARAM_INT);
$stmt->bindValue(':len',$length,PDO::PARAM_INT);
$stmt->execute();

json([
  "draw"=>$draw,
  "recordsTotal"=>$recordsTotal,
  "recordsFiltered"=>$recordsFiltered,
  "data"=>$stmt->fetchAll(PDO::FETCH_ASSOC)
]);
