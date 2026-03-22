#!/usr/bin/env php
<?php
/**
 * artisan.php — CLI Maintenance Commands
 *
 * Penggunaan:
 *   php artisan.php <command> [options]
 *
 * Commands:
 *   modules:discover          Scan dan daftarkan modul baru
 *   modules:list              Tampilkan daftar modul
 *   analytics:cleanup         Bersihkan data analytics lama
 *   risk:recalculate          Hitung ulang semua skor risiko
 *   reports:stats             Tampilkan statistik laporan
 *   cache:clear               Hapus file cache rate-limit
 *   admin:create              Buat admin user baru
 *   admin:reset-password      Reset password admin
 */

define('ROOT_PATH', __DIR__);

// ─── Warna CLI ────────────────────────────────────────────────
function out(string $text, string $color = ''): void {
    $map = ['red'=>"\033[31m",'green'=>"\033[32m",'yellow'=>"\033[33m",
            'blue'=>"\033[34m",'cyan'=>"\033[36m",'bold'=>"\033[1m",''=>''];
    echo ($map[$color] ?? '') . $text . "\033[0m" . "\n";
}
function info(string $m)    { out("  ℹ  {$m}", 'cyan'); }
function ok(string $m)      { out("  ✓  {$m}", 'green'); }
function warn(string $m)    { out("  ⚠  {$m}", 'yellow'); }
function fail(string $m)    { out("  ✕  {$m}", 'red'); }
function row(string $k, string $v) { printf("  %-30s %s\n", $k, $v); }
function ask(string $p, string $d = ''): string {
    echo "\033[34m  ?  \033[0m{$p}" . ($d ? " [{$d}]" : '') . ': ';
    $i = trim(fgets(STDIN));
    return $i !== '' ? $i : $d;
}
function askSecret(string $p): string {
    echo "\033[34m  ?  \033[0m{$p}: ";
    system('stty -echo'); $i = trim(fgets(STDIN)); system('stty echo');
    echo "\n"; return $i;
}

// ─── Load bootstrap ───────────────────────────────────────────
try {
    require_once ROOT_PATH . '/bootstrap.php';
} catch (Throwable $e) {
    fail("Bootstrap gagal: " . $e->getMessage());
    fail("Pastikan file .env sudah dikonfigurasi dengan benar.");
    exit(1);
}

// ─── Parse command ────────────────────────────────────────────
$args    = array_slice($argv, 1);
$command = $args[0] ?? 'help';
$options = array_slice($args, 1);

out("\n" . str_repeat('─', 50), 'bold');
out(" cek.resource.my.id — Artisan CLI", 'bold');
out(str_repeat('─', 50) . "\n", 'bold');

