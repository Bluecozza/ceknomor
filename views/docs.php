<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dokumentasi API — cek.resource.my.id</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark:    #0f172a;
            --bg-card:    #1e293b;
            --bg-code:    #0d1117;
            --accent-red: #e63946;
            --accent-blue:#3b82f6;
            --text-muted: #94a3b8;
            --border:     #334155;
        }

        body {
            background: var(--bg-dark);
            color: #e2e8f0;
            font-family: 'Inter', sans-serif;
            font-size: 15px;
        }

        /* ── Sidebar ── */
        .sidebar {
            position: fixed;
            top: 0; left: 0; bottom: 0;
            width: 260px;
            background: var(--bg-card);
            border-right: 1px solid var(--border);
            overflow-y: auto;
            z-index: 100;
            padding: 1.5rem 0;
        }
        .sidebar-brand {
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: 1rem;
        }
        .sidebar-brand a {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: #fff;
            text-decoration: none;
        }
        .sidebar-brand span { color: var(--accent-red); }
        .nav-section {
            padding: 0.5rem 1.5rem;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--text-muted);
            margin-top: .75rem;
        }
        .sidebar .nav-link {
            padding: .35rem 1.5rem;
            color: #cbd5e1;
            font-size: .875rem;
            border-left: 3px solid transparent;
            transition: all .15s;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background: rgba(255,255,255,.04);
            border-left-color: var(--accent-red);
        }

        /* ── Main Content ── */
        .main-content {
            margin-left: 260px;
            padding: 2rem 3rem;
            max-width: 900px;
        }

        h1, h2, h3 {
            font-family: 'Space Grotesk', sans-serif;
        }
        h2 { margin-top: 3rem; padding-top: 1rem; border-top: 1px solid var(--border); }
        h3 { margin-top: 2rem; color: #f1f5f9; }

        /* ── Code blocks ── */
        pre, code {
            font-family: 'JetBrains Mono', monospace;
        }
        pre {
            background: var(--bg-code);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.25rem;
            overflow-x: auto;
            font-size: .8rem;
            line-height: 1.6;
        }
        code:not(pre code) {
            background: rgba(255,255,255,.08);
            padding: .15rem .4rem;
            border-radius: 4px;
            font-size: .82em;
            color: #f472b6;
        }

        /* ── Method badges ── */
        .method {
            display: inline-block;
            padding: .2rem .6rem;
            border-radius: 5px;
            font-family: 'JetBrains Mono', monospace;
            font-size: .75rem;
            font-weight: 600;
            margin-right: .5rem;
        }
        .method-get    { background: rgba(59,130,246,.2); color:#60a5fa; }
        .method-post   { background: rgba(34,197,94,.2);  color:#4ade80; }
        .method-put    { background: rgba(245,158,11,.2); color:#fbbf24; }
        .method-delete { background: rgba(239,68,68,.2);  color:#f87171; }

        /* ── Endpoint card ── */
        .endpoint-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
        }
        .endpoint-path {
            font-family: 'JetBrains Mono', monospace;
            font-size: .9rem;
            color: #e2e8f0;
        }

        /* ── Parameter table ── */
        .param-table { font-size: .85rem; }
        .param-table th { color: var(--text-muted); font-weight: 600; }
        .param-table td, .param-table th {
            border-color: var(--border) !important;
            padding: .5rem .75rem;
        }
        .badge-required { background: rgba(230,57,70,.2); color: #f87171; font-size:.7rem; }
        .badge-optional { background: rgba(148,163,184,.1); color:#94a3b8; font-size:.7rem; }

        /* ── Risk level badges ── */
        .risk-unknown  { color: #94a3b8; }
        .risk-safe     { color: #4ade80; }
        .risk-low      { color: #a3e635; }
        .risk-medium   { color: #facc15; }
        .risk-high     { color: #fb923c; }
        .risk-critical { color: #f87171; }

        /* ── Alert info ── */
        .alert-info-dark {
            background: rgba(59,130,246,.1);
            border: 1px solid rgba(59,130,246,.3);
            border-radius: 8px;
            padding: 1rem 1.25rem;
            color: #93c5fd;
            font-size: .875rem;
        }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; padding: 1.5rem; }
        }
    </style>
</head>
<body>

<!-- ═══════════════════════════════════════
     SIDEBAR NAVIGASI
════════════════════════════════════════ -->
<nav class="sidebar">
    <div class="sidebar-brand">
        <a href="/"><span>cek</span>.resource.my.id</a>
        <div class="text-muted mt-1" style="font-size:.75rem">Dokumentasi API v1</div>
    </div>

    <div class="nav-section">Pengantar</div>
    <a href="#intro"        class="nav-link">Pendahuluan</a>
    <a href="#auth"         class="nav-link">Autentikasi</a>
    <a href="#errors"       class="nav-link">Error Handling</a>
    <a href="#rate-limit"   class="nav-link">Rate Limiting</a>

    <div class="nav-section">Endpoint Publik</div>
    <a href="#search"       class="nav-link">Pencarian Data</a>
    <a href="#reports-list" class="nav-link">Daftar Laporan</a>
    <a href="#reports-get"  class="nav-link">Detail Laporan</a>
    <a href="#reports-post" class="nav-link">Buat Laporan</a>
    <a href="#categories"   class="nav-link">Kategori</a>

    <div class="nav-section">Endpoint Admin</div>
    <a href="#auth-login"   class="nav-link">Login Admin</a>
    <a href="#admin-stats"  class="nav-link">Statistik</a>
    <a href="#admin-reports"class="nav-link">Moderasi Laporan</a>
    <a href="#modules"      class="nav-link">Manajemen Modul</a>
    <a href="#settings"     class="nav-link">Pengaturan</a>
    <a href="#api-keys"     class="nav-link">API Keys</a>

    <div class="nav-section">Referensi</div>
    <a href="#risk-levels"  class="nav-link">Level Risiko</a>
    <a href="#categories-ref" class="nav-link">Kategori Data</a>
    <a href="#report-types" class="nav-link">Jenis Laporan</a>
    <a href="#response-format" class="nav-link">Format Response</a>
</nav>

<!-- ═══════════════════════════════════════
     KONTEN UTAMA
════════════════════════════════════════ -->
<main class="main-content">

    <!-- HEADER -->
    <div style="padding-top:1rem">
        <div class="text-muted small mb-2">cek.resource.my.id</div>
        <h1 class="mb-1">Dokumentasi API</h1>
        <p class="text-muted">Versi <strong style="color:#60a5fa">v1</strong> · Base URL: <code>https://cek.resource.my.id/api/v1</code></p>
    </div>

    <!-- ─── PENDAHULUAN ─── -->
    <section id="intro">
        <h2>Pendahuluan</h2>
        <p>API ini memungkinkan aplikasi pihak ketiga (Android, iOS, web) untuk mengakses database laporan penipuan, melakukan pencarian, dan mengirim laporan baru.</p>

        <div class="alert-info-dark mb-3">
            <i class="bi bi-info-circle me-2"></i>
            Semua request dan response menggunakan format <strong>JSON</strong>. Pastikan header
            <code>Content-Type: application/json</code> disertakan pada setiap request POST/PUT.
        </div>

        <h3>Base URL</h3>
        <pre>https://cek.resource.my.id/api/v1</pre>

        <h3>Format Response Umum</h3>
        <pre>{
  "success": true,
  "data": { ... },
  "message": "Pesan opsional",
  "timestamp": "2025-01-15T10:30:00+07:00"
}</pre>
    </section>

    <!-- ─── AUTENTIKASI ─── -->
    <section id="auth">
        <h2>Autentikasi</h2>
        <p>Endpoint publik tidak memerlukan autentikasi. Endpoint admin menggunakan <strong>Bearer JWT Token</strong>.</p>

        <pre>Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...</pre>

        <p>Untuk aplikasi mobile/klien pihak ketiga, gunakan <strong>API Key</strong> di header:</p>
        <pre>X-API-Key: ck_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxx</pre>
    </section>

    <!-- ─── ERROR HANDLING ─── -->
    <section id="errors">
        <h2>Error Handling</h2>
        <table class="table param-table">
            <thead><tr><th>HTTP Code</th><th>Arti</th></tr></thead>
            <tbody>
                <tr><td><code>200</code></td><td>Success</td></tr>
                <tr><td><code>201</code></td><td>Created — resource berhasil dibuat</td></tr>
                <tr><td><code>400</code></td><td>Bad Request — parameter tidak valid</td></tr>
                <tr><td><code>401</code></td><td>Unauthorized — token tidak ada atau tidak valid</td></tr>
                <tr><td><code>403</code></td><td>Forbidden — tidak punya izin</td></tr>
                <tr><td><code>404</code></td><td>Not Found — resource tidak ditemukan</td></tr>
                <tr><td><code>422</code></td><td>Unprocessable — validasi gagal</td></tr>
                <tr><td><code>429</code></td><td>Too Many Requests — rate limit terlampaui</td></tr>
                <tr><td><code>500</code></td><td>Server Error</td></tr>
            </tbody>
        </table>

        <h3>Contoh Error Response</h3>
        <pre>{
  "success": false,
  "message": "Validasi gagal",
  "errors": {
    "reported_value": "Nomor telepon tidak valid",
    "title": "Judul minimal 10 karakter"
  }
}</pre>
    </section>

    <!-- ─── RATE LIMIT ─── -->
    <section id="rate-limit">
        <h2>Rate Limiting</h2>
        <table class="table param-table">
            <thead><tr><th>Endpoint</th><th>Limit</th><th>Window</th></tr></thead>
            <tbody>
                <tr><td>GET /search</td><td>30 request</td><td>Per menit, per IP</td></tr>
                <tr><td>POST /reports</td><td>5 request</td><td>Per jam, per IP</td></tr>
                <tr><td>POST /auth/login</td><td>5 request</td><td>Per 5 menit, per IP</td></tr>
                <tr><td>Lainnya (publik)</td><td>100 request</td><td>Per menit, per IP</td></tr>
            </tbody>
        </table>
        <p class="text-muted small">Saat limit terlampaui, API mengembalikan HTTP 429 dengan header <code>Retry-After</code>.</p>
    </section>

    <!-- ═══════════════════════
         ENDPOINT PUBLIK
    ════════════════════════ -->

    <!-- PENCARIAN -->
    <section id="search">
        <h2>Pencarian Data</h2>

        <div class="endpoint-card">
            <div class="mb-2">
                <span class="method method-get">GET</span>
                <span class="endpoint-path">/search</span>
                &nbsp;&nbsp;atau&nbsp;&nbsp;
                <span class="method method-post">POST</span>
                <span class="endpoint-path">/search</span>
            </div>
            <p class="text-muted small mb-3">Mencari data berdasarkan nomor telepon, rekening, atau akun dompet digital. Mengembalikan tingkat risiko dan daftar laporan terkait.</p>

            <h6 class="text-muted">Query Parameters (GET) / Body (POST)</h6>
            <table class="table param-table">
                <thead><tr><th>Parameter</th><th>Tipe</th><th>Keterangan</th></tr></thead>
                <tbody>
                    <tr>
                        <td><code>q</code> <span class="badge badge-required ms-1">required</span></td>
                        <td>string</td>
                        <td>Data yang dicari (nomor HP, rekening, dll)</td>
                    </tr>
                    <tr>
                        <td><code>category</code> <span class="badge badge-optional ms-1">optional</span></td>
                        <td>string</td>
                        <td>Slug kategori: <code>phone</code>, <code>bank_account</code>, <code>dana</code>, dll</td>
                    </tr>
                </tbody>
            </table>

            <h6 class="text-muted">Contoh Request</h6>
            <pre>GET /api/v1/search?q=08123456789&category=phone</pre>

            <h6 class="text-muted">Contoh Response (Ada Data)</h6>
            <pre>{
  "success": true,
  "data": {
    "has_data": true,
    "count": 3,
    "query": "08123456789",
    "normalized": "08123456789",
    "risk": {
      "score": 75.5,
      "level": "high",
      "approved_count": 3,
      "total_count": 4
    },
    "reports": [
      {
        "ulid": "01HXXXXXXXXXXXXXXXXXXXXX",
        "title": "Penipuan jual beli online",
        "report_type": "online_fraud",
        "category": "phone",
        "severity": 3,
        "status": "approved",
        "created_at": "2025-01-10T08:00:00+07:00"
      }
    ]
  }
}</pre>

            <h6 class="text-muted">Contoh Response (Tidak Ada Data)</h6>
            <pre>{
  "success": true,
  "data": {
    "has_data": false,
    "count": 0,
    "query": "08999999999",
    "normalized": "08999999999",
    "risk": null,
    "reports": []
  }
}</pre>
        </div>
    </section>

    <!-- DAFTAR LAPORAN -->
    <section id="reports-list">
        <h2>Daftar Laporan</h2>

        <div class="endpoint-card">
            <div class="mb-2">
                <span class="method method-get">GET</span>
                <span class="endpoint-path">/reports</span>
            </div>
            <p class="text-muted small mb-3">Mengambil daftar laporan yang sudah disetujui (status: approved).</p>

            <h6 class="text-muted">Query Parameters</h6>
            <table class="table param-table">
                <thead><tr><th>Parameter</th><th>Default</th><th>Keterangan</th></tr></thead>
                <tbody>
                    <tr><td><code>page</code></td><td>1</td><td>Halaman</td></tr>
                    <tr><td><code>per_page</code></td><td>15</td><td>Jumlah per halaman (max 50)</td></tr>
                    <tr><td><code>category</code></td><td>—</td><td>Filter slug kategori</td></tr>
                    <tr><td><code>type</code></td><td>—</td><td>Filter slug jenis laporan</td></tr>
                    <tr><td><code>sort</code></td><td>newest</td><td><code>newest</code> | <code>oldest</code> | <code>highest_risk</code></td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- DETAIL LAPORAN -->
    <section id="reports-get">
        <h2>Detail Laporan</h2>

        <div class="endpoint-card">
            <div class="mb-2">
                <span class="method method-get">GET</span>
                <span class="endpoint-path">/reports/{ulid}</span>
            </div>
            <p class="text-muted small mb-3">Mengambil detail lengkap satu laporan berdasarkan ULID-nya.</p>

            <h6 class="text-muted">Contoh Response</h6>
            <pre>{
  "success": true,
  "data": {
    "report": {
      "ulid": "01HXXXXXXXXXXXXXXXXXXXXX",
      "title": "Penipuan jual beli online",
      "description": "Saya mentransfer uang ...",
      "reported_value": "08123456789",
      "reported_value_masked": "0812*****89",
      "category": { "name": "Nomor Telepon", "slug": "phone", "icon": "telephone" },
      "report_type": { "name": "Penipuan Online", "slug": "online_fraud", "severity": 3 },
      "bank_name": null,
      "account_name": null,
      "incident_date": "2025-01-08",
      "amount_lost": 500000,
      "evidence_urls": [],
      "reporter_name": "A***",
      "status": "approved",
      "risk": { "score": 75.5, "level": "high" },
      "view_count": 42,
      "helpful_count": 8,
      "created_at": "2025-01-10T08:00:00+07:00"
    }
  }
}</pre>
        </div>
    </section>

    <!-- BUAT LAPORAN -->
    <section id="reports-post">
        <h2>Buat Laporan Baru</h2>

        <div class="endpoint-card">
            <div class="mb-2">
                <span class="method method-post">POST</span>
                <span class="endpoint-path">/reports</span>
            </div>
            <p class="text-muted small mb-3">Mengirim laporan baru. Tidak memerlukan autentikasi, namun identitas pelapor harus diisi. Laporan akan masuk status <code>pending</code> hingga disetujui admin.</p>

            <div class="alert-info-dark mb-3">
                <i class="bi bi-paperclip me-1"></i>
                Untuk upload bukti gambar, gunakan <code>multipart/form-data</code>. Field file bernama <code>evidence[]</code> (maks 5 file, maks 5MB/file, format: jpg/png/gif/webp).
            </div>

            <h6 class="text-muted">Body Parameters</h6>
            <table class="table param-table">
                <thead><tr><th>Field</th><th>Tipe</th><th>Keterangan</th></tr></thead>
                <tbody>
                    <tr><td><code>category_id</code> <span class="badge badge-required ms-1">req</span></td><td>integer</td><td>ID kategori</td></tr>
                    <tr><td><code>report_type_id</code> <span class="badge badge-required ms-1">req</span></td><td>integer</td><td>ID jenis laporan</td></tr>
                    <tr><td><code>reported_value</code> <span class="badge badge-required ms-1">req</span></td><td>string</td><td>Nomor HP/rekening/dll yang dilaporkan</td></tr>
                    <tr><td><code>title</code> <span class="badge badge-required ms-1">req</span></td><td>string</td><td>Judul laporan (10–200 karakter)</td></tr>
                    <tr><td><code>description</code> <span class="badge badge-required ms-1">req</span></td><td>string</td><td>Deskripsi kejadian (30–5000 karakter)</td></tr>
                    <tr><td><code>bank_name</code> <span class="badge badge-optional ms-1">opt</span></td><td>string</td><td>Nama bank (jika kategori rekening)</td></tr>
                    <tr><td><code>account_name</code> <span class="badge badge-optional ms-1">opt</span></td><td>string</td><td>Nama pemilik rekening</td></tr>
                    <tr><td><code>incident_date</code> <span class="badge badge-optional ms-1">opt</span></td><td>date</td><td>Tanggal kejadian (YYYY-MM-DD)</td></tr>
                    <tr><td><code>amount_lost</code> <span class="badge badge-optional ms-1">opt</span></td><td>integer</td><td>Jumlah kerugian (Rupiah)</td></tr>
                    <tr><td><code>reporter_name</code> <span class="badge badge-required ms-1">req</span></td><td>string</td><td>Nama pelapor</td></tr>
                    <tr><td><code>reporter_contact</code> <span class="badge badge-required ms-1">req</span></td><td>string</td><td>Kontak pelapor (HP/email)</td></tr>
                    <tr><td><code>reporter_contact_type</code> <span class="badge badge-required ms-1">req</span></td><td>string</td><td><code>phone</code> atau <code>email</code></td></tr>
                    <tr><td><code>evidence[]</code> <span class="badge badge-optional ms-1">opt</span></td><td>file</td><td>Bukti gambar (multipart)</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- KATEGORI -->
    <section id="categories">
        <h2>Kategori</h2>

        <div class="endpoint-card">
            <div class="mb-2">
                <span class="method method-get">GET</span>
                <span class="endpoint-path">/categories</span>
            </div>
            <p class="text-muted small">Daftar semua kategori yang aktif.</p>
        </div>

        <div class="endpoint-card">
            <div class="mb-2">
                <span class="method method-get">GET</span>
                <span class="endpoint-path">/categories/report-types</span>
            </div>
            <p class="text-muted small">Daftar semua jenis laporan.</p>
        </div>
    </section>

    <!-- ═══════════════════════
         ENDPOINT ADMIN
    ════════════════════════ -->

    <!-- LOGIN -->
    <section id="auth-login">
        <h2>Login Admin</h2>

        <div class="endpoint-card">
            <div class="mb-2">
                <span class="method method-post">POST</span>
                <span class="endpoint-path">/auth/login</span>
            </div>
            <pre>{
  "email": "admin@cek.resource.my.id",
  "password": "Admin@1234"
}</pre>
            <h6 class="text-muted mt-3">Response</h6>
            <pre>{
  "success": true,
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expires_in": 86400,
    "admin": { "id": 1, "name": "Admin", "role": "superadmin" }
  }
}</pre>
        </div>
    </section>

    <!-- STATS -->
    <section id="admin-stats">
        <h2>Statistik Dashboard</h2>

        <div class="endpoint-card">
            <div class="mb-2">
                <span class="method method-get">GET</span>
                <span class="endpoint-path">/admin/stats</span>
                &nbsp;<span class="badge" style="background:rgba(250,204,21,.15);color:#fbbf24;font-size:.7rem">Requires Auth</span>
            </div>
            <p class="text-muted small">Statistik lengkap untuk dashboard admin.</p>
        </div>
    </section>

    <!-- MODERASI -->
    <section id="admin-reports">
        <h2>Moderasi Laporan</h2>

        <div class="endpoint-card">
            <div class="mb-2">
                <span class="method method-get">GET</span>
                <span class="endpoint-path">/admin/reports</span>
                &nbsp;<span class="badge" style="background:rgba(250,204,21,.15);color:#fbbf24;font-size:.7rem">Requires Auth</span>
            </div>
            <p class="text-muted small mb-3">Daftar semua laporan (termasuk pending) dengan filter.</p>
            <p class="text-muted small"><strong>Query params:</strong> <code>status</code>, <code>category</code>, <code>search</code>, <code>page</code>, <code>per_page</code></p>
        </div>

        <div class="endpoint-card">
            <div class="mb-2">
                <span class="method method-post">POST</span>
                <span class="endpoint-path">/admin/reports/{id}/moderate</span>
                &nbsp;<span class="badge" style="background:rgba(250,204,21,.15);color:#fbbf24;font-size:.7rem">Requires Auth</span>
            </div>
            <p class="text-muted small mb-3">Approve, reject, atau flag laporan.</p>
            <pre>{ "action": "approve", "note": "Laporan valid" }</pre>
        </div>

        <div class="endpoint-card">
            <div class="mb-2">
                <span class="method method-post">POST</span>
                <span class="endpoint-path">/admin/reports/bulk</span>
                &nbsp;<span class="badge" style="background:rgba(250,204,21,.15);color:#fbbf24;font-size:.7rem">Requires Auth</span>
            </div>
            <p class="text-muted small mb-3">Moderasi massal (maks 50 laporan).</p>
            <pre>{ "ids": [1, 2, 3], "action": "approve", "note": "" }</pre>
        </div>
    </section>

    <!-- MODUL -->
    <section id="modules">
        <h2>Manajemen Modul</h2>

        <div class="endpoint-card">
            <div class="mb-2">
                <span class="method method-get">GET</span>
                <span class="endpoint-path">/modules</span>
            </div>
            <div class="mb-2">
                <span class="method method-post">POST</span>
                <span class="endpoint-path">/modules/{slug}/enable</span>
            </div>
            <div class="mb-2">
                <span class="method method-post">POST</span>
                <span class="endpoint-path">/modules/{slug}/disable</span>
            </div>
            <div class="mb-0">
                <span class="method method-put">PUT</span>
                <span class="endpoint-path">/modules/{slug}/config</span>
            </div>
        </div>
    </section>

    <!-- SETTINGS -->
    <section id="settings">
        <h2>Pengaturan Sistem</h2>

        <div class="endpoint-card">
            <div class="mb-2">
                <span class="method method-get">GET</span>
                <span class="endpoint-path">/admin/settings</span>
            </div>
            <div class="mb-0">
                <span class="method method-put">PUT</span>
                <span class="endpoint-path">/admin/settings</span>
            </div>
        </div>
    </section>

    <!-- API KEYS -->
    <section id="api-keys">
        <h2>API Keys</h2>

        <div class="endpoint-card">
            <div class="mb-2">
                <span class="method method-get">GET</span>
                <span class="endpoint-path">/admin/api-keys</span>
                &nbsp;— Daftar semua API key
            </div>
            <div class="mb-2">
                <span class="method method-post">POST</span>
                <span class="endpoint-path">/admin/api-keys</span>
                &nbsp;— Buat API key baru
            </div>
            <div class="mb-0">
                <span class="method method-delete">DELETE</span>
                <span class="endpoint-path">/admin/api-keys/{id}</span>
                &nbsp;— Revoke API key
            </div>
        </div>
    </section>

    <!-- ═══════════════════════
         REFERENSI
    ════════════════════════ -->

    <!-- RISK LEVELS -->
    <section id="risk-levels">
        <h2>Level Risiko</h2>
        <table class="table param-table">
            <thead><tr><th>Level</th><th>Skor</th><th>Laporan Disetujui</th><th>Keterangan</th></tr></thead>
            <tbody>
                <tr><td><span class="risk-unknown fw-bold">unknown</span></td><td>0</td><td>0</td><td>Belum ada laporan</td></tr>
                <tr><td><span class="risk-safe fw-bold">safe</span></td><td>1–15</td><td>1</td><td>Laporan sangat sedikit, kemungkinan aman</td></tr>
                <tr><td><span class="risk-low fw-bold">low</span></td><td>16–35</td><td>1–2</td><td>Risiko rendah</td></tr>
                <tr><td><span class="risk-medium fw-bold">medium</span></td><td>36–60</td><td>2–4</td><td>Perlu waspada</td></tr>
                <tr><td><span class="risk-high fw-bold">high</span></td><td>61–80</td><td>4–9</td><td>Risiko tinggi, hindari transaksi</td></tr>
                <tr><td><span class="risk-critical fw-bold">critical</span></td><td>81–100</td><td>10+</td><td>Sangat berbahaya, banyak korban</td></tr>
            </tbody>
        </table>
    </section>

    <!-- KATEGORI REF -->
    <section id="categories-ref">
        <h2>Kategori Data</h2>
        <table class="table param-table">
            <thead><tr><th>Slug</th><th>Nama</th><th>Contoh Format</th></tr></thead>
            <tbody>
                <tr><td><code>phone</code></td><td>Nomor Telepon</td><td>08xxxxxxxxxx</td></tr>
                <tr><td><code>bank_account</code></td><td>Rekening Bank</td><td>1234567890 (BCA)</td></tr>
                <tr><td><code>dana</code></td><td>DANA</td><td>08xxxxxxxxxx</td></tr>
                <tr><td><code>ovo</code></td><td>OVO</td><td>08xxxxxxxxxx</td></tr>
                <tr><td><code>gopay</code></td><td>GoPay</td><td>08xxxxxxxxxx</td></tr>
                <tr><td><code>shopeepay</code></td><td>ShopeePay</td><td>08xxxxxxxxxx</td></tr>
                <tr><td><code>linkaja</code></td><td>LinkAja</td><td>08xxxxxxxxxx</td></tr>
                <tr><td><code>email</code></td><td>Email</td><td>user@example.com</td></tr>
                <tr><td><code>social</code></td><td>Akun Media Sosial</td><td>@username</td></tr>
                <tr><td><code>other</code></td><td>Lainnya</td><td>—</td></tr>
            </tbody>
        </table>
    </section>

    <!-- REPORT TYPES -->
    <section id="report-types">
        <h2>Jenis Laporan</h2>
        <table class="table param-table">
            <thead><tr><th>Slug</th><th>Nama</th><th>Severity</th></tr></thead>
            <tbody>
                <tr><td><code>online_fraud</code></td><td>Penipuan Online</td><td>3</td></tr>
                <tr><td><code>fake_seller</code></td><td>Penjual Palsu</td><td>3</td></tr>
                <tr><td><code>fake_investment</code></td><td>Investasi Bodong</td><td>4</td></tr>
                <tr><td><code>illegal_loan</code></td><td>Pinjol Ilegal</td><td>4</td></tr>
                <tr><td><code>blackmail</code></td><td>Pemerasan / Ancaman</td><td>4</td></tr>
                <tr><td><code>gambling</code></td><td>Judi Online</td><td>2</td></tr>
                <tr><td><code>spam</code></td><td>Spam / Iklan Masif</td><td>1</td></tr>
                <tr><td><code>prize_scam</code></td><td>Penipuan Hadiah</td><td>3</td></tr>
                <tr><td><code>love_scam</code></td><td>Love Scam / Romance</td><td>3</td></tr>
                <tr><td><code>other</code></td><td>Lainnya</td><td>2</td></tr>
            </tbody>
        </table>
    </section>

    <!-- FORMAT RESPONSE -->
    <section id="response-format">
        <h2>Format Response Paginated</h2>
        <pre>{
  "success": true,
  "data": [ ... ],
  "pagination": {
    "total": 100,
    "per_page": 15,
    "current_page": 1,
    "last_page": 7,
    "from": 1,
    "to": 15
  }
}</pre>

        <p class="text-muted mt-4 small">
            Versi API: <strong>1.0.0</strong> &middot;
            Terakhir diperbarui: Januari 2025 &middot;
            Kontak: <a href="mailto:dev@cek.resource.my.id" class="text-muted">dev@cek.resource.my.id</a>
        </p>
    </section>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Highlight active nav link berdasarkan scroll
    const sections = document.querySelectorAll('section[id]');
    const navLinks  = document.querySelectorAll('.sidebar .nav-link');

    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                navLinks.forEach(l => l.classList.remove('active'));
                const active = document.querySelector('.sidebar .nav-link[href="#' + entry.target.id + '"]');
                if (active) active.classList.add('active');
            }
        });
    }, { rootMargin: '-20% 0px -70% 0px' });

    sections.forEach(s => observer.observe(s));
</script>
</body>
</html>
