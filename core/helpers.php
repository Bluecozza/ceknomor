<?php
/**
 * ./core/helpers.php
 * Fungsi utilitas global — kompatibel PHP 7.4+
 */

// ── PHP 7.4 Polyfills ─────────────────────────────────────────
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $h, string $n): bool {
        return $n === '' || strncmp($h, $n, strlen($n)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $h, string $n): bool {
        return $n === '' || substr($h, -strlen($n)) === $n;
    }
}
if (!function_exists('str_contains')) {
    function str_contains(string $h, string $n): bool {
        return $n === '' || strpos($h, $n) !== false;
    }
}

// ── ULID ──────────────────────────────────────────────────────
function generate_ulid(): string {
    $t = (int)(microtime(true) * 1000);
    $b = '';
    for ($i = 9; $i >= 0; $i--) { $b = chr($t & 0xFF) . $b; $t >>= 8; }
    $b = substr($b, 4) . random_bytes(10);
    $a = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    $r = ''; $v = 0; $bits = 0;
    for ($i = 0; $i < strlen($b); $i++) {
        $v = ($v << 8) | ord($b[$i]); $bits += 8;
        while ($bits >= 5) { $bits -= 5; $r .= $a[($v >> $bits) & 0x1F]; }
    }
    return str_pad($r, 26, '0', STR_PAD_LEFT);
}

// ── String ────────────────────────────────────────────────────
function generate_token(int $len = 32): string { return bin2hex(random_bytes($len / 2)); }
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function truncate(string $t, int $max = 100): string {
    return mb_strlen($t) <= $max ? $t : mb_substr($t, 0, $max) . '…';
}

// ── Normalize ─────────────────────────────────────────────────
function normalize_phone(string $p): string {
    $p = preg_replace('/[\s\-\(\)\.]+/', '', $p);
    if (str_starts_with($p, '+62'))  $p = '0' . substr($p, 3);
    elseif (str_starts_with($p, '62') && strlen($p) > 9) $p = '0' . substr($p, 2);
    elseif (str_starts_with($p, '8') && strlen($p) >= 9) $p = '0' . $p;
    return $p;
}
function normalize_account(string $v): string { return preg_replace('/\D/', '', $v); }
function normalize_email(string $v): string   { return strtolower(trim($v)); }
function normalize_value(string $v, string $slug): string {
    switch ($slug) {
        case 'phone':                                       return normalize_phone($v);
        case 'bank_account': case 'dana': case 'ovo':
        case 'gopay': case 'shopeepay':  case 'linkaja':  return normalize_account($v);
        case 'email':                                      return normalize_email($v);
        default:                                           return strtolower(trim($v));
    }
}
// Alias untuk backward compat
function normalize_reported_value(string $v, string $slug): string { return normalize_value($v, $slug); }

function mask_value(string $v, int $start = 4, int $end = 2): string {
    $len = strlen($v);
    if ($len <= ($start + $end)) return $v;
    return substr($v, 0, $start) . str_repeat('*', $len - $start - $end) . substr($v, -$end);
}

// ── Risk ──────────────────────────────────────────────────────
function calculate_risk_score(int $approved, float $severity = 3.0, int $total = 0): float {
    if ($approved === 0) return 0.0;
    $countScore    = min(50, log($approved + 1, 1.3));
    $severityScore = (($severity - 1) / 3) * 50;
    return round(min(100, $countScore + $severityScore), 2);
}
function get_risk_level(float $score, int $approved = 0): string {
    if ($approved === 0) return 'unknown';
    if ($score <= 0)  return 'safe';
    if ($score <= 20) return 'low';
    if ($score <= 50) return 'medium';
    if ($score <= 75) return 'high';
    return 'critical';
}
function get_risk_badge(string $level): array {
    $map = [
        'safe'     => ['label' => 'Aman',           'color' => 'success',   'icon' => 'bi-shield-check'],
        'low'      => ['label' => 'Risiko Rendah',   'color' => 'info',      'icon' => 'bi-shield'],
        'medium'   => ['label' => 'Risiko Sedang',   'color' => 'warning',   'icon' => 'bi-shield-exclamation'],
        'high'     => ['label' => 'Risiko Tinggi',   'color' => 'danger',    'icon' => 'bi-shield-x'],
        'critical' => ['label' => 'BERBAHAYA',        'color' => 'dark',      'icon' => 'bi-shield-fill-x'],
    ];
    return $map[$level] ?? ['label' => 'Belum Diketahui', 'color' => 'secondary', 'icon' => 'bi-question-circle'];
}

