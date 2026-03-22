<?php
/**
 * core/helpers.php
 * ---------------------------------------------------------------
 * Fungsi-fungsi utilitas global
 * Kompatibel dengan PHP 7.4+
 * ---------------------------------------------------------------
 */

// ── PHP 7.4 Polyfills ──────────────────────────────────────────
// str_starts_with, str_ends_with, str_contains tersedia sejak PHP 8.0
// Polyfill ini memastikan kompatibilitas dengan PHP 7.4

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

// ── String Helpers ─────────────────────────────────────────────

/**
 * Generate ULID (Universally Unique Lexicographically Sortable Identifier)
 * Lebih readable dari UUID, bisa dipakai di URL
 */
function generate_ulid(): string
{
    $time      = (int)(microtime(true) * 1000);
    $timeBytes = '';
    for ($i = 9; $i >= 0; $i--) {
        $timeBytes = chr($time & 0xFF) . $timeBytes;
        $time >>= 8;
    }
    $timeBytes = substr($timeBytes, 4);

    $randomBytes = random_bytes(10);
    $bytes       = $timeBytes . $randomBytes;

    $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    $result   = '';
    $value    = 0;
    $bits     = 0;

    for ($i = 0; $i < strlen($bytes); $i++) {
        $value = ($value << 8) | ord($bytes[$i]);
        $bits += 8;
        while ($bits >= 5) {
            $bits -= 5;
            $result .= $alphabet[($value >> $bits) & 0x1F];
        }
    }

    return str_pad($result, 26, '0', STR_PAD_LEFT);
}

/**
 * Generate random token (untuk verifikasi, API key, dll)
 */
function generate_token(int $length = 32): string
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Sanitize string untuk output HTML (XSS prevention)
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Truncate string dengan ellipsis
 */
function truncate(string $text, int $length = 100, string $suffix = '...'): string
{
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length - mb_strlen($suffix)) . $suffix;
}

/**
 * Slug dari string
 */
function slugify(string $text): string
{
    $text = mb_strtolower($text);
    $text = preg_replace('/[^\w\s-]/', '', $text);
    $text = preg_replace('/[\s_-]+/', '-', $text);
    return trim($text, '-');
}

// ── Data Normalization ─────────────────────────────────────────

/**
 * Normalisasi nomor telepon Indonesia
 * 08xx, +628xx, 628xx → 08xx
 */
function normalize_phone(string $phone): string
{
    // Hapus semua karakter non-digit
    $phone = preg_replace('/\D/', '', $phone);

    if (str_starts_with($phone, '628')) {
        $phone = '0' . substr($phone, 2);
    } elseif (str_starts_with($phone, '8') && strlen($phone) >= 9) {
        $phone = '0' . $phone;
    }

    return $phone;
}

/**
 * Normalisasi nomor rekening (hapus spasi, strip, dll)
 */
function normalize_account(string $account): string
{
    return preg_replace('/[\s\-.]/', '', $account);
}

/**
 * Normalisasi email (lowercase, trim)
 */
function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

/**
 * Normalisasi nilai yang dilaporkan berdasarkan kategori
 *
 * @param string $value      Nilai asli
 * @param string $categorySlug Slug kategori
 */
function normalize_reported_value(string $value, string $categorySlug): string
{
    $value = trim($value);

    switch ($categorySlug) {
        case 'phone':
            return normalize_phone($value);
        case 'bank_account':
        case 'dana':
        case 'ovo':
        case 'gopay':
        case 'shopeepay':
        case 'linkaja':
            return normalize_account($value);
        case 'email':
            return normalize_email($value);
        default:
            return strtolower($value);
    }
}

/**
 * Mask data sensitif untuk tampilan (hanya tampilkan sebagian)
 * Contoh: 081234567890 → 0812****7890
 */
function mask_value(string $value, int $visibleStart = 4, int $visibleEnd = 4): string
{
    $length = strlen($value);
    if ($length <= ($visibleStart + $visibleEnd)) {
        return $value;
    }
    $masked = substr($value, 0, $visibleStart)
        . str_repeat('*', $length - $visibleStart - $visibleEnd)
        . substr($value, -$visibleEnd);
    return $masked;
}

// ── Risk Calculation ───────────────────────────────────────────

/**
 * Hitung skor risiko berdasarkan jumlah laporan dan severity
 *
 * @param int   $approvedReports  Laporan yang disetujui
 * @param float $avgSeverity      Rata-rata severity laporan (1-4)
 * @param int   $totalReports     Total semua laporan
 * @return float Skor 0-100
 */
