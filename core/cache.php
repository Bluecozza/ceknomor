<?php

function redis_instance()
{
    static $r = null;

    if ($r !== null) {
        return $r;
    }

    try {
        $r = new Redis();
        $r->connect('127.0.0.1', 6379, 1.0);
        $r->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
    } catch (Throwable $e) {
        $r = null; // fallback — app tetap jalan tanpa redis
    }

    return $r;
}

function cache_get($key)
{
    $r = redis_instance();
    if (!$r) return false;

    return $r->get($key);
}

function cache_set($key, $value, $ttl = 600)
{
    $r = redis_instance();
    if (!$r) return false;

    return $r->setex($key, $ttl, $value);
}

function cache_del($key)
{
    $r = redis_instance();
    if ($r) {
        $r->del($key);
    }
}

function cache_key_number_summary($number){
    return "number:{$number}:summary";
}

function cache_key_number_reports($number){
    return "number:{$number}:reports";
}

function cache_key_number_reputation(string $number): string {
    return 'rep:number:' . md5($number);
}

/**
 * Reset semua cache yang berkaitan dengan nomor
 */
function reset_all_number_cache(string $number): void
{
    cache_del(phone_cache_key($number));
    cache_del(cache_key_number_summary($number));
    cache_del(cache_key_number_reports($number));
    cache_del('rep:number:' . md5($number));
}