// ── Validation ────────────────────────────────────────────────
function validate(array $data, array $rules): array {
    $errors = [];
    foreach ($rules as $field => $ruleStr) {
        $value = $data[$field] ?? null;
        $rs    = explode('|', $ruleStr);
        foreach ($rs as $rule) {
            $param = null;
            if (strpos($rule, ':') !== false) [$rule, $param] = explode(':', $rule, 2);
            switch ($rule) {
                case 'required':
                    if ($value === null || $value === '') {
                        $errors[$field] = ucfirst($field) . ' wajib diisi'; continue 3;
                    }
                    break;
                case 'min':
                    if (strlen((string)$value) < (int)$param)
                        $errors[$field] = ucfirst($field) . " minimal {$param} karakter";
                    break;
                case 'max':
                    if (strlen((string)$value) > (int)$param)
                        $errors[$field] = ucfirst($field) . " maksimal {$param} karakter";
                    break;
                case 'numeric':
                    if ($value !== null && $value !== '' && !is_numeric($value))
                        $errors[$field] = ucfirst($field) . ' harus angka';
                    break;
                case 'email':
                    if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL))
                        $errors[$field] = 'Format email tidak valid';
                    break;
                case 'in':
                    if ($value !== null && $value !== '' && !in_array($value, explode(',', $param), true))
                        $errors[$field] = ucfirst($field) . ' tidak valid';
                    break;
            }
            if (isset($errors[$field])) break;
        }
    }
    return $errors;
}

// ── Request ───────────────────────────────────────────────────
function get_json_body(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
function get_client_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

// ── Auth Header ───────────────────────────────────────────────
function get_auth_header(): string {
    // 1. Standard
    if (!empty($_SERVER['HTTP_AUTHORIZATION']))
        return $_SERVER['HTTP_AUTHORIZATION'];

    // 2. After mod_rewrite passthrough
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']))
        return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];

    // 3. PHP-CGI / some Apache configs
    if (!empty($_SERVER['HTTP_AUTH']))
        return $_SERVER['HTTP_AUTH'];

    // 4. getallheaders() - works in mod_php
    if (function_exists('getallheaders')) {
        $headers = getallheaders() ?: [];
        foreach ($headers as $k => $v) {
            if (strtolower($k) === 'authorization') return $v;
        }
    }

    // 5. apache_request_headers()
    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers() ?: [];
        foreach ($headers as $k => $v) {
            if (strtolower($k) === 'authorization') return $v;
        }
    }

    return '';
}

function get_bearer_token(): string {
    $h = get_auth_header();
    // Handle "Bearer TOKEN" or just "TOKEN"
    if (stripos($h, 'Bearer ') === 0) return trim(substr($h, 7));
    return '';
}

// ── Format ────────────────────────────────────────────────────
function format_rupiah(float $n): string { return 'Rp ' . number_format($n, 0, ',', '.'); }
function format_date(string $d, string $fmt = 'd M Y'): string {
    try { return (new DateTime($d))->format($fmt); } catch (Exception $e) { return $d; }
}
function time_ago(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)    return 'Baru saja';
    if ($diff < 3600)  return floor($diff/60)   . ' menit lalu';
    if ($diff < 86400) return floor($diff/3600)  . ' jam lalu';
    return floor($diff/86400) . ' hari lalu';
}

// ── Password & JWT ────────────────────────────────────────────
function hash_password(string $p): string { return password_hash($p, PASSWORD_BCRYPT, ['cost'=>10]); }
function verify_password(string $p, string $h): bool { return password_verify($p, $h); }

/** Base64URL encode (JWT-safe: no +, /, = characters) */
function b64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/** Base64URL decode */
function b64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

function generate_jwt(array $payload): string {
    $header  = b64url_encode(json_encode(['alg'=>'HS256','typ'=>'JWT']));
    $payload['iat'] = time();
    $payload['exp'] = time() + JWT_EXPIRE;
    $body    = b64url_encode(json_encode($payload));
    $sig     = b64url_encode(hash_hmac('sha256', "{$header}.{$body}", JWT_SECRET, true));
    return "{$header}.{$body}.{$sig}";
}

function verify_jwt(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$header, $body, $sig] = $parts;
    $expected = b64url_encode(hash_hmac('sha256', "{$header}.{$body}", JWT_SECRET, true));
    if (!hash_equals($expected, $sig)) return null;
    $payload = json_decode(b64url_decode($body), true);
    if (!is_array($payload) || ($payload['exp'] ?? 0) < time()) return null;
    return $payload;
}

// ── Rate Limit ────────────────────────────────────────────────
function check_rate_limit(string $id, int $limit = 60, int $window = 60): bool {
    $dir  = (defined('STORAGE_PATH') ? STORAGE_PATH : LOG_PATH) . '/cache';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $file = $dir . '/rl_' . md5($id) . '.json';
    $now  = time();
    $data = file_exists($file) ? (json_decode(file_get_contents($file), true) ?? []) : [];
    $data = array_values(array_filter($data, function($t) use ($now, $window) { return $t > $now - $window; }));
    if (count($data) >= $limit) return false;
    $data[] = $now;
    @file_put_contents($file, json_encode($data), LOCK_EX);
    return true;
}
