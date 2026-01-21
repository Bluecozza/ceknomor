<?php

require "./core/db.php";
require "./core/normalize.php";
require "./core/response.php";
require "./core/rate_limit.php";
require "./core/api_signature.php";
$path = $_GET["action"] ?? null;

switch ($path) {

  case "search":
    require_once __DIR__ . "/modules/search.php";
    require_once __DIR__ . "/core/response.php";
    require_once __DIR__ . "/core/rate_limit.php";

    rate_limit("search", 25, 60);

    $query = trim($_GET["query"] ?? "");

    if ($query === "") {
        json(["error" => "query_required"], 400);
    }
    $data = search_number($query);

    json($data);
    break;

case "batch_lookup":
    require_once __DIR__ . "/modules/batch_lookup.php";
    require_once __DIR__ . "/core/response.php";
    require_once __DIR__ . "/core/rate_limit.php";

    rate_limit("batch_lookup", 5, 60); // 5x / menit per client

    $payload = json_decode(file_get_contents("php://input"), true);

    if (!isset($payload["numbers"]) || !is_array($payload["numbers"])) {
        json(["error" => "numbers_array_required"], 400);
    }

    if (count($payload["numbers"]) > 100) {
        json(["error" => "max_100_numbers"], 429);
    }

    $data = batch_lookup($payload["numbers"]);

    json($data);
    break;


  case "report.add":
    require "./modules/report_add.php";
    break;

  case "report.list":
	verify_signature();
    require "./modules/report_list.php";
    break;

  case "report.detail":
    require "./modules/report_detail.php";
    break;

  default:
    json(["error" => "Invalid endpoint"], 404);
}
