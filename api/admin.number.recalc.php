<?php
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../modules/reputation.php';
require_once __DIR__ . '/../core/cache.php';

$data = json_decode(file_get_contents("php://input"), true);
$number = trim($data['number'] ?? '');

if (!$number) {
    json(['status'=>'error'],400);
}

// reset cache
$cache_key = 'rep:number:' . md5($number);
cache_del($cache_key);

// force recalc
$rep = get_number_reputation($number);

json([
    'status' => 'ok',
    'reputation' => $rep
]);
