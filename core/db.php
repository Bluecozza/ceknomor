<?php

$DB = new PDO(
  "mysql:host=localhost;dbname=phone;charset=utf8mb4",
  "root",
  "",
  [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]
);

function db() {
  global $DB;
  return $DB;
}
