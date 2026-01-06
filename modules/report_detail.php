<?php

$id = intval($_GET["id"] ?? 0);

$stmt = db()->prepare("
  SELECT id, category, description, created_at
  FROM reports
  WHERE id = ? AND status='publish'
");
$stmt->execute([$id]);

$row = $stmt->fetch();

if (!$row) json(["error" => "not found"], 404);

json(["status" => "ok", "report" => $row]);