function calculate_risk_score(int $approvedReports, float $avgSeverity = 3.0, int $totalReports = 0): float
{
    if ($approvedReports === 0) return 0;

    // Bobot berdasarkan jumlah laporan (logaritmik, maks ~50)
    $countScore = min(50, log($approvedReports + 1, 1.3));

    // Bobot severity (1-4 dikonversi ke 0-50)
    $severityScore = (($avgSeverity - 1) / 3) * 50;

    $score = $countScore + $severityScore;
    return round(min(100, $score), 2);
}

/**
 * Tentukan level risiko dari skor
 *
 * @param float $score Skor 0-100
 * @return string Level: unknown|safe|low|medium|high|critical
 */
function get_risk_level(float $score, int $approvedReports = 0): string
{
    if ($approvedReports === 0) return 'unknown';
    if ($score <= 0)   return 'safe';
    if ($score <= 20)  return 'low';
    if ($score <= 50)  return 'medium';
    if ($score <= 75)  return 'high';
    return 'critical';
}

/**
 * Dapatkan label dan warna Bootstrap untuk risk level
 */
function get_risk_badge(string $level): array
{
    $badges = [
        'safe'     => ['label' => 'Aman',           'color' => 'success',   'icon' => 'bi-shield-check'],
        'low'      => ['label' => 'Risiko Rendah',   'color' => 'info',      'icon' => 'bi-shield'],
        'medium'   => ['label' => 'Risiko Sedang',   'color' => 'warning',   'icon' => 'bi-shield-exclamation'],
        'high'     => ['label' => 'Risiko Tinggi',   'color' => 'danger',    'icon' => 'bi-shield-x'],
        'critical' => ['label' => 'BERBAHAYA',        'color' => 'dark',      'icon' => 'bi-shield-fill-x'],
    ];
    return $badges[$level] ?? ['label' => 'Belum Diketahui', 'color' => 'secondary', 'icon' => 'bi-question-circle'];
}

// ── Validation ─────────────────────────────────────────────────

/**
 * Validasi request body dan kembalikan error jika ada
 *
 * @param array $data    Data yang divalidasi
 * @param array $rules   Rules validasi ['field' => 'required|min:3|max:100|email']
 * @return array Array error, kosong jika valid
 */
function validate(array $data, array $rules): array
{
    $errors = [];

    foreach ($rules as $field => $ruleString) {
        $value     = $data[$field] ?? null;
        $ruleList  = explode('|', $ruleString);

        foreach ($ruleList as $rule) {
            // Rule dengan parameter (e.g., min:3)
            if (str_contains($rule, ':')) {
                [$ruleName, $ruleParam] = explode(':', $rule, 2);
            } else {
                $ruleName  = $rule;
                $ruleParam = null;
            }

            $error = null;

            switch ($ruleName) {
                case 'required':
                    if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                        $error = "Field {$field} wajib diisi";
                    }
                    break;

                case 'min':
                    if ($value !== null && mb_strlen((string)$value) < (int)$ruleParam) {
                        $error = "Field {$field} minimal {$ruleParam} karakter";
                    }
                    break;

                case 'max':
                    if ($value !== null && mb_strlen((string)$value) > (int)$ruleParam) {
                        $error = "Field {$field} maksimal {$ruleParam} karakter";
                    }
                    break;

                case 'email':
                    if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $error = "Format email tidak valid";
                    }
                    break;

                case 'numeric':
                    if ($value !== null && $value !== '' && !is_numeric($value)) {
                        $error = "Field {$field} harus berupa angka";
                    }
                    break;

                case 'in':
                    $allowed = explode(',', $ruleParam);
                    if ($value !== null && $value !== '' && !in_array($value, $allowed)) {
                        $error = "Field {$field} tidak valid";
                    }
                    break;

                case 'regex':
                    if ($value !== null && $value !== '' && !preg_match($ruleParam, $value)) {
                        $error = "Format {$field} tidak valid";
                    }
                    break;
            }

            if ($error) {
                $errors[$field] = $error;
                break; // Stop validasi field ini setelah error pertama
            }
        }
    }

    return $errors;
}

// ── HTTP Helpers ───────────────────────────────────────────────

/**
 * Ambil body request JSON (untuk API)
 */
function get_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Dapatkan IP address client
 */
