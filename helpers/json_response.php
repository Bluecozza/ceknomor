<?php

function json($data, int $code = 200)
{
    http_response_code($code);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $message, int $code = 400, array $meta = [])
{
    json([
        "success" => false,
        "error"   => $message,
        "meta"    => $meta
    ], $code);
}
