<?php

function normalize_phone($raw) {
    $num = preg_replace('/[^0-9+]/', '', $raw);

    // contoh normalisasi sederhana
    if (substr($num, 0, 1) === "0") {
        $num = "62" . substr($num, 1);
    }

    return $num;
}

function cache_key_phone($phone) {
    return "phone:lookup:" . $phone;
}
