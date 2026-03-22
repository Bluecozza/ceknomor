<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel — cek.resource.my.id</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-w: 256px;
            --accent:    #e63946;
            --bg-body:   #0f172a;
            --bg-card:   #1e293b;
            --bg-hover:  #253347;
            --border:    #334155;
            --text:      #e2e8f0;
            --muted:     #94a3b8;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-body); color: var(--text); margin: 0; font-size: 14px; }

        /* ── Login ── */
        #loginOverlay { position:fixed;inset:0;background:var(--bg-body);display:flex;align-items:center;justify-content:center;z-index:9999; }
        .login-card { background:var(--bg-card);border:1px solid var(--border);border-radius:16px;padding:2.5rem;width:100%;max-width:400px; }
        .login-card .brand { font-family:'Space Grotesk',sans-serif;font-size:1.4rem;font-weight:700; }
        .login-card .brand span { color:var(--accent); }

        /* ── Sidebar ── */
        .sidebar { width:var(--sidebar-w);height:100vh;background:#0c1628;border-right:1px solid var(--border);position:fixed;top:0;left:0;display:flex;flex-direction:column;z-index:200;transition:transform .3s; }
        .sidebar-brand { padding:1.1rem 1.25rem;border-bottom:1px solid var(--border);font-family:'Space Grotesk',sans-serif;font-weight:700;font-size:1rem;color:#fff;display:flex;align-items:center;gap:.5rem; }
        .sidebar-brand span { color:var(--accent); }
        .sidebar-brand small { font-size:.65rem;color:var(--muted);font-family:'Inter',sans-serif;font-weight:400; }
        .sidebar-nav { flex:1;padding:.75rem 0;overflow-y:auto; }
        .nav-section { font-size:.65rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.25);padding:.9rem 1.25rem .35rem; }
        .nav-link { display:flex;align-items:center;gap:.65rem;padding:.5rem 1.25rem;color:rgba(255,255,255,.55);font-size:.86rem;border-left:3px solid transparent;transition:all .15s;text-decoration:none;cursor:pointer; }
        .nav-link:hover { color:rgba(255,255,255,.9);background:rgba(255,255,255,.05); }
        .nav-link.active { color:#fff;border-left-color:var(--accent);background:rgba(230,57,70,.08); }
        .badge-count { margin-left:auto;background:var(--accent);color:#fff;font-size:.65rem;padding:.15rem .45rem;border-radius:99px;min-width:18px;text-align:center; }
        .sidebar-footer { padding:.85rem 1.25rem;border-top:1px solid var(--border);font-size:.8rem;color:var(--muted); }

        /* ── Main ── */
        .main { margin-left:var(--sidebar-w);min-height:100vh;display:flex;flex-direction:column; }
        .topbar { height:60px;background:rgba(12,22,40,.95);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 1.5rem;position:sticky;top:0;z-index:100;backdrop-filter:blur(8px); }
        .page-title { font-family:'Space Grotesk',sans-serif;font-size:1rem;font-weight:600;margin:0; }
        .topbar-right { display:flex;align-items:center;gap:.75rem;font-size:.82rem; }
        .content { padding:1.5rem;flex:1; }
        .section-page { display:none; }
        .section-page.active { display:block; }

        /* ── Cards ── */
        .card-dark { background:var(--bg-card);border:1px solid var(--border);border-radius:10px;overflow:hidden; }
        .card-header-dark { padding:.85rem 1.1rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;font-weight:600;font-size:.88rem; }
        .stat-card { background:var(--bg-card);border:1px solid var(--border);border-radius:10px;padding:1.1rem 1.25rem;display:flex;align-items:center;gap:1rem; }
        .stat-icon { width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0; }
        .stat-value { font-family:'Space Grotesk',sans-serif;font-size:1.6rem;font-weight:700;line-height:1; }
        .stat-label { font-size:.75rem;color:var(--muted);margin-top:.2rem; }

        /* ── Table ── */
        .table { color:var(--text);border-color:var(--border);font-size:.84rem;margin:0; }
        .table thead th { background:rgba(255,255,255,.03);border-color:var(--border);color:var(--muted);font-weight:600;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;padding:.65rem 1rem;white-space:nowrap; }
        .table tbody tr { border-color:var(--border);transition:background .1s; }
        .table tbody tr:hover { background:rgba(255,255,255,.02); }
        .table td { padding:.6rem 1rem;vertical-align:middle;border-color:var(--border); }

        /* ── Forms ── */
        .form-control,.form-select { background:var(--bg-body);border:1px solid var(--border);color:var(--text);border-radius:7px;font-size:.85rem; }
        .form-control:focus,.form-select:focus { background:var(--bg-body);border-color:#3b82f6;color:var(--text);box-shadow:0 0 0 3px rgba(59,130,246,.15); }
        .form-control::placeholder { color:var(--muted); }
        .form-label { font-size:.83rem;font-weight:500;color:#cbd5e1;margin-bottom:.35rem; }

        /* ── Badges ── */
        .badge-pending  { background:rgba(250,204,21,.15);color:#fbbf24; }
        .badge-approved { background:rgba(74,222,128,.15);color:#4ade80; }
        .badge-rejected { background:rgba(148,163,184,.15);color:#94a3b8; }
        .badge-flagged  { background:rgba(248,113,113,.15);color:#f87171; }

        /* ── Modal ── */
        .modal-content { background:var(--bg-card);border:1px solid var(--border);color:var(--text); }
        .modal-header,.modal-footer { border-color:var(--border); }
        .btn-close { filter:invert(1) brightness(.7); }

        /* ── Misc ── */
        .form-switch .form-check-input { width:2.4em;height:1.25em;cursor:pointer; }
        .form-switch .form-check-input:checked { background-color:#4ade80;border-color:#4ade80; }
        .page-link { background:var(--bg-card);border-color:var(--border);color:var(--text);font-size:.82rem; }
        .page-link:hover { background:var(--bg-hover);color:#fff; }
        .page-item.active .page-link { background:var(--accent);border-color:var(--accent);color:#fff; }
        .page-item.disabled .page-link { background:var(--bg-card);color:var(--muted); }
        .filter-bar { display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem; }
        .filter-bar .form-control,.filter-bar .form-select { max-width:200px; }
        code { font-family:'JetBrains Mono',monospace;background:rgba(255,255,255,.07);padding:.15rem .4rem;border-radius:4px;font-size:.8em;color:#f472b6; }
        .toast-container { position:fixed;bottom:1.25rem;right:1.25rem;z-index:9998; }
        .toast { background:var(--bg-card);border:1px solid var(--border);color:var(--text);min-width:280px; }
        @media(max-width:768px) { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} }
    </style>
</head>
<body>

<!-- LOGIN -->
<div id="loginOverlay">
    <div class="login-card">
        <div class="brand mb-1"><span>cek</span>.resource.my.id</div>
        <div class="text-muted mb-4" style="font-size:.82rem">Panel Administrasi</div>
        <div id="loginError" class="alert alert-danger d-none py-2 mb-3" style="font-size:.84rem"></div>
        <div class="mb-3"><label class="form-label">Email</label><input type="email" id="loginEmail" class="form-control" placeholder="admin@cek.resource.my.id" autocomplete="email"></div>
        <div class="mb-4"><label class="form-label">Password</label><input type="password" id="loginPassword" class="form-control" placeholder="••••••••" autocomplete="current-password"></div>
        <button class="btn btn-danger w-100 fw-semibold" id="loginBtn" onclick="doLogin()">Masuk</button>
    </div>
</div>

<!-- SIDEBAR -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="bi bi-shield-check text-danger"></i>
        <div><div><span>cek</span>.resource</div><small>Admin Panel</small></div>
    </div>
    <div class="sidebar-nav">
        <div class="nav-section">Utama</div>
        <a class="nav-link active" onclick="showPage('dashboard')"><i class="bi bi-grid"></i> Dashboard</a>
        <a class="nav-link" onclick="showPage('reports')"><i class="bi bi-flag"></i> Laporan <span class="badge-count" id="pendingBadge" style="display:none">0</span></a>
        <a class="nav-link" onclick="showPage('risk')"><i class="bi bi-exclamation-diamond"></i> Risk Monitor</a>
        <a class="nav-link" onclick="showPage('searches')"><i class="bi bi-search"></i> Log Pencarian</a>
        <div class="nav-section">Sistem</div>
        <a class="nav-link" onclick="showPage('modules')"><i class="bi bi-puzzle"></i> Modul</a>
        <a class="nav-link" onclick="showPage('settings')"><i class="bi bi-gear"></i> Pengaturan</a>
        <div class="nav-section">Akun</div>
        <a class="nav-link" onclick="showPage('admins')"><i class="bi bi-people"></i> Admin Users</a>
        <a class="nav-link" onclick="showPage('api-keys')"><i class="bi bi-key"></i> API Keys</a>
    </div>
    <div class="sidebar-footer">
       
        <a href="/" target="_blank" class="text-muted text-decoration-none small me-2"><i class="bi bi-box-arrow-up-right"></i> Situs</a>
        <a href="#" class="text-muted text-decoration-none small" onclick="doLogout()"><i class="bi bi-box-arrow-right"></i> Keluar</a>
    </div>
</nav>

<!-- MAIN -->
<div class="main" id="mainApp" style="display:none">
    <div class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm d-md-none" style="background:rgba(255,255,255,.07);color:#e2e8f0;border:1px solid #334155" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
            <h1 class="page-title" id="pageTitle">Dashboard</h1>
        </div>
        <div class="topbar-right">
            <span class="badge bg-success rounded-pill" style="font-size:.7rem">● Online</span>
            <span class="text-muted" id="adminNameTop">—</span>
        </div>
    </div>

    <div class="content">

        <!-- DASHBOARD -->
        <div class="section-page active" id="page-dashboard">
            <div class="row g-3 mb-4" id="statCards"><div class="col-12 text-center py-5"><div class="spinner-border text-danger"></div></div></div>
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card-dark">
                        <div class="card-header-dark"><span><i class="bi bi-clock-history me-2 text-muted"></i>Laporan Terbaru</span><a href="#" class="btn btn-sm btn-outline-danger" style="font-size:.78rem" onclick="showPage('reports')">Lihat Semua</a></div>
                        <div class="table-responsive"><table class="table"><thead><tr><th>Data</th><th>Kategori</th><th>Status</th><th>Waktu</th></tr></thead><tbody id="recentReportsTable"><tr><td colspan="4" class="text-center text-muted py-4">Memuat...</td></tr></tbody></table></div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card-dark mb-3">
                        <div class="card-header-dark"><span><i class="bi bi-bar-chart me-2 text-muted"></i>Kategori Teratas</span></div>
                        <div class="table-responsive"><table class="table"><thead><tr><th>Kategori</th><th>Laporan</th></tr></thead><tbody id="topCategoriesTable"><tr><td colspan="2" class="text-center text-muted py-3">Memuat...</td></tr></tbody></table></div>
                    </div>
                    <div class="card-dark">
                        <div class="card-header-dark"><span><i class="bi bi-search me-2 text-muted"></i>Top Pencarian</span></div>
                        <div id="topSearchesContainer" class="p-3"><div class="text-center text-muted py-2 small">Memuat...</div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- REPORTS -->
        <div class="section-page" id="page-reports">
            <div class="filter-bar">
                <input type="text" id="reportSearch" class="form-control" placeholder="Cari nilai / judul...">
                <select id="reportStatusFilter" class="form-select"><option value="">Semua Status</option><option value="pending">Pending</option><option value="approved">Disetujui</option><option value="rejected">Ditolak</option><option value="flagged">Flagged</option></select>
                <select id="reportCategoryFilter" class="form-select"><option value="">Semua Kategori</option><option value="phone">Nomor Telepon</option><option value="bank_account">Rekening</option><option value="dana">DANA</option><option value="ovo">OVO</option><option value="gopay">GoPay</option><option value="email">Email</option><option value="other">Lainnya</option></select>
                <button class="btn btn-danger btn-sm" onclick="loadReports(1)"><i class="bi bi-funnel me-1"></i>Filter</button>
                <button class="btn btn-sm" style="background:rgba(255,255,255,.07);border:1px solid #334155;color:#e2e8f0" onclick="resetReportFilters()">Reset</button>
            </div>
            <div class="card-dark">
                <div class="card-header-dark">
                    <span>Daftar Laporan <span id="reportTotalBadge" class="badge bg-secondary ms-1">0</span></span>
                    <div class="d-flex gap-2">
                        <button id="bulkApproveBtn" class="btn btn-sm btn-success" onclick="bulkModerate('approve')" style="display:none!important"><i class="bi bi-check2-all me-1"></i>Setujui</button>
                        <button id="bulkRejectBtn"  class="btn btn-sm btn-danger"  onclick="bulkModerate('reject')"  style="display:none!important"><i class="bi bi-x-lg me-1"></i>Tolak</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th><input type="checkbox" id="selectAllReports" onchange="toggleSelectAll()"></th><th>#</th><th>Data Dilaporkan</th><th>Kategori</th><th>Jenis</th><th>Status</th><th>Pelapor</th><th>Waktu</th><th>Aksi</th></tr></thead>
                        <tbody id="reportsTable"><tr><td colspan="9" class="text-center text-muted py-4">Memuat...</td></tr></tbody>
                    </table>
                </div>
                <div class="p-3 border-top d-flex justify-content-between align-items-center" style="border-color:#334155!important">
                    <span class="text-muted small" id="reportsPaginationInfo"></span>
                    <nav><ul class="pagination pagination-sm mb-0" id="reportsPagination"></ul></nav>
                </div>
            </div>
        </div>

        <!-- RISK -->
        <div class="section-page" id="page-risk">
            <div class="filter-bar">
                <select id="riskLevelFilter" class="form-select"><option value="">Semua Level</option><option value="critical">Critical</option><option value="high">High</option><option value="medium">Medium</option><option value="low">Low</option><option value="safe">Safe</option></select>
                <select id="riskCategoryFilter" class="form-select"><option value="">Semua Kategori</option><option value="1">Telepon</option><option value="2">Rekening</option><option value="3">DANA</option><option value="4">OVO</option><option value="5">GoPay</option></select>
                <button class="btn btn-danger btn-sm" onclick="loadRisk(1)"><i class="bi bi-funnel me-1"></i>Filter</button>
            </div>
            <div class="card-dark">
                <div class="card-header-dark"><span>Risk Scores <span id="riskTotalBadge" class="badge bg-secondary ms-1">0</span></span><span class="text-muted small">Diurutkan: skor tertinggi</span></div>
                <div class="table-responsive"><table class="table"><thead><tr><th>Nilai Normalized</th><th>Kategori</th><th>Level</th><th>Skor</th><th>Approved</th><th>Total</th><th>Terakhir</th></tr></thead><tbody id="riskTable"><tr><td colspan="7" class="text-center text-muted py-4">Memuat...</td></tr></tbody></table></div>
                <div class="p-3 border-top d-flex justify-content-between align-items-center" style="border-color:#334155!important"><span class="text-muted small" id="riskPaginationInfo"></span><nav><ul class="pagination pagination-sm mb-0" id="riskPagination"></ul></nav></div>
            </div>
        </div>

        <!-- SEARCH LOGS -->
        <div class="section-page" id="page-searches">
            <div class="filter-bar">
                <input type="text" id="searchLogQuery" class="form-control" placeholder="Filter query...">
                <select id="searchHasResult" class="form-select"><option value="">Semua</option><option value="1">Ada Hasil</option><option value="0">Tidak Ada</option></select>
                <button class="btn btn-danger btn-sm" onclick="loadSearchLogs(1)"><i class="bi bi-funnel me-1"></i>Filter</button>
            </div>
            <div class="card-dark">
                <div class="card-header-dark"><span>Log Pencarian <span id="searchLogTotalBadge" class="badge bg-secondary ms-1">0</span></span></div>
                <div class="table-responsive"><table class="table"><thead><tr><th>Query</th><th>Normalized</th><th>Kategori</th><th>Hasil</th><th>Ada Data?</th><th>IP</th><th>Waktu</th></tr></thead><tbody id="searchLogsTable"><tr><td colspan="7" class="text-center text-muted py-4">Memuat...</td></tr></tbody></table></div>
                <div class="p-3 border-top d-flex justify-content-between align-items-center" style="border-color:#334155!important"><span class="text-muted small" id="searchLogsPaginationInfo"></span><nav><ul class="pagination pagination-sm mb-0" id="searchLogsPagination"></ul></nav></div>
            </div>
        </div>

        <!-- MODULES -->
        <div class="section-page" id="page-modules">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-muted small">Modul aktif dimuat otomatis saat server boot.</span>
                <button class="btn btn-sm btn-outline-secondary" onclick="discoverModules()"><i class="bi bi-arrow-repeat me-1"></i>Scan Modul Baru</button>
            </div>
            <div class="row g-3" id="moduleCards"><div class="col-12 text-center py-5"><div class="spinner-border text-danger"></div></div></div>
        </div>

        <!-- SETTINGS -->
        <div class="section-page" id="page-settings">
            <div class="row g-4">
                <div class="col-lg-7"><div class="card-dark"><div class="card-header-dark"><i class="bi bi-gear me-2 text-muted"></i>Pengaturan Sistem</div><div class="p-4" id="settingsForm"><div class="text-center py-4"><div class="spinner-border text-danger"></div></div></div></div></div>
                <div class="col-lg-5"><div class="card-dark"><div class="card-header-dark"><i class="bi bi-info-circle me-2 text-muted"></i>Info Sistem</div><div class="p-3" id="sysInfo"></div></div></div>
            </div>
        </div>

        <!-- ADMINS -->
        <div class="section-page" id="page-admins">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-muted small">Kelola akun admin dan moderator.</span>
                <button class="btn btn-danger btn-sm" onclick="openAddAdmin()"><i class="bi bi-plus me-1"></i>Tambah Admin</button>
            </div>
            <div class="card-dark">
                <div class="card-header-dark"><i class="bi bi-people me-2 text-muted"></i>Daftar Admin</div>
                <div class="table-responsive"><table class="table"><thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>Status</th><th>Login Terakhir</th><th>Aksi</th></tr></thead><tbody id="adminsTable"><tr><td colspan="6" class="text-center text-muted py-4">Memuat...</td></tr></tbody></table></div>
            </div>
        </div>

        <!-- API KEYS -->
        <div class="section-page" id="page-api-keys">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-muted small">API key untuk aplikasi klien pihak ketiga.</span>
                <button class="btn btn-danger btn-sm" onclick="openAddApiKey()"><i class="bi bi-plus me-1"></i>Buat API Key</button>
            </div>
            <div class="card-dark mb-3" id="newKeyAlert" style="display:none">
                <div class="p-3" style="background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.2);border-radius:10px">
                    <div class="fw-semibold text-success mb-1"><i class="bi bi-check-circle me-1"></i>API Key Dibuat — Simpan sekarang, tidak akan ditampilkan lagi.</div>
                    <div class="d-flex align-items-center gap-2 mt-2">
                        <code id="newKeyValue" class="flex-grow-1" style="word-break:break-all;font-size:.82rem;color:#4ade80"></code>
                        <button class="btn btn-sm btn-outline-secondary" onclick="copyNewKey()"><i class="bi bi-clipboard"></i></button>
                    </div>
                </div>
            </div>
            <div class="card-dark">
                <div class="card-header-dark"><i class="bi bi-key me-2 text-muted"></i>Daftar API Keys</div>
                <div class="table-responsive"><table class="table"><thead><tr><th>Nama</th><th>Prefix</th><th>Permissions</th><th>Rate Limit</th><th>Pemakaian</th><th>Status</th><th>Aksi</th></tr></thead><tbody id="apiKeysTable"><tr><td colspan="7" class="text-center text-muted py-4">Memuat...</td></tr></tbody></table></div>
            </div>
        </div>

    </div>
</div>

<!-- MODAL: Moderate -->
<div class="modal fade" id="moderateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-flag me-2"></i>Review Laporan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="moderateBody" style="min-height:200px"><div class="text-center py-4"><div class="spinner-border text-danger"></div></div></div>
            <div class="modal-footer">
                <input type="text" id="modNote" class="form-control me-auto" style="max-width:300px" placeholder="Catatan moderasi (opsional)">
                <button class="btn btn-success" onclick="moderateReport('approve')"><i class="bi bi-check2 me-1"></i>Setujui</button>
                <button class="btn btn-warning text-dark" onclick="moderateReport('flag')"><i class="bi bi-flag me-1"></i>Flag</button>
                <button class="btn btn-danger" onclick="moderateReport('reject')"><i class="bi bi-x me-1"></i>Tolak</button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Add Admin -->
<div class="modal fade" id="addAdminModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Tambah Admin</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Nama Lengkap</label><input type="text" id="newAdminName" class="form-control"></div>
                <div class="mb-3"><label class="form-label">Email</label><input type="email" id="newAdminEmail" class="form-control"></div>
                <div class="mb-3"><label class="form-label">Role</label><select id="newAdminRole" class="form-select"><option value="moderator">Moderator</option><option value="admin">Admin</option></select></div>
                <div class="mb-3"><label class="form-label">Password</label><input type="password" id="newAdminPass" class="form-control" placeholder="Min 8 karakter"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-danger" onclick="submitAddAdmin()">Simpan</button></div>
        </div>
    </div>
</div>

<!-- MODAL: Add API Key -->
<div class="modal fade" id="addApiKeyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-key me-2"></i>Buat API Key</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Nama / Keterangan</label><input type="text" id="newKeyName" class="form-control" placeholder="Contoh: Aplikasi Android v1"></div>
                <div class="mb-3"><label class="form-label">Rate Limit (req/menit)</label><input type="number" id="newKeyRateLimit" class="form-control" value="60" min="1"></div>
                <div class="mb-3"><label class="form-label">Environment</label><select id="newKeyEnv" class="form-select"><option value="live">Production (live)</option><option value="test">Test</option></select></div>
                <div class="mb-3"><label class="form-label">Tanggal Kadaluarsa (opsional)</label><input type="date" id="newKeyExpiry" class="form-control"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-danger" onclick="submitAddApiKey()">Buat Key</button></div>
        </div>
    </div>
</div>

<div class="toast-container"><div id="mainToast" class="toast align-items-center border-0" role="alert"><div class="d-flex"><div class="toast-body" id="toastBody"></div><button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
'use strict';
const API = '/api/v1';
let token = localStorage.getItem('admin_token'), me = null;
let currentReportId = null, selectedReports = new Set();
let reportPage = 1, riskPage = 1, searchLogPage = 1;

// ── Utils ──────────────────────────────────────────────────────
function toast(msg, type='info') {
    const colors = {success:'#4ade80',error:'#f87171',info:'#60a5fa',warning:'#fbbf24'};
    const el = document.getElementById('toastBody');
    el.style.color = colors[type]||colors.info; el.textContent = msg;
    bootstrap.Toast.getOrCreateInstance(document.getElementById('mainToast'),{delay:3000}).show();
}
async function api(method, path, body=null) {
    const opts = {method, headers:{'Content-Type':'application/json','Authorization':'Bearer '+(token||'')}};
    if (body) opts.body = JSON.stringify(body);
    const res = await fetch(API+path, opts);
    const data = await res.json().catch(()=>({success:false,message:'Response tidak valid'}));
    if (!res.ok && !data.success) { const e=new Error(data.message||'Request gagal'); e.errors=data.errors||{}; e.status=res.status; throw e; }
    return data;
}
function statusBadge(s) {
    const m={pending:['badge-pending','Pending'],approved:['badge-approved','Disetujui'],rejected:['badge-rejected','Ditolak'],flagged:['badge-flagged','Flagged']};
    const [c,l]=m[s]||['bg-secondary',s]; return `<span class="badge ${c}">${l}</span>`;
}
function riskBadge(level) {
    const m={unknown:['❓','#94a3b8'],safe:['✅','#4ade80'],low:['🟡','#a3e635'],medium:['🟠','#facc15'],high:['🔴','#fb923c'],critical:['🚨','#f87171']};
    const labels={unknown:'Unknown',safe:'Aman',low:'Rendah',medium:'Sedang',high:'Tinggi',critical:'KRITIS'};
    const [icon,color]=m[level]||m.unknown;
    return `<span style="color:${color};font-weight:600;font-size:.82rem">${icon} ${labels[level]||level}</span>`;
}
function severityDot(n) {
    const c=['','#94a3b8','#facc15','#fb923c','#f87171'][n]||'#94a3b8';
    return `<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${c};margin-right:4px"></span>`;
}
function timeAgo(d) {
    if(!d) return '—';
    const diff=Math.floor((Date.now()-new Date(d))/1000);
    if(diff<60) return 'Baru saja';
    if(diff<3600) return Math.floor(diff/60)+' mnt lalu';
    if(diff<86400) return Math.floor(diff/3600)+' jam lalu';
    return Math.floor(diff/86400)+' hari lalu';
}
function fmtDate(d) {
    if(!d) return '—';
    return new Date(d).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'});
}
function buildPagination(ulId, total, page, perPage, cb) {
    const last=Math.ceil(total/perPage)||1, ul=document.getElementById(ulId);
    ul.innerHTML='';
    const add=(label,pg,disabled=false,active=false)=>{
        const li=document.createElement('li');
        li.className='page-item'+(disabled?' disabled':'')+(active?' active':'');
        li.innerHTML=`<a class="page-link" href="#">${label}</a>`;
        if(!disabled&&!active) li.querySelector('a').onclick=e=>{e.preventDefault();cb(pg);};
        ul.appendChild(li);
    };
    add('‹',page-1,page<=1);
    const s=Math.max(1,page-2),e=Math.min(last,page+2);
    if(s>1){add('1',1);if(s>2)add('…',null,true);}
    for(let p=s;p<=e;p++) add(p,p,false,p===page);
    if(e<last){if(e<last-1)add('…',null,true);add(last,last);}
    add('›',page+1,page>=last);
}

// ── Auth ───────────────────────────────────────────────────────
async function doLogin() {
    const email=document.getElementById('loginEmail').value.trim();
    const pass=document.getElementById('loginPassword').value;
    const err=document.getElementById('loginError');
    if(!email||!pass){err.textContent='Email dan password wajib diisi.';err.classList.remove('d-none');return;}
    err.classList.add('d-none');
    document.getElementById('loginBtn').disabled=true;
    document.getElementById('loginBtn').textContent='Masuk...';
    try {
        const data=await api('POST','/auth/login',{email,password:pass});
        token=data.data.token; me=data.data.admin;
        localStorage.setItem('admin_token',token); initAdmin();
    } catch(e) {
        err.textContent=e.message||'Login gagal.'; err.classList.remove('d-none');
        document.getElementById('loginBtn').disabled=false;
        document.getElementById('loginBtn').textContent='Masuk';
    }
}
document.getElementById('loginPassword').addEventListener('keydown',e=>{if(e.key==='Enter')doLogin();});
function doLogout(){localStorage.removeItem('admin_token');location.reload();}
async function checkAuth() {
    if(!token) return false;
    try{const data=await api('GET','/auth/me');me=data.data.admin;return true;}catch{return false;}
}
function initAdmin() {
    document.getElementById('loginOverlay').style.display='none';
    document.getElementById('mainApp').style.display='flex';
    document.getElementById('adminInfo').textContent=(me?.name||'—')+' ('+me?.role+')';
    document.getElementById('adminNameTop').textContent=me?.name||'—';
    showPage('dashboard');
}

// ── Navigation ─────────────────────────────────────────────────
const PAGE_TITLES={dashboard:'Dashboard',reports:'Laporan',risk:'Risk Monitor',searches:'Log Pencarian',modules:'Modul',settings:'Pengaturan',admins:'Admin Users','api-keys':'API Keys'};
function showPage(page) {
    document.querySelectorAll('.section-page').forEach(p=>p.classList.remove('active'));
    document.querySelectorAll('.nav-link').forEach(l=>l.classList.remove('active'));
    const pg=document.getElementById('page-'+page); if(pg) pg.classList.add('active');
    document.querySelectorAll('.nav-link').forEach(l=>{if(l.getAttribute('onclick')?.includes(`'${page}'`))l.classList.add('active');});
    document.getElementById('pageTitle').textContent=PAGE_TITLES[page]||page;
    document.getElementById('sidebar').classList.remove('open');
    ({dashboard:loadDashboard,reports:()=>loadReports(1),risk:()=>loadRisk(1),searches:()=>loadSearchLogs(1),modules:loadModules,settings:loadSettings,admins:loadAdmins,'api-keys':loadApiKeys})[page]?.();
}
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');}

// ── Dashboard ──────────────────────────────────────────────────
async function loadDashboard() {
    try {
        const {data:s}=await api('GET','/admin/stats');
        const cards=[
            {icon:'bi-flag-fill',color:'#e63946',bg:'rgba(230,57,70,.12)',label:'Total Laporan',val:s.total_reports||0},
            {icon:'bi-hourglass-split',color:'#fbbf24',bg:'rgba(251,191,36,.12)',label:'Pending Review',val:s.pending_reports||0},
            {icon:'bi-check-circle',color:'#4ade80',bg:'rgba(74,222,128,.12)',label:'Disetujui',val:s.approved_reports||0},
            {icon:'bi-exclamation-triangle',color:'#fb923c',bg:'rgba(251,146,60,.12)',label:'High/Critical',val:s.high_risk_count||0},
        ];
        document.getElementById('statCards').innerHTML=cards.map(c=>`
            <div class="col-sm-6 col-xl-3"><div class="stat-card">
                <div class="stat-icon" style="background:${c.bg}"><i class="bi ${c.icon}" style="color:${c.color}"></i></div>
                <div><div class="stat-value">${Number(c.val).toLocaleString('id')}</div><div class="stat-label">${c.label}</div></div>
            </div></div>`).join('');
        if(s.pending_reports>0){const pb=document.getElementById('pendingBadge');pb.textContent=s.pending_reports;pb.style.display='inline-block';}
        document.getElementById('recentReportsTable').innerHTML=s.recent_reports?.length
            ?s.recent_reports.map(r=>`<tr><td><code style="font-size:.8rem">${r.reported_value||'—'}</code></td><td class="text-muted small">${r.category_name||'—'}</td><td>${statusBadge(r.status)}</td><td class="text-muted small">${timeAgo(r.created_at)}</td></tr>`).join('')
            :'<tr><td colspan="4" class="text-center text-muted py-3">Belum ada laporan</td></tr>';
        document.getElementById('topCategoriesTable').innerHTML=s.top_categories?.length
            ?s.top_categories.map(c=>`<tr><td>${c.name}</td><td><span class="badge bg-secondary">${c.count}</span></td></tr>`).join('')
            :'<tr><td colspan="2" class="text-center text-muted py-2">—</td></tr>';
        const srch=document.getElementById('topSearchesContainer');
        srch.innerHTML=s.top_searches?.length
            ?s.top_searches.slice(0,8).map(q=>`<div class="d-flex justify-content-between align-items-center py-1 border-bottom" style="border-color:#334155!important;font-size:.82rem"><code>${q.query}</code><span class="badge bg-secondary">${q.count}×</span></div>`).join('')
            :'<div class="text-center text-muted py-2 small">Belum ada data</div>';
    } catch(e) { document.getElementById('statCards').innerHTML=`<div class="col-12"><div class="alert alert-danger">${e.message}</div></div>`; }
}

// ── Reports ────────────────────────────────────────────────────
async function loadReports(page=1) {
    reportPage=page; selectedReports.clear();
    document.getElementById('selectAllReports').checked=false;
    const p=new URLSearchParams({page,per_page:20});
    const s=document.getElementById('reportSearch').value;
    const st=document.getElementById('reportStatusFilter').value;
    const cat=document.getElementById('reportCategoryFilter').value;
    if(s)p.set('search',s); if(st)p.set('status',st); if(cat)p.set('category',cat);
    document.getElementById('reportsTable').innerHTML='<tr><td colspan="9" class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-danger"></div></td></tr>';
    try {
        const {data:reports,pagination}=await api('GET','/admin/reports?'+p);
        const total=pagination?.total||0;
        document.getElementById('reportTotalBadge').textContent=total;
        document.getElementById('reportsPaginationInfo').textContent=`${reports.length} dari ${total} laporan`;
        document.getElementById('reportsTable').innerHTML=reports.length
            ?reports.map(r=>`<tr>
                <td><input type="checkbox" class="report-cb" data-id="${r.id}" onchange="onReportCheck()"></td>
                <td class="text-muted small">${r.id}</td>
                <td><div style="font-family:'JetBrains Mono',monospace;font-size:.82rem;color:#f472b6">${r.reported_value||'—'}</div><div class="text-muted" style="font-size:.75rem;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${r.title||''}</div></td>
                <td class="text-muted small">${r.category_name||'—'}</td>
                <td>${severityDot(r.severity)}<span class="text-muted small">${r.report_type_name||'—'}</span></td>
                <td>${statusBadge(r.status)}</td>
                <td class="text-muted small">${r.reporter_name||'—'}</td>
                <td class="text-muted small">${timeAgo(r.created_at)}</td>
                <td><button class="btn btn-sm btn-outline-secondary" style="font-size:.75rem" onclick="openModerate(${r.id})"><i class="bi bi-eye"></i></button></td>
            </tr>`).join('')
            :'<tr><td colspan="9" class="text-center text-muted py-4">Tidak ada laporan</td></tr>';
        buildPagination('reportsPagination',total,page,20,loadReports);
    } catch(e){ document.getElementById('reportsTable').innerHTML=`<tr><td colspan="9" class="text-center text-danger py-3">${e.message}</td></tr>`; }
}
function resetReportFilters(){document.getElementById('reportSearch').value='';document.getElementById('reportStatusFilter').value='';document.getElementById('reportCategoryFilter').value='';loadReports(1);}
function onReportCheck(){
    document.querySelectorAll('.report-cb:checked').forEach(cb=>selectedReports.add(+cb.dataset.id));
    document.querySelectorAll('.report-cb:not(:checked)').forEach(cb=>selectedReports.delete(+cb.dataset.id));
    const has=selectedReports.size>0;
    document.getElementById('bulkApproveBtn').style.setProperty('display',has?'inline-block':'none','important');
    document.getElementById('bulkRejectBtn').style.setProperty('display',has?'inline-block':'none','important');
}
function toggleSelectAll(){
    const checked=document.getElementById('selectAllReports').checked;
    document.querySelectorAll('.report-cb').forEach(cb=>{cb.checked=checked;checked?selectedReports.add(+cb.dataset.id):selectedReports.delete(+cb.dataset.id);});
    onReportCheck();
}
async function bulkModerate(action){
    if(!selectedReports.size||!confirm(`${action==='approve'?'Setujui':'Tolak'} ${selectedReports.size} laporan?`)) return;
    try{const d=await api('POST','/admin/reports/bulk',{ids:[...selectedReports],action});toast(d.data.message||'Selesai','success');loadReports(reportPage);}
    catch(e){toast(e.message,'error');}
}
async function openModerate(id){
    currentReportId=id;
    document.getElementById('modNote').value='';
    document.getElementById('moderateBody').innerHTML='<div class="text-center py-4"><div class="spinner-border text-danger"></div></div>';
    new bootstrap.Modal('#moderateModal').show();
    try{
        const {data:{report:r}}=await api('GET','/admin/reports/'+id);
        const evid=r.evidence_urls?.length?r.evidence_urls.map(u=>`<a href="${u}" target="_blank"><img src="${u}" style="width:80px;height:80px;object-fit:cover;border-radius:6px;border:1px solid #334155"></a>`).join(''):'<span class="text-muted small">Tidak ada bukti</span>';
        document.getElementById('moderateBody').innerHTML=`
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="mb-2"><div class="text-muted small">Data Dilaporkan</div><span style="font-family:'JetBrains Mono',monospace;color:#f472b6;font-size:1.1rem;font-weight:600">${r.reported_value||'—'}</span></div>
                    <div class="mb-2"><div class="text-muted small">Kategori</div><strong>${r.category_name||'—'}</strong></div>
                    <div class="mb-2"><div class="text-muted small">Jenis Laporan</div>${severityDot(r.severity)}<strong>${r.report_type_name||'—'}</strong> <span class="badge bg-secondary">Sev.${r.severity||'?'}</span></div>
                    ${r.bank_name?`<div class="mb-2"><div class="text-muted small">Bank</div><strong>${r.bank_name}</strong></div>`:''}
                    ${r.account_name?`<div class="mb-2"><div class="text-muted small">Pemilik</div><strong>${r.account_name}</strong></div>`:''}
                    ${r.incident_date?`<div class="mb-2"><div class="text-muted small">Tanggal Kejadian</div>${fmtDate(r.incident_date)}</div>`:''}
                    ${r.amount_lost?`<div class="mb-2"><div class="text-muted small">Kerugian</div>Rp ${Number(r.amount_lost).toLocaleString('id')}</div>`:''}
                </div>
                <div class="col-md-6">
                    <div class="mb-2"><div class="text-muted small">Pelapor</div><strong>${r.reporter_name||'Anonim'}</strong></div>
                    <div class="mb-2"><div class="text-muted small">Status</div>${statusBadge(r.status)}</div>
                    <div class="mb-2"><div class="text-muted small">Dikirim</div>${timeAgo(r.created_at)}</div>
                    ${r.risk_score?`<div class="mb-2"><div class="text-muted small">Risk</div>${riskBadge(r.risk_level)} <span class="text-muted small">(${r.risk_score})</span></div>`:''}
                    <div class="mt-2"><div class="text-muted small mb-1">Bukti</div><div class="d-flex flex-wrap gap-1">${evid}</div></div>
                </div>
                <div class="col-12">
                    <div class="text-muted small mb-1">Judul</div><div class="fw-semibold mb-2">${r.title||'—'}</div>
                    <div class="text-muted small mb-1">Deskripsi</div>
                    <div style="background:rgba(255,255,255,.04);border:1px solid #334155;border-radius:8px;padding:.75rem;white-space:pre-wrap;font-size:.84rem;max-height:150px;overflow-y:auto">${r.description||'—'}</div>
                </div>
                ${r.moderation_history?.length?`<div class="col-12"><div class="text-muted small mb-1">Riwayat Moderasi</div><div style="font-size:.8rem">${r.moderation_history.map(h=>`<div class="d-flex gap-2 py-1 border-bottom" style="border-color:#334155!important"><span class="text-muted">${fmtDate(h.created_at)}</span><strong>${h.admin_name||'—'}</strong><span>${h.action}</span>${h.description?`<span class="text-muted">— ${h.description}</span>`:''}</div>`).join('')}</div></div>`:''}
            </div>`;
    } catch(e){document.getElementById('moderateBody').innerHTML=`<div class="alert alert-danger">${e.message}</div>`;}
}
async function moderateReport(action){
    if(!currentReportId) return;
    try{
        await api('POST',`/admin/reports/${currentReportId}/moderate`,{action,note:document.getElementById('modNote').value});
        bootstrap.Modal.getInstance(document.getElementById('moderateModal')).hide();
        toast('Laporan berhasil di-'+action,'success'); loadReports(reportPage); loadDashboard();
    }catch(e){toast(e.message,'error');}
}

// ── Risk Monitor ───────────────────────────────────────────────
async function loadRisk(page=1){
    riskPage=page;
    const level=document.getElementById('riskLevelFilter').value;
    const cat=document.getElementById('riskCategoryFilter').value;
    const p=new URLSearchParams({page,per_page:25});
    if(level)p.set('level',level); if(cat)p.set('category',cat);
    document.getElementById('riskTable').innerHTML='<tr><td colspan="7" class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-danger"></div></td></tr>';
    try{
        const {data:rows,pagination}=await api('GET','/admin/risk-scores?'+p);
        const total=pagination?.total||0;
        document.getElementById('riskTotalBadge').textContent=total;
        document.getElementById('riskPaginationInfo').textContent=`${rows.length} dari ${total}`;
        document.getElementById('riskTable').innerHTML=rows.length
            ?rows.map(r=>{const sc=parseFloat(r.score||r.risk_score||0);return`<tr>
                <td><code style="font-size:.82rem;color:#f472b6">${r.normalized_value||r.reported_value_normalized||'—'}</code></td>
                <td class="text-muted small">${r.category_name||'—'}</td>
                <td>${riskBadge(r.level||r.risk_level)}</td>
                <td><div style="width:80px;background:rgba(255,255,255,.08);border-radius:99px;height:6px;display:inline-block;vertical-align:middle"><div style="width:${Math.min(100,sc)}%;background:#e63946;height:6px;border-radius:99px"></div></div><span class="ms-2 small">${sc.toFixed(1)}</span></td>
                <td>${r.approved_count||r.approved_reports||0}</td>
                <td>${r.total_count||r.total_reports||0}</td>
                <td class="text-muted small">${timeAgo(r.last_reported_at)}</td>
            </tr>`;}).join('')
            :'<tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data</td></tr>';
        buildPagination('riskPagination',total,page,25,loadRisk);
    }catch(e){document.getElementById('riskTable').innerHTML=`<tr><td colspan="7" class="text-center text-danger py-3">${e.message}</td></tr>`;}
}

// ── Search Logs ────────────────────────────────────────────────
async function loadSearchLogs(page=1){
    searchLogPage=page;
    const q=document.getElementById('searchLogQuery').value;
    const hr=document.getElementById('searchHasResult').value;
    const p=new URLSearchParams({page,per_page:30});
    if(q)p.set('query',q); if(hr!=='')p.set('has_result',hr);
    document.getElementById('searchLogsTable').innerHTML='<tr><td colspan="7" class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-danger"></div></td></tr>';
    try{
        const {data:logs,pagination}=await api('GET','/admin/search-logs?'+p);
        const total=pagination?.total||0;
        document.getElementById('searchLogTotalBadge').textContent=total;
        document.getElementById('searchLogsPaginationInfo').textContent=`${logs.length} dari ${total}`;
        document.getElementById('searchLogsTable').innerHTML=logs.length
            ?logs.map(l=>`<tr>
                <td><code style="color:#f472b6;font-size:.82rem">${l.query}</code></td>
                <td><code style="color:#94a3b8;font-size:.78rem">${l.query_normalized||l.query}</code></td>
                <td class="text-muted small">${l.category||'—'}</td>
                <td class="text-muted small">${l.results_count||0}</td>
                <td>${l.has_result?'<span class="badge badge-approved">Ya</span>':'<span class="badge badge-rejected">Tidak</span>'}</td>
                <td class="text-muted small" style="font-family:'JetBrains Mono',monospace;font-size:.78rem">${l.ip_address||'—'}</td>
                <td class="text-muted small">${timeAgo(l.created_at)}</td>
            </tr>`).join('')
            :'<tr><td colspan="7" class="text-center text-muted py-4">Tidak ada log</td></tr>';
        buildPagination('searchLogsPagination',total,page,30,loadSearchLogs);
    }catch(e){document.getElementById('searchLogsTable').innerHTML=`<tr><td colspan="7" class="text-center text-danger py-3">${e.message}</td></tr>`;}
}

// ── Modules ────────────────────────────────────────────────────
async function loadModules(){
    document.getElementById('moduleCards').innerHTML='<div class="col-12 text-center py-5"><div class="spinner-border text-danger"></div></div>';
    try{
        const {data:{modules:mods}}=await api('GET','/modules');
        document.getElementById('moduleCards').innerHTML=mods?.length
            ?mods.map(m=>`<div class="col-md-6 col-lg-4"><div class="card-dark p-3 h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div><div class="fw-semibold">${m.name}</div><div class="text-muted" style="font-size:.75rem">v${m.version||'1.0'}${m.is_core?' · <span style="color:#facc15">Core</span>':''}</div></div>
                    <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" ${m.is_enabled?'checked':''} ${m.is_core?'disabled':''} onchange="toggleModule('${m.slug}',this.checked)"></div>
                </div>
                <p class="text-muted mb-0" style="font-size:.82rem">${m.description||'—'}</p>
                ${m.is_core?'<div class="mt-2"><span class="badge" style="background:rgba(250,204,21,.15);color:#fbbf24;font-size:.7rem">Core</span></div>':''}
            </div></div>`).join('')
            :'<div class="col-12"><div class="alert alert-secondary">Tidak ada modul. Klik "Scan Modul Baru".</div></div>';
    }catch(e){document.getElementById('moduleCards').innerHTML=`<div class="col-12"><div class="alert alert-danger">${e.message}</div></div>`;}
}
async function toggleModule(slug,enable){
    try{await api('POST',`/modules/${slug}/${enable?'enable':'disable'}`);toast(`Modul ${slug} ${enable?'diaktifkan':'dinonaktifkan'}`,'success');}
    catch(e){toast(e.message,'error');loadModules();}
}
async function discoverModules(){
    try{await api('GET','/modules');toast('Scan selesai','success');loadModules();}
    catch(e){toast(e.message,'error');}
}

// ── Settings ───────────────────────────────────────────────────
let settingsData={};
async function loadSettings(){
    document.getElementById('settingsForm').innerHTML='<div class="text-center py-4"><div class="spinner-border text-danger"></div></div>';
    try{
        const {data:{settings}}=await api('GET','/admin/settings');
        settingsData=settings||{};
        const groupOrder=['general','reports','risk','security','api'];
        const groupLabels={general:'Umum',reports:'Laporan',risk:'Risiko',security:'Keamanan',api:'API'};
        const groups={};
        Object.entries(settingsData).forEach(([key,cfg])=>{const g=cfg.group||'general';if(!groups[g])groups[g]=[];groups[g].push({key,...cfg});});
        let html='';
        groupOrder.forEach(grp=>{
            if(!groups[grp])return;
            html+=`<div class="mb-4"><h6 class="text-muted mb-3" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.08em;border-bottom:1px solid #334155;padding-bottom:.5rem">${groupLabels[grp]||grp}</h6>`;
            groups[grp].forEach(({key,value,type,label,description})=>{
                if(type==='boolean'){
                    html+=`<div class="d-flex justify-content-between align-items-center mb-3"><div><div style="font-size:.86rem;font-weight:500">${label||key}</div>${description?`<div class="text-muted" style="font-size:.78rem">${description}</div>`:''}</div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="s_${key}" ${value?'checked':''} onchange="settingsData['${key}'].value=this.checked"></div></div>`;
                }else{
                    html+=`<div class="mb-3"><label class="form-label" for="s_${key}">${label||key}</label>${description?`<div class="text-muted mb-1" style="font-size:.78rem">${description}</div>`:''}<input type="${type==='integer'?'number':'text'}" id="s_${key}" class="form-control" value="${value??''}" oninput="settingsData['${key}'].value=this.value"></div>`;
                }
            });
            html+='</div>';
        });
        html+=`<button class="btn btn-danger" onclick="saveSettings()"><i class="bi bi-save me-1"></i>Simpan Pengaturan</button>`;
        document.getElementById('settingsForm').innerHTML=html;
        document.getElementById('sysInfo').innerHTML=`
            <div class="mb-2 d-flex justify-content-between border-bottom pb-2" style="border-color:#334155!important;font-size:.83rem"><span class="text-muted">Versi Platform</span><span>1.0.0</span></div>
            <div class="mb-2 d-flex justify-content-between border-bottom pb-2" style="border-color:#334155!important;font-size:.83rem"><span class="text-muted">Database</span><span>MySQL</span></div>
            <div class="d-flex justify-content-between" style="font-size:.83rem"><span class="text-muted">Role Anda</span><span>${me?.role||'—'}</span></div>`;
    }catch(e){document.getElementById('settingsForm').innerHTML=`<div class="alert alert-danger">${e.message}</div>`;}
}
async function saveSettings(){
    const payload={};
    Object.entries(settingsData).forEach(([key,cfg])=>{payload[key]=cfg.value;});
    try{await api('PUT','/admin/settings',payload);toast('Pengaturan disimpan','success');}
    catch(e){toast(e.message,'error');}
}

// ── Admins ─────────────────────────────────────────────────────
async function loadAdmins(){
    document.getElementById('adminsTable').innerHTML='<tr><td colspan="6" class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-danger"></div></td></tr>';
    try{
        const {data:{users}}=await api('GET','/admin/users');
        const roleColors={superadmin:'#f87171',admin:'#60a5fa',moderator:'#a3e635'};
        document.getElementById('adminsTable').innerHTML=users?.length
            ?users.map(u=>`<tr>
                <td class="fw-semibold">${u.name}</td>
                <td class="text-muted small">${u.email}</td>
                <td><span class="badge" style="background:rgba(255,255,255,.07);color:${roleColors[u.role]||'#94a3b8'}">${u.role}</span></td>
                <td>${u.is_active?'<span class="badge badge-approved">Aktif</span>':'<span class="badge badge-rejected">Nonaktif</span>'}</td>
                <td class="text-muted small">${u.last_login_at?timeAgo(u.last_login_at):'Belum pernah'}</td>
                <td>${u.role!=='superadmin'?`<button class="btn btn-sm btn-outline-secondary" style="font-size:.75rem" onclick="toggleAdminStatus(${u.id},${u.is_active?0:1},'${u.name}')">${u.is_active?'<i class="bi bi-pause"></i>':'<i class="bi bi-play"></i>'}</button><button class="btn btn-sm btn-outline-danger ms-1" style="font-size:.75rem" onclick="deleteAdmin(${u.id},'${u.name}')"><i class="bi bi-trash"></i></button>`:'<span class="text-muted small">—</span>'}</td>
            </tr>`).join('')
            :'<tr><td colspan="6" class="text-center text-muted py-4">Tidak ada admin</td></tr>';
    }catch(e){document.getElementById('adminsTable').innerHTML=`<tr><td colspan="6" class="text-center text-danger py-3">${e.message}</td></tr>`;}
}
function openAddAdmin(){['newAdminName','newAdminEmail','newAdminPass'].forEach(id=>document.getElementById(id).value='');new bootstrap.Modal('#addAdminModal').show();}
async function submitAddAdmin(){
    const name=document.getElementById('newAdminName').value.trim();
    const email=document.getElementById('newAdminEmail').value.trim();
    const role=document.getElementById('newAdminRole').value;
    const pass=document.getElementById('newAdminPass').value;
    if(!name||!email||!pass){toast('Semua field wajib diisi','error');return;}
    try{await api('POST','/admin/users',{name,email,role,password:pass});bootstrap.Modal.getInstance(document.getElementById('addAdminModal')).hide();toast('Admin ditambahkan','success');loadAdmins();}
    catch(e){toast(e.message,'error');}
}
async function toggleAdminStatus(id,newStatus,name){
    try{await api('PUT','/admin/users/'+id,{is_active:newStatus});toast(`${name} ${newStatus?'diaktifkan':'dinonaktifkan'}`,'success');loadAdmins();}
    catch(e){toast(e.message,'error');}
}
async function deleteAdmin(id,name){
    if(!confirm(`Hapus admin "${name}"?`)) return;
    try{await api('DELETE','/admin/users/'+id);toast('Admin dihapus','success');loadAdmins();}
    catch(e){toast(e.message,'error');}
}

// ── API Keys ───────────────────────────────────────────────────
async function loadApiKeys(){
    document.getElementById('newKeyAlert').style.display='none';
    document.getElementById('apiKeysTable').innerHTML='<tr><td colspan="7" class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-danger"></div></td></tr>';
    try{
        const {data:{api_keys:keys}}=await api('GET','/admin/api-keys');
        document.getElementById('apiKeysTable').innerHTML=keys?.length
            ?keys.map(k=>`<tr>
                <td class="fw-semibold">${k.name}</td>
                <td><code style="font-size:.8rem">${k.key_prefix}</code></td>
                <td class="text-muted small">${Array.isArray(k.permissions)?k.permissions.join(', '):k.permissions||'—'}</td>
                <td class="text-muted small">${k.rate_limit}/mnt</td>
                <td class="text-muted small">${k.usage_count||0}×</td>
                <td>${k.is_active?'<span class="badge badge-approved">Aktif</span>':'<span class="badge badge-rejected">Revoked</span>'}</td>
                <td>${k.is_active?`<button class="btn btn-sm btn-outline-danger" style="font-size:.75rem" onclick="revokeApiKey(${k.id},'${k.name}')"><i class="bi bi-x-circle me-1"></i>Revoke</button>`:'—'}</td>
            </tr>`).join('')
            :'<tr><td colspan="7" class="text-center text-muted py-4">Belum ada API key</td></tr>';
    }catch(e){document.getElementById('apiKeysTable').innerHTML=`<tr><td colspan="7" class="text-center text-danger py-3">${e.message}</td></tr>`;}
}
function openAddApiKey(){document.getElementById('newKeyName').value='';document.getElementById('newKeyRateLimit').value='60';new bootstrap.Modal('#addApiKeyModal').show();}
async function submitAddApiKey(){
    const name=document.getElementById('newKeyName').value.trim();
    if(!name){toast('Nama wajib diisi','error');return;}
    try{
        const d=await api('POST','/admin/api-keys',{name,rate_limit:+document.getElementById('newKeyRateLimit').value,env:document.getElementById('newKeyEnv').value,expires_at:document.getElementById('newKeyExpiry').value||null});
        bootstrap.Modal.getInstance(document.getElementById('addApiKeyModal')).hide();
        document.getElementById('newKeyValue').textContent=d.data.key;
        document.getElementById('newKeyAlert').style.display='block';
        toast('API key dibuat. Simpan sekarang!','success'); loadApiKeys();
    }catch(e){toast(e.message,'error');}
}
function copyNewKey(){navigator.clipboard.writeText(document.getElementById('newKeyValue').textContent).then(()=>toast('Key disalin','success'));}
async function revokeApiKey(id,name){
    if(!confirm(`Revoke key "${name}"?`)) return;
    try{await api('DELETE','/admin/api-keys/'+id);toast('Key direvoke','success');loadApiKeys();}
    catch(e){toast(e.message,'error');}
}

// ── Boot ───────────────────────────────────────────────────────
(async()=>{if(token&&await checkAuth())initAdmin();})();
</script>
</body>
</html>
