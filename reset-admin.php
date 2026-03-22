<!DOCTYPE html>
<?php
/**
 * reset-admin.php
 * ---------------------------------------------------------------
 * Script untuk reset password admin atau membuat akun admin baru.
 * HAPUS file ini setelah digunakan!
 *
 * Akses via browser: http://localhost/cek-resource/reset-admin.php
 * Atau via CLI:      php reset-admin.php
 * ---------------------------------------------------------------
 */

define('ROOT_PATH', __DIR__);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/Database.php';

$isCli   = (php_sapi_name() === 'cli');
$message = '';
$msgType = '';
$done    = false;

// ── Deteksi & perbaiki kolom yang kurang di tabel admins ─────
function getAdminColumns(PDO $pdo, string $dbName): array
{
    $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = '{$dbName}' AND TABLE_NAME = 'admins'")->fetchAll(PDO::FETCH_COLUMN);
    return $cols;
}

function ensureAdminColumns(PDO $pdo, string $dbName): array
{
    $existing = getAdminColumns($pdo, $dbName);
    $fixes    = [];

    $needed = [
        'updated_at'   => "ALTER TABLE `admins` ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        'last_login_at'=> "ALTER TABLE `admins` ADD COLUMN `last_login_at` TIMESTAMP NULL",
        'last_login_ip'=> "ALTER TABLE `admins` ADD COLUMN `last_login_ip` VARCHAR(45) DEFAULT NULL",
    ];

    foreach ($needed as $col => $sql) {
        if (!in_array($col, $existing)) {
            try {
                $pdo->exec($sql);
                $fixes[] = "Kolom `{$col}` ditambahkan otomatis";
            } catch (PDOException $e) {
                $fixes[] = "Gagal tambah `{$col}`: " . $e->getMessage();
            }
        }
    }

    return $fixes;
}

// ── Koneksi database ─────────────────────────────────────────
$db        = null;
$pdo       = null;
$dbName    = defined('DB_NAME') ? DB_NAME : 'cek_resource';
$dbError   = null;
$autoFixes = [];
$adminList = [];
$adminCount = 0;
$tableExists = false;

try {
    $db  = Database::getInstance();
    $ref = new ReflectionClass($db);
    $prop = $ref->getProperty('pdo');
    $prop->setAccessible(true);
    $pdo = $prop->getValue($db);

    $tableExists = $db->tableExists('admins');
    if ($tableExists) {
        // Auto-fix kolom yang kurang
        $autoFixes  = ensureAdminColumns($pdo, $dbName);
        $adminCount = (int)$db->fetchColumn("SELECT COUNT(*) FROM admins");
        $adminList  = $db->fetchAll("SELECT id, name, email, role, is_active FROM admins ORDER BY id");
    }
} catch (Exception $e) {
    $dbError = $e->getMessage();
}

// ── Helper: build insert data tanpa kolom yang tidak ada ─────
function buildAdminData(string $name, string $email, string $hash, bool $isNew = true): array
{
    $data = [
        'name'      => $name,
        'email'     => strtolower($email),
        'password'  => $hash,
        'is_active' => 1,
    ];
    if ($isNew) {
        $data['role']       = 'superadmin';
        $data['created_at'] = date('Y-m-d H:i:s');
    }
    $data['updated_at'] = date('Y-m-d H:i:s');
    return $data;
}

// ── Mode CLI ─────────────────────────────────────────────────
if ($isCli) {
    if (!defined('STDIN')) define('STDIN', fopen('php://stdin', 'r'));
    echo "\n=== cek.resource.my.id -- Admin Password Reset ===\n\n";

    if (!empty($autoFixes)) {
        foreach ($autoFixes as $fix) echo "  [fix] {$fix}\n";
        echo "\n";
    }

    echo "1. Reset password admin yang sudah ada\n";
    echo "2. Buat admin baru\n";
    echo "Pilihan [1]: ";
    $choice = trim(fgets(STDIN)) ?: '1';

    echo "Email [admin@cek.resource.my.id]: ";
    $email = trim(fgets(STDIN)) ?: 'admin@cek.resource.my.id';

    $name = 'Super Admin';
    if ($choice === '2') {
        echo "Nama [Super Admin]: ";
        $name = trim(fgets(STDIN)) ?: 'Super Admin';
    }

    echo "Password baru [Admin@1234]: ";
    $pass = trim(fgets(STDIN)) ?: 'Admin@1234';

    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 10]);

    if ($choice === '1') {
        $admin = $db->fetchOne("SELECT id FROM admins WHERE email = ?", [$email]);
        if ($admin) {
            $db->update('admins',
                ['password' => $hash, 'is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')],
                'id = ?', [$admin['id']]
            );
            echo "\nBerhasil! Password '{$email}' telah direset.\n";
        } else {
            echo "\nAdmin '{$email}' tidak ditemukan, membuat baru...\n";
            $choice = '2';
        }
    }

    if ($choice === '2') {
        try {
            $db->insert('admins', buildAdminData($name, $email, $hash, true));
            echo "\nBerhasil! Admin '{$email}' telah dibuat.\n";
        } catch (Exception $e) {
            $db->update('admins',
                ['password' => $hash, 'is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')],
                'email = ?', [strtolower($email)]
            );
            echo "\nAdmin sudah ada, password berhasil diperbarui.\n";
        }
    }

    echo "\nLogin dengan:\n  Email   : {$email}\n  Password: {$pass}\n\n";
    echo "HAPUS file reset-admin.php setelah selesai!\n\n";
    exit(0);
}

