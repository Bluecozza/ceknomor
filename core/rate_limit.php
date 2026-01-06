<?php

require_once __DIR__ . "/db.php";

function client_hash()
{
  $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
  return hash("sha256", $ip . '|' . $ua);
}

/**
 * limitX — batas request dalam X detik
 */
function rate_limit($action, $limit, $seconds)
{
  $db = db();
  $hash = client_hash();

  // hitung request dalam window waktu
  $stmt = $db->prepare("
    SELECT COUNT(*) AS c
    FROM rate_logs
    WHERE client_hash = ?
      AND action = ?
      AND created_at >= (NOW() - INTERVAL ? SECOND)
  ");
  $stmt->execute([$hash, $action, $seconds]);
  $row = $stmt->fetch();

  if ($row['c'] >= $limit) {

    http_response_code(429);
    header('Content-Type: application/json');

    echo json_encode([
      "error"  => "rate_limited",
      "retry"  => $seconds,
      "reason" => "too_many_requests"
    ]);

    exit;
  }

  // simpan log (micro-weight)
  $stmt = $db->prepare("
    INSERT INTO rate_logs (client_hash, action)
    VALUES (?, ?)
  ");
  $stmt->execute([$hash, $action]);
}
//membatasi jumlah laporan masuk per hari
daily_cap("report_add", 2000); // max laporan / hari
function daily_cap($action, $limitPerDay)
{
  $db = db();
  $hash = client_hash();

  $stmt = $db->prepare("
    SELECT COUNT(*) AS c
    FROM rate_logs
    WHERE client_hash = ?
      AND action = ?
      AND created_at >= CURDATE()
  ");
  $stmt->execute([$hash, $action]);
  $row = $stmt->fetch();

  if ($row['c'] >= $limitPerDay) {
    http_response_code(429);
    echo json_encode([
      "error" => "daily_limit_reached"
    ]);
    exit;
  }
}