// ═══════════════════════════════════════════════════════════════
switch ($command) {

// ─── HELP ─────────────────────────────────────────────────────
case 'help':
case '--help':
case '-h':
    out("Perintah yang tersedia:\n", 'bold');
    row("modules:discover",         "Scan dan daftarkan modul baru");
    row("modules:list",             "Tampilkan daftar modul");
    row("analytics:cleanup",        "Bersihkan data analytics lama");
    row("risk:recalculate",         "Hitung ulang semua skor risiko");
    row("reports:stats",            "Tampilkan statistik laporan");
    row("cache:clear",              "Hapus file cache rate-limit");
    row("admin:create",             "Buat admin user baru");
    row("admin:reset-password",     "Reset password admin");
    echo "\n";
    break;

// ─── MODULES:DISCOVER ─────────────────────────────────────────
case 'modules:discover':
    info("Scanning modules...");
    $manager = ModuleManager::getInstance();
    $manager->discoverModules();
    ok("Selesai. Gunakan 'modules:list' untuk melihat hasilnya.");
    break;

// ─── MODULES:LIST ─────────────────────────────────────────────
case 'modules:list':
    $db   = Database::getInstance();
    $mods = $db->fetchAll("SELECT slug, name, version, is_enabled, is_core FROM modules ORDER BY slug");

    if (empty($mods)) {
        warn("Tidak ada modul terdaftar. Jalankan 'modules:discover' terlebih dahulu.");
        break;
    }

    out(sprintf("  %-20s %-20s %-10s %-8s %-6s", "SLUG", "NAMA", "VERSI", "STATUS", "CORE"), 'bold');
    out("  " . str_repeat('─', 68));
    foreach ($mods as $mod) {
        $status = $mod['is_enabled'] ? "\033[32maktif\033[0m" : "\033[31mnonaktif\033[0m";
        $core   = $mod['is_core'] ? 'Ya' : '-';
        printf("  %-20s %-20s %-10s %-8s %-6s\n",
            $mod['slug'], $mod['name'], $mod['version'], $status, $core);
    }
    echo "\n";
    break;

// ─── ANALYTICS:CLEANUP ────────────────────────────────────────
case 'analytics:cleanup':
    $days = (int) ($options[0] ?? 90);
    info("Membersihkan data analytics lebih dari {$days} hari...");

    $db     = Database::getInstance();
    $cutoff = date('Y-m-d', strtotime("-{$days} days"));

    $deleted = [];

    $r = $db->query("DELETE FROM analytics_page_views WHERE DATE(created_at) < ?", [$cutoff]);
    $deleted['page_views'] = 0; // rowCount tidak selalu tersedia

    $db->query("DELETE FROM analytics_search_daily WHERE date < ?", [$cutoff]);
    $db->query("DELETE FROM analytics_reports_daily WHERE date < ?", [$cutoff]);
    $db->query("DELETE FROM search_logs WHERE DATE(created_at) < ?", [$cutoff]);

    ok("Cleanup selesai. Data sebelum {$cutoff} telah dihapus.");
    break;

// ─── RISK:RECALCULATE ─────────────────────────────────────────
case 'risk:recalculate':
    info("Menghitung ulang semua skor risiko...");

    $db      = Database::getInstance();
    $service = new ReportService();

    // Ambil semua nilai unik yang ada laporannya
    $values = $db->fetchAll(
        "SELECT DISTINCT reported_value_normalized, category_id FROM reports WHERE status = 'approved'"
    );

    if (empty($values)) {
        warn("Tidak ada laporan approved ditemukan.");
        break;
    }

    $count = 0;
    foreach ($values as $row) {
        try {
            $service->updateRiskScore($row['reported_value_normalized'], $row['category_id']);
            $count++;
        } catch (Throwable $e) {
            fail("Gagal update: {$row['reported_value_normalized']} — " . $e->getMessage());
        }
    }

    ok("Selesai. {$count} risk score berhasil dihitung ulang.");
    break;

// ─── REPORTS:STATS ────────────────────────────────────────────
case 'reports:stats':
    $db = Database::getInstance();

    out("Statistik Laporan\n", 'bold');
    row("Total laporan",    (string)$db->fetchColumn("SELECT COUNT(*) FROM reports"));
    row("  → pending",      (string)$db->fetchColumn("SELECT COUNT(*) FROM reports WHERE status='pending'"));
    row("  → approved",     (string)$db->fetchColumn("SELECT COUNT(*) FROM reports WHERE status='approved'"));
    row("  → rejected",     (string)$db->fetchColumn("SELECT COUNT(*) FROM reports WHERE status='rejected'"));
    row("  → flagged",      (string)$db->fetchColumn("SELECT COUNT(*) FROM reports WHERE status='flagged'"));
    echo "\n";
    row("Risk scores tersimpan", (string)$db->fetchColumn("SELECT COUNT(*) FROM risk_scores"));
    row("  → high/critical",     (string)$db->fetchColumn("SELECT COUNT(*) FROM risk_scores WHERE risk_level IN ('high','critical')"));
    echo "\n";
    row("Total pencarian",   (string)$db->fetchColumn("SELECT COUNT(*) FROM search_logs"));
    row("Pencarian hari ini",(string)$db->fetchColumn("SELECT COUNT(*) FROM search_logs WHERE DATE(created_at) = CURDATE()"));
    echo "\n";

    out("Top Kategori (approved):", 'bold');
    $cats = $db->fetchAll(
        "SELECT c.name, COUNT(*) as cnt FROM reports r
         JOIN categories c ON c.id = r.category_id
         WHERE r.status = 'approved'
         GROUP BY c.id ORDER BY cnt DESC LIMIT 5"
    );
    foreach ($cats as $cat) {
        row("  " . $cat['name'], (string)$cat['cnt'] . " laporan");
    }
    echo "\n";
    break;

// ─── CACHE:CLEAR ──────────────────────────────────────────────
case 'cache:clear':
    $cacheDir = ROOT_PATH . '/storage/cache';
    if (!is_dir($cacheDir)) {
        warn("Direktori cache tidak ditemukan: {$cacheDir}");
        break;
    }

    $files   = glob($cacheDir . '/*.json');
    $deleted = 0;
    foreach ($files as $file) {
        if (unlink($file)) $deleted++;
    }

    ok("Berhasil menghapus {$deleted} file cache.");
    break;

// ─── ADMIN:CREATE ─────────────────────────────────────────────
case 'admin:create':
    out("Buat Admin User Baru\n", 'bold');
    $name  = ask("Nama lengkap");
    $email = ask("Email");
    $role  = ask("Role (admin/moderator)", "moderator");
    $pass  = askSecret("Password (minimal 8 karakter)");

    if (!$name || !$email || !$pass) {
        fail("Semua field wajib diisi.");
        break;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fail("Format email tidak valid.");
        break;
    }
    if (strlen($pass) < 8) {
        fail("Password minimal 8 karakter.");
        break;
    }
    if (!in_array($role, ['admin', 'moderator', 'superadmin'])) {
        fail("Role tidak valid. Gunakan: admin, moderator, superadmin");
        break;
    }

    $db     = Database::getInstance();
    $exists = $db->fetchColumn("SELECT id FROM admins WHERE email = ?", [$email]);
    if ($exists) {
        fail("Email sudah digunakan.");
        break;
    }

    $id = $db->insert('admins', [
        'name'       => $name,
        'email'      => strtolower($email),
        'password'   => hash_password($pass),
        'role'       => $role,
        'is_active'  => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    ok("Admin berhasil dibuat (ID: {$id})");
    row("  Email", $email);
    row("  Role",  $role);
    echo "\n";
    break;

// ─── ADMIN:RESET-PASSWORD ─────────────────────────────────────
case 'admin:reset-password':
    out("Reset Password Admin\n", 'bold');

    $db    = Database::getInstance();
    $email = ask("Email admin");
    $admin = $db->fetchOne("SELECT id, name, role FROM admins WHERE email = ?", [strtolower($email)]);

    if (!$admin) {
        fail("Admin dengan email '{$email}' tidak ditemukan.");
        break;
    }

    info("Ditemukan: {$admin['name']} ({$admin['role']})");
    $pass    = askSecret("Password baru (minimal 8 karakter)");
    $confirm = askSecret("Konfirmasi password");

    if ($pass !== $confirm) {
        fail("Password tidak cocok.");
        break;
    }
    if (strlen($pass) < 8) {
        fail("Password minimal 8 karakter.");
        break;
    }

    $db->update('admins', [
        'password'   => hash_password($pass),
        'updated_at' => date('Y-m-d H:i:s'),
    ], 'id = ?', [$admin['id']]);

    ok("Password berhasil direset untuk: {$email}");
    break;

// ─── UNKNOWN ──────────────────────────────────────────────────
default:
    fail("Perintah tidak dikenali: '{$command}'");
    info("Jalankan 'php artisan.php help' untuk daftar perintah.");
    exit(1);
}

echo "\n";
