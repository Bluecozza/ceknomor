#!/usr/bin/env php
<?php
/**
 * setup.php — Script inisialisasi awal cek.resource.my.id
 *
 * Jalankan dari Command Prompt / Terminal:
 *   php setup.php
 *
 * JANGAN diakses via browser.
 */

define('SETUP_ROOT', __DIR__);
define('SETUP_VERSION', '1.0.0');

// ─── Pastikan dijalankan dari CLI, bukan browser ──────────────
if (php_sapi_name() !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    echo '<!DOCTYPE html><html><body>';
    echo '<h2>403 Forbidden</h2>';
    echo '<p>setup.php hanya boleh dijalankan dari command line:</p>';
    echo '<pre>php setup.php</pre>';
    echo '</body></html>';
    exit(1);
}

// ─── Definisikan STDIN jika belum ada (safety net) ────────────
if (!defined('STDIN')) {
    define('STDIN', fopen('php://stdin', 'r'));
}
if (!defined('STDOUT')) {
    define('STDOUT', fopen('php://stdout', 'w'));
}
if (!defined('STDERR')) {
    define('STDERR', fopen('php://stderr', 'w'));
}

// ─── Warna CLI ────────────────────────────────────────────────
function c(string $text, string $color = ''): string
{
    // Nonaktifkan warna di Windows jika tidak mendukung ANSI
    $isWindows   = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    $supportsColor = !$isWindows || getenv('ANSICON') || getenv('ConEmuANSI') === 'ON' || getenv('TERM');
    if (!$supportsColor) return $text;

    $colors = [
        'red'    => "\033[31m",
        'green'  => "\033[32m",
        'yellow' => "\033[33m",
        'blue'   => "\033[34m",
        'cyan'   => "\033[36m",
        'bold'   => "\033[1m",
        ''       => "\033[0m",
    ];
    return ($colors[$color] ?? '') . $text . "\033[0m";
}

function info(string $msg)    { echo c('  i  ', 'cyan')   . $msg . "\n"; }
function ok(string $msg)      { echo c('  v  ', 'green')  . $msg . "\n"; }
function warn(string $msg)    { echo c('  !  ', 'yellow') . $msg . "\n"; }
function fail(string $msg)    { echo c('  x  ', 'red')    . $msg . "\n"; }
function title(string $msg)   { echo "\n" . c("=== {$msg} ", 'bold') . "\n"; }

function ask(string $prompt, string $default = ''): string
{
    $hint  = $default !== '' ? " [{$default}]" : '';
    echo c('  ?  ', 'blue') . $prompt . $hint . ': ';
    $input = trim(fgets(STDIN));
    return $input !== '' ? $input : $default;
}

// ─── HEADER ───────────────────────────────────────────────────
echo "\n+-----------------------------------------+\n";
echo "|   cek.resource.my.id -- Setup v" . SETUP_VERSION . "      |\n";
echo "+-----------------------------------------+\n\n";

// ═══════════════════════════════════════════
// STEP 1 — Cek requirement
// ═══════════════════════════════════════════
title("STEP 1: Checking Requirements");

$allOk = true;

if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    ok("PHP " . PHP_VERSION);
} else {
    fail("PHP 7.4+ dibutuhkan, Anda menggunakan " . PHP_VERSION);
    $allOk = false;
}

foreach (['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl'] as $ext) {
    if (extension_loaded($ext)) {
        ok("Extension {$ext}");
    } else {
        fail("Extension {$ext} tidak tersedia");
        $allOk = false;
    }
}

if (!$allOk) {
    fail("Setup gagal: requirement tidak terpenuhi.");
    exit(1);
}

// ═══════════════════════════════════════════
// STEP 2 — Buat .env
// ═══════════════════════════════════════════
title("STEP 2: Environment Configuration");

$envPath = SETUP_ROOT . DIRECTORY_SEPARATOR . '.env';
$skipEnv = false;

if (file_exists($envPath)) {
    $overwrite = ask(".env sudah ada. Timpa? (y/N)", "N");
    if (strtolower($overwrite) !== 'y') {
        info("Melewati pembuatan .env");
        $skipEnv = true;
    }
}

if (!$skipEnv) {
    info("Masukkan konfigurasi database (tekan Enter untuk pakai nilai default):");
    $dbHost = ask("DB_HOST", "localhost");
    $dbPort = ask("DB_PORT", "3306");
    $dbName = ask("DB_NAME", "cek_resource");
    $dbUser = ask("DB_USER", "root");
    $dbPass = ask("DB_PASS", "");

    $appKey    = bin2hex(random_bytes(32));
    $jwtSecret = bin2hex(random_bytes(32));
    $genDate   = date('Y-m-d H:i:s');

    $envLines = [
        "# ============================================================",
        "# cek.resource.my.id -- Environment Configuration",
        "# Generated: {$genDate}",
        "# ============================================================",
        "",
        "APP_ENV=development",
        "",
        "DB_HOST={$dbHost}",
        "DB_PORT={$dbPort}",
        "DB_NAME={$dbName}",
        "DB_USER={$dbUser}",
        "DB_PASS={$dbPass}",
        "",
        "APP_KEY={$appKey}",
        "JWT_SECRET={$jwtSecret}",
        "",
        "UPLOAD_PATH=public/uploads",
        "UPLOAD_MAX_SIZE=5242880",
    ];

    $written = file_put_contents($envPath, implode("\n", $envLines) . "\n");
    if ($written === false) {
        fail("Gagal menulis .env — pastikan direktori bisa ditulis.");
    } else {
        ok(".env berhasil dibuat");
        ok("APP_KEY   : " . substr($appKey, 0, 16) . "...");
        ok("JWT_SECRET: " . substr($jwtSecret, 0, 16) . "...");
    }
}