// ── Proses form submit (browser) ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db) {
    $action = $_POST['action'] ?? '';
    $email  = trim($_POST['email']   ?? '');
    $name   = trim($_POST['name']    ?? 'Super Admin');
    $pass   = $_POST['password']     ?? '';
    $pass2  = $_POST['password2']    ?? '';

    if ($pass !== $pass2) {
        $message = 'Password dan konfirmasi tidak cocok.';
        $msgType = 'danger';
    } elseif (strlen($pass) < 6) {
        $message = 'Password minimal 6 karakter.';
        $msgType = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Format email tidak valid.';
        $msgType = 'danger';
    } else {
        $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 10]);

        try {
            if ($action === 'reset') {
                $admin = $db->fetchOne("SELECT id FROM admins WHERE email = ?", [$email]);
                if (!$admin) {
                    $message = "Admin <strong>" . htmlspecialchars($email) . "</strong> tidak ditemukan. Gunakan tab Buat Admin.";
                    $msgType = 'warning';
                } else {
                    $db->update('admins',
                        ['password' => $hash, 'is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')],
                        'id = ?', [$admin['id']]
                    );
                    $message = "Password untuk <strong>" . htmlspecialchars($email) . "</strong> berhasil direset!";
                    $msgType = 'success';
                    $done    = true;
                }
            } elseif ($action === 'create') {
                $exists = $db->fetchOne("SELECT id FROM admins WHERE email = ?", [strtolower($email)]);
                if ($exists) {
                    // Update saja
                    $db->update('admins',
                        ['password' => $hash, 'name' => $name, 'is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')],
                        'id = ?', [$exists['id']]
                    );
                    $message = "Admin <strong>" . htmlspecialchars($email) . "</strong> sudah ada, password diperbarui.";
                    $msgType = 'success';
                    $done    = true;
                } else {
                    $db->insert('admins', buildAdminData($name, $email, $hash, true));
                    $message = "Admin <strong>" . htmlspecialchars($email) . "</strong> berhasil dibuat!";
                    $msgType = 'success';
                    $done    = true;
                }
            }
        } catch (Exception $e) {
            $message = 'Error: ' . htmlspecialchars($e->getMessage());
            $msgType = 'danger';
        }
    }
}
?>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Admin — cek.resource.my.id</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#0f172a; color:#e2e8f0; font-family:sans-serif; }
        .card { background:#1e293b; border:1px solid #334155; border-radius:10px; }
        .form-control { background:#0f172a; border-color:#334155; color:#e2e8f0; }
        .form-control:focus { background:#0f172a; border-color:#3b82f6; color:#e2e8f0; box-shadow:0 0 0 3px rgba(59,130,246,.15); }
        .form-control::placeholder { color:#64748b; }
        .nav-tabs .nav-link { color:#94a3b8; border-color:transparent; }
        .nav-tabs .nav-link.active { background:#1e293b; border-color:#334155 #334155 #1e293b; color:#e2e8f0; }
        .nav-tabs { border-color:#334155; }
        table { color:#e2e8f0; } th { color:#94a3b8 !important; }
        td,th { border-color:#334155 !important; }
        .warn-box { background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.25); border-radius:8px; padding:.85rem 1rem; }
        .fix-box  { background:rgba(74,222,128,.08); border:1px solid rgba(74,222,128,.2);  border-radius:8px; padding:.85rem 1rem; }
        label { font-size:.85rem; color:#cbd5e1; }
    </style>
</head>
<body>
<div class="container py-5" style="max-width:620px">

    <div class="text-center mb-4">
        <h4 style="font-weight:700"><span style="color:#e63946">cek</span>.resource.my.id</h4>
        <p class="text-muted small mb-0">Admin Password Reset Utility</p>
    </div>

    <?php if ($dbError): ?>
    <div class="alert alert-danger">
        <strong>Database Error:</strong> <?= htmlspecialchars($dbError) ?><br>
        <small>Pastikan file <code>.env</code> sudah dikonfigurasi dan MySQL berjalan.</small>
    </div>

    <?php else: ?>

    <!-- Auto-fix notices -->
    <?php if (!empty($autoFixes)): ?>
    <div class="fix-box mb-3">
        <strong style="color:#4ade80">✓ Schema diperbaiki otomatis:</strong>
        <?php foreach ($autoFixes as $f): ?>
        <div class="text-muted small">• <?= htmlspecialchars($f) ?></div>
        <?php endforeach; ?>
        <div class="small mt-1" style="color:#86efac">Refresh halaman lalu coba lagi.</div>
    </div>
    <?php endif; ?>

    <div class="warn-box mb-4">
        <strong style="color:#f87171">⚠ Hapus file ini setelah selesai!</strong><br>
        <small class="text-muted">File <code>reset-admin.php</code> memberikan akses penuh tanpa autentikasi.</small>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $msgType ?> mb-4">
        <?= $message ?>
        <?php if ($done): ?>
        <hr class="my-2">
        <a href="/<?= basename(__DIR__) ?>/admin" class="btn btn-sm btn-danger">Buka Admin Panel →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!$done): ?>

    <!-- Daftar admin -->
    <?php if (!empty($adminList)): ?>
    <div class="card mb-4 p-3">
        <div class="text-muted mb-2" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em">Admin Terdaftar</div>
        <table class="table table-sm mb-0" style="font-size:.83rem">
            <thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($adminList as $a): ?>
            <tr>
                <td><?= htmlspecialchars($a['name']) ?></td>
                <td><?= htmlspecialchars($a['email']) ?></td>
                <td><span class="badge bg-secondary"><?= $a['role'] ?></span></td>
                <td><?= $a['is_active'] ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Nonaktif</span>' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Form -->
    <div class="card p-4">
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabReset">Reset Password</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabCreate">Buat Admin Baru</a></li>
        </ul>
        <div class="tab-content">

            <!-- Reset -->
            <div class="tab-pane active" id="tabReset">
                <form method="post">
                    <input type="hidden" name="action" value="reset">
                    <div class="mb-3">
                        <label>Email Admin</label>
                        <input type="email" name="email" class="form-control mt-1"
                            value="<?= htmlspecialchars($_POST['email'] ?? 'admin@cek.resource.my.id') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label>Password Baru</label>
                        <input type="password" name="password" class="form-control mt-1" placeholder="Minimal 6 karakter" required minlength="6">
                    </div>
                    <div class="mb-4">
                        <label>Konfirmasi Password</label>
                        <input type="password" name="password2" class="form-control mt-1" placeholder="Ulangi password" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-danger w-100">Reset Password</button>
                </form>
            </div>

            <!-- Create -->
            <div class="tab-pane" id="tabCreate">
                <form method="post">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label>Nama</label>
                        <input type="text" name="name" class="form-control mt-1" value="Super Admin">
                    </div>
                    <div class="mb-3">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control mt-1" placeholder="admin@cek.resource.my.id" required>
                    </div>
                    <div class="mb-3">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control mt-1" placeholder="Minimal 6 karakter" required minlength="6">
                    </div>
                    <div class="mb-4">
                        <label>Konfirmasi Password</label>
                        <input type="password" name="password2" class="form-control mt-1" placeholder="Ulangi password" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-danger w-100">Buat Admin Superadmin</button>
                </form>
            </div>

        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <p class="text-center text-muted mt-4" style="font-size:.75rem">
        Hapus setelah selesai: <code>reset-admin.php</code>
    </p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


$isCli     = (php_sapi_name() === 'cli');
$message   = '';
$msgType   = '';
$done      = false;

// ── Proses form submit ───────────────────────────────────────
if (!$isCli && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $email  = trim($_POST['email']  ?? '');
    $name   = trim($_POST['name']   ?? '');
    $pass   = $_POST['password']    ?? '';
    $pass2  = $_POST['password2']   ?? '';

    if ($pass !== $pass2) {
        $message = 'Password dan konfirmasi tidak cocok.';
        $msgType = 'danger';
    } elseif (strlen($pass) < 8) {
        $message = 'Password minimal 8 karakter.';
        $msgType = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Format email tidak valid.';
        $msgType = 'danger';
    } else {
        try {
            $db   = Database::getInstance();
            $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 10]);

            if ($action === 'reset') {
                // Reset password admin yang sudah ada
                $admin = $db->fetchOne("SELECT id FROM admins WHERE email = ?", [$email]);
                if (!$admin) {
                    $message = "Admin dengan email <strong>{$email}</strong> tidak ditemukan.";
                    $msgType = 'danger';
                } else {
                    $db->update('admins',
                        ['password' => $hash, 'updated_at' => date('Y-m-d H:i:s'), 'is_active' => 1],
                        'id = ?',
                        [$admin['id']]
                    );
                    $message = "Password untuk <strong>{$email}</strong> berhasil direset!";
                    $msgType = 'success';
                    $done    = true;
                }
            } elseif ($action === 'create') {
                // Buat admin baru
                $exists = $db->fetchOne("SELECT id FROM admins WHERE email = ?", [$email]);
                if ($exists) {
                    $message = "Email <strong>{$email}</strong> sudah terdaftar. Gunakan Reset Password.";
                    $msgType = 'warning';
                } else {
                    if (empty($name)) $name = 'Super Admin';
                    $db->insert('admins', [
                        'name'       => $name,
                        'email'      => strtolower($email),
                        'password'   => $hash,
                        'role'       => 'superadmin',
                        'is_active'  => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $message = "Admin <strong>{$email}</strong> berhasil dibuat!";
                    $msgType = 'success';
                    $done    = true;
                }
            }
        } catch (Exception $e) {
            $message = 'Database error: ' . htmlspecialchars($e->getMessage());
            $msgType = 'danger';
        }
    }
}

// ── Mode CLI ─────────────────────────────────────────────────
if ($isCli) {
    if (!defined('STDIN')) define('STDIN', fopen('php://stdin', 'r'));
    echo "\n=== cek.resource.my.id — Admin Password Reset ===\n\n";
    echo "1. Reset password admin yang sudah ada\n";
    echo "2. Buat admin baru\n";
    echo "Pilihan [1]: ";
    $choice = trim(fgets(STDIN)) ?: '1';

    echo "Email [admin@cek.resource.my.id]: ";
    $email = trim(fgets(STDIN)) ?: 'admin@cek.resource.my.id';

    $name = '';
    if ($choice === '2') {
        echo "Nama [Super Admin]: ";
        $name = trim(fgets(STDIN)) ?: 'Super Admin';
    }

    echo "Password baru [Admin@1234]: ";
    $pass = trim(fgets(STDIN)) ?: 'Admin@1234';

    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 10]);
    $db   = Database::getInstance();

    if ($choice === '1') {
        $admin = $db->fetchOne("SELECT id FROM admins WHERE email = ?", [$email]);
        if (!$admin) {
            echo "\nAdmin '{$email}' tidak ditemukan. Membuat baru...\n";
            $choice = '2';
        } else {
            $db->update('admins',
                ['password' => $hash, 'is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')],
                'id = ?', [$admin['id']]
            );
            echo "\nBerhasil! Password '{$email}' telah direset.\n";
        }
    }

    if ($choice === '2') {
        if (empty($name)) $name = 'Super Admin';
        try {
            $db->insert('admins', [
                'name'       => $name,
                'email'      => strtolower($email),
                'password'   => $hash,
                'role'       => 'superadmin',
                'is_active'  => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            echo "\nBerhasil! Admin '{$email}' telah dibuat.\n";
        } catch (Exception $e) {
            // Coba update jika sudah ada
            $db->update('admins',
                ['password' => $hash, 'is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')],
                'email = ?', [$email]
            );
            echo "\nAdmin sudah ada, password berhasil diperbarui.\n";
        }
    }

    echo "\nLogin dengan:\n  Email   : {$email}\n  Password: {$pass}\n\n";
    echo "HAPUS file reset-admin.php setelah selesai!\n\n";
    exit(0);
}

// ── Mode Browser ─────────────────────────────────────────────
// Cek apakah tabel admins ada
$tableExists = false;
try {
    $db          = Database::getInstance();
    $tableExists = $db->tableExists('admins');
    // Cek jumlah admin
    $adminCount = $tableExists ? (int)$db->fetchColumn("SELECT COUNT(*) FROM admins") : 0;
    $adminList  = $tableExists ? $db->fetchAll("SELECT id, name, email, role, is_active FROM admins ORDER BY id") : [];
} catch (Exception $e) {
    $dbError = $e->getMessage();
}
?>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Admin — cek.resource.my.id</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #0f172a; color: #e2e8f0; font-family: 'Inter', sans-serif; }
        .card { background: #1e293b; border: 1px solid #334155; }
        .form-control, .form-select { background: #0f172a; border-color: #334155; color: #e2e8f0; }
        .form-control:focus { background: #0f172a; border-color: #3b82f6; color: #e2e8f0; box-shadow: 0 0 0 3px rgba(59,130,246,.15); }
        .form-control::placeholder { color: #64748b; }
        .nav-tabs .nav-link { color: #94a3b8; border-color: transparent; }
        .nav-tabs .nav-link.active { background: #1e293b; border-color: #334155 #334155 #1e293b; color: #e2e8f0; }
        .nav-tabs { border-color: #334155; }
        .warning-box { background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.3); border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; }
        table { color: #e2e8f0; }
        th { color: #94a3b8 !important; }
        td, th { border-color: #334155 !important; }
    </style>
</head>
<body>
<div class="container py-5" style="max-width: 640px">

    <div class="text-center mb-4">
        <h4 style="font-weight:700"><span style="color:#e63946">cek</span>.resource.my.id</h4>
        <p class="text-muted small">Admin Password Reset Utility</p>
    </div>

    <?php if (isset($dbError)): ?>
    <div class="alert alert-danger">
        <strong>Database Error:</strong> <?= htmlspecialchars($dbError) ?><br>
        <small>Pastikan file <code>.env</code> sudah dikonfigurasi dengan benar.</small>
    </div>
    <?php else: ?>

    <!-- Warning -->
    <div class="warning-box">
        <strong style="color:#f87171">⚠ Perhatian Keamanan</strong><br>
        <small class="text-muted">Hapus file <code>reset-admin.php</code> segera setelah selesai digunakan!</small>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $msgType ?> mb-4">
        <?= $message ?>
        <?php if ($done): ?>
        <hr class="my-2">
        <a href="/<?= basename(__DIR__) ?>/admin" class="btn btn-sm btn-danger">Buka Admin Panel</a>
        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-sm btn-outline-secondary ms-2">Kembali</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!$done): ?>

    <!-- Daftar admin yang ada -->
    <?php if (!empty($adminList)): ?>
    <div class="card mb-4">
        <div class="card-body">
            <h6 class="text-muted mb-3" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.05em">Admin Terdaftar</h6>
            <table class="table table-sm" style="font-size:.85rem">
                <thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($adminList as $a): ?>
                <tr>
                    <td><?= htmlspecialchars($a['name']) ?></td>
                    <td><?= htmlspecialchars($a['email']) ?></td>
                    <td><span class="badge bg-secondary"><?= $a['role'] ?></span></td>
                    <td><?= $a['is_active'] ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Nonaktif</span>' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Form tabs -->
    <div class="card">
        <div class="card-body">
            <ul class="nav nav-tabs mb-4" id="tabs">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#tabReset">Reset Password</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#tabCreate">Buat Admin Baru</a>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Reset Password -->
                <div class="tab-pane active" id="tabReset">
                    <form method="post">
                        <input type="hidden" name="action" value="reset">
                        <div class="mb-3">
                            <label class="form-label small">Email Admin</label>
                            <input type="email" name="email" class="form-control"
                                value="admin@cek.resource.my.id" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Password Baru</label>
                            <input type="password" name="password" class="form-control"
                                placeholder="Minimal 8 karakter" required minlength="8">
                        </div>
                        <div class="mb-4">
                            <label class="form-label small">Konfirmasi Password</label>
                            <input type="password" name="password2" class="form-control"
                                placeholder="Ulangi password" required minlength="8">
                        </div>
                        <button type="submit" class="btn btn-danger w-100">
                            Reset Password
                        </button>
                    </form>
                </div>

                <!-- Buat Admin Baru -->
                <div class="tab-pane" id="tabCreate">
                    <form method="post">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label class="form-label small">Nama</label>
                            <input type="text" name="name" class="form-control"
                                placeholder="Super Admin" value="Super Admin">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Email</label>
                            <input type="email" name="email" class="form-control"
                                placeholder="admin@example.com" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Password</label>
                            <input type="password" name="password" class="form-control"
                                placeholder="Minimal 8 karakter" required minlength="8">
                        </div>
                        <div class="mb-4">
                            <label class="form-label small">Konfirmasi Password</label>
                            <input type="password" name="password2" class="form-control"
                                placeholder="Ulangi password" required minlength="8">
                        </div>
                        <button type="submit" class="btn btn-danger w-100">
                            Buat Admin Superadmin
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>
    <?php endif; ?>

    <p class="text-center text-muted mt-4" style="font-size:.78rem">
        Setelah selesai, hapus file ini: <code>reset-admin.php</code>
    </p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