function get_client_ip(): string
{
    $candidates = [
        'HTTP_CF_CONNECTING_IP',   // Cloudflare
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'REMOTE_ADDR',
    ];

    foreach ($candidates as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return '0.0.0.0';
}

/**
 * Cek apakah request berasal dari AJAX
 */
function is_ajax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

// ── Format Helpers ─────────────────────────────────────────────

/**
 * Format rupiah
 */
function format_rupiah(float $amount): string
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

/**
 * Format tanggal ke bahasa Indonesia
 */
function format_date(string $date, string $format = 'd M Y'): string
{
    $bulan = [
        1=>'Jan', 2=>'Feb', 3=>'Mar', 4=>'Apr', 5=>'Mei', 6=>'Jun',
        7=>'Jul', 8=>'Agt', 9=>'Sep', 10=>'Okt', 11=>'Nov', 12=>'Des'
    ];
    $timestamp = strtotime($date);
    $result    = date($format, $timestamp);
    // Ganti nama bulan English → Indonesia
    foreach ($bulan as $num => $id) {
        $en = date('M', mktime(0, 0, 0, $num, 1));
        $result = str_replace($en, $id, $result);
    }
    return $result;
}

/**
 * Relative time (e.g., "2 jam lalu")
 */
function time_ago(string $datetime): string
{
    $time  = time() - strtotime($datetime);
    $units = [
        31536000 => 'tahun',
        2592000  => 'bulan',
        604800   => 'minggu',
        86400    => 'hari',
        3600     => 'jam',
        60       => 'menit',
        1        => 'detik',
    ];

    foreach ($units as $seconds => $unit) {
        if ($time >= $seconds) {
            $count = floor($time / $seconds);
            return "{$count} {$unit} lalu";
        }
    }

    return 'baru saja';
}

// ── Security ───────────────────────────────────────────────────

/**
 * Hash password
 */
function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verifikasi password
 */
function verify_password(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

/**
 * Generate JWT token sederhana untuk admin
 */
function generate_jwt(array $payload): string
{
    $header    = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload['iat'] = time();
    $payload['exp'] = time() + JWT_EXPIRE;
    $payloadB64 = base64_encode(json_encode($payload));
    $signature  = hash_hmac('sha256', "{$header}.{$payloadB64}", JWT_SECRET, true);
    $sigB64     = base64_encode($signature);
    return "{$header}.{$payloadB64}.{$sigB64}";
}

/**
 * Verifikasi dan decode JWT token
 *
 * @return array|null Payload atau null jika tidak valid
 */
function verify_jwt(string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;

    [$header, $payloadB64, $sigB64] = $parts;
    $expectedSig = base64_encode(
        hash_hmac('sha256', "{$header}.{$payloadB64}", JWT_SECRET, true)
    );

    if (!hash_equals($expectedSig, $sigB64)) return null;

    $payload = json_decode(base64_decode($payloadB64), true);
    if (!$payload || $payload['exp'] < time()) return null;

    return $payload;
}

/**
 * Rate limiting sederhana menggunakan file/session
 * Untuk production, gunakan Redis
 */
function check_rate_limit(string $identifier, int $limit = 60, int $window = 60): bool
{
    $key  = 'rl_' . md5($identifier);
    $file = LOG_PATH . '/ratelimit/' . $key . '.json';

    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0755, true);
    }

    $now  = time();
    $data = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    $data = array_filter($data ?? [], function($t) use ($now, $window) { return $t > $now - $window; });

    if (count($data) >= $limit) return false;

    $data[] = $now;
    file_put_contents($file, json_encode(array_values($data)), LOCK_EX);
    return true;
}

// ── Auth Header Helpers ────────────────────────────────────────

/**
 * Ambil nilai Authorization header dari berbagai sumber.
 *
 * Apache sering memstrip header Authorization sebelum sampai ke PHP.
 * Fungsi ini mencoba berbagai cara untuk mendapatkan header tersebut:
 *  1. $_SERVER['HTTP_AUTHORIZATION']          — standar, kadang tidak ada
 *  2. $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] — setelah mod_rewrite redirect
 *  3. apache_request_headers() / getallheaders() — fungsi Apache khusus
 *  4. $_SERVER['PHP_AUTH_USER']               — Basic auth fallback
 *
 * @return string Nilai Authorization header, atau string kosong jika tidak ada
 */
function get_auth_header(): string
{
    // 1. Cara standar (bekerja di Nginx, PHP-FPM, dan beberapa Apache)
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return $_SERVER['HTTP_AUTHORIZATION'];
    }

    // 2. Setelah RewriteRule pass di .htaccess
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    // 3. Via RewriteRule E=HTTP_AUTHORIZATION di .htaccess
    if (!empty($_SERVER['REDIRECT_HTTP_AUTH_USER'])) {
        return 'Bearer ' . $_SERVER['REDIRECT_HTTP_AUTH_USER'];
    }

    // 4. apache_request_headers() — tersedia di mod_php
    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        // Header tidak case-sensitive
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'authorization') {
                return $value;
            }
        }
    }

    // 5. getallheaders() — tersedia di beberapa environment
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'authorization') {
                return $value;
            }
        }
    }

    return '';
}

/**
 * Ekstrak Bearer token dari Authorization header.
 * Mengembalikan string kosong jika tidak ada atau bukan Bearer token.
 *
 * @return string Token JWT, atau string kosong
 */
function get_bearer_token(): string
{
    $header = get_auth_header();
    if (str_starts_with($header, 'Bearer ')) {
        return trim(substr($header, 7));
    }
    return '';
}