// ═══════════════════════════════════════════
// STEP 3 — Import database
// ═══════════════════════════════════════════
title("STEP 3: Database Setup");

// Baca ulang .env
$envVars = [];
if (file_exists($envPath)) {
    foreach (file($envPath) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        $parts             = explode('=', $line, 2);
        $envVars[trim($parts[0])] = isset($parts[1]) ? trim($parts[1]) : '';
    }
}

$dbHost = $envVars['DB_HOST'] ?? 'localhost';
$dbPort = $envVars['DB_PORT'] ?? '3306';
$dbName = $envVars['DB_NAME'] ?? 'cek_resource';
$dbUser = $envVars['DB_USER'] ?? 'root';
$dbPass = $envVars['DB_PASS'] ?? '';

info("Mencoba koneksi ke MySQL ({$dbHost}:{$dbPort} / user: {$dbUser})...");

$dbOk = false;
$pdo  = null;

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    ok("Koneksi MySQL berhasil");
    $dbOk = true;
} catch (PDOException $e) {
    fail("Koneksi database gagal: " . $e->getMessage());
    warn("Pastikan MySQL/MariaDB sudah berjalan.");
    warn("Anda bisa import database.sql secara manual via phpMyAdmin.");
}

if ($dbOk && $pdo !== null) {
    $checkDb  = $pdo->query("SHOW DATABASES LIKE '{$dbName}'")->rowCount() > 0;
    $doImport = true;

    if ($checkDb) {
        $reimport = ask("Database '{$dbName}' sudah ada. Import ulang schema? (y/N)", "N");
        if (strtolower($reimport) !== 'y') {
            info("Melewati import database.");
            $doImport = false;
        }
    }

    if ($doImport) {
        $sqlFile = SETUP_ROOT . DIRECTORY_SEPARATOR . 'database.sql';
        if (!file_exists($sqlFile)) {
            fail("File database.sql tidak ditemukan di: {$sqlFile}");
        } else {
            info("Mengimport database.sql...");
            try {
                $sql = file_get_contents($sqlFile);
                // PDO::exec tidak bisa multi-statement, gunakan loop
                // Hapus komentar dan split per statement
                $sql = preg_replace('/--[^\n]*\n/', "\n", $sql);
                $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
                $statements = array_filter(
                    array_map('trim', explode(';', $sql)),
                    function ($s) { return $s !== ''; }
                );
                foreach ($statements as $stmt) {
                    try {
                        $pdo->exec($stmt);
                    } catch (PDOException $e) {
                        // Abaikan error "table already exists" dll
                        if (strpos($e->getMessage(), 'already exists') === false) {
                            warn("Peringatan query: " . substr($e->getMessage(), 0, 80));
                        }
                    }
                }
                ok("Database '{$dbName}' berhasil diimport");
            } catch (Exception $e) {
                fail("Import gagal: " . $e->getMessage());
                warn("Coba import database.sql secara manual via phpMyAdmin.");
            }
        }
    }
}

// ═══════════════════════════════════════════
// STEP 4 — Buat direktori
// ═══════════════════════════════════════════
title("STEP 4: Creating Directories");

$sep   = DIRECTORY_SEPARATOR;
$year  = date('Y');
$month = date('m');

$dirs = [
    "public{$sep}uploads",
    "public{$sep}uploads{$sep}{$year}",
    "public{$sep}uploads{$sep}{$year}{$sep}{$month}",
    "public{$sep}assets{$sep}css",
    "public{$sep}assets{$sep}js",
    "public{$sep}assets{$sep}img",
    "logs",
    "storage{$sep}cache",
    "storage{$sep}sessions",
];

foreach ($dirs as $dir) {
    $path = SETUP_ROOT . $sep . $dir;
    if (!is_dir($path)) {
        if (mkdir($path, 0755, true)) {
            ok("Dibuat: {$dir}");
        } else {
            fail("Gagal membuat: {$dir}");
        }
    } else {
        info("Sudah ada: {$dir}");
    }
}

// Security .htaccess di uploads
$uploadsHtaccess = SETUP_ROOT . "{$sep}public{$sep}uploads{$sep}.htaccess";
if (!file_exists($uploadsHtaccess)) {
    $htContent  = "Options -Indexes\n";
    $htContent .= "<FilesMatch \"\\.(php|php5|phtml|asp|aspx|cgi|pl)\$\">\n";
    $htContent .= "    Deny from all\n";
    $htContent .= "</FilesMatch>\n";
    if (file_put_contents($uploadsHtaccess, $htContent)) {
        ok("Security .htaccess dibuat di public/uploads/");
    }
}

// ═══════════════════════════════════════════
// STEP 5 — Selesai
// ═══════════════════════════════════════════
title("STEP 5: Done!");

echo "\n";
echo "+-----------------------------------------------------+\n";
echo "|                                                     |\n";
echo "|   Setup selesai! Langkah selanjutnya:               |\n";
echo "|                                                     |\n";
echo "|   1. Buka http://localhost/cek-resource/            |\n";
echo "|   2. Login admin: admin@cek.resource.my.id          |\n";
echo "|      Password  : Admin@1234                         |\n";
echo "|   3. GANTI PASSWORD default segera!                 |\n";
echo "|   4. Konfigurasi modul di /admin -> Modul           |\n";
echo "|                                                     |\n";
echo "+-----------------------------------------------------+\n\n";

