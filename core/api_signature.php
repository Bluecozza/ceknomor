<?php
define('API_SECRET', '272018');//Ubah (samakan di client)

function verify_signature() {
    $r = $_GET['r'] ?? '';
    $h = $_GET['h'] ?? '';

    if (!$r || !$h) {
        json(['error'=>'missing_signature'], 401);
    }

    // max toleransi 5 menit
    if (abs(time() - (int)$r) > 300) {
        json(['error'=>'signature_expired'], 401);
    }

    $expected = hash('sha256', $r . API_SECRET);

    if (!hash_equals($expected, $h)) {
        json(['error'=>'invalid_signature'], 401);
    }
}