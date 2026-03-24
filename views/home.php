<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Database laporan penipuan online. Cek nomor telepon, rekening bank, dan akun keuangan bermasalah.">
    <title>Cek Resource – Database Laporan Penipuan</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary:       #1a1f36;
            --primary-light: #252d4a;
            --accent:        #e63946;
            --accent-light:  #ff6b6b;
            --safe:          #2ec27e;
            --warning-clr:   #e5a50a;
            --danger-clr:    #e63946;
            --text-primary:  #1a1f36;
            --text-muted:    #6c757d;
            --bg-main:       #f5f6fa;
            --bg-card:       #ffffff;
            --border-color:  #e8eaf0;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-main);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Hero Section ───────────────────────────── */
        .hero {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: 
                radial-gradient(ellipse 80% 60% at 20% 30%, rgba(230, 57, 70, 0.12) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 80% 70%, rgba(37, 99, 235, 0.10) 0%, transparent 60%);
        }

        .hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
            width: 100%;
            max-width: 720px;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(230, 57, 70, 0.15);
            border: 1px solid rgba(230, 57, 70, 0.3);
            color: #ff6b6b;
            padding: 6px 16px;
            border-radius: 100px;
            font-size: 0.78rem;
            font-weight: 500;
            letter-spacing: 0.05em;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
        }

        .hero-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(2rem, 6vw, 3.5rem);
            font-weight: 700;
            color: #ffffff;
            line-height: 1.15;
            margin-bottom: 1rem;
        }

        .hero-title span {
            background: linear-gradient(135deg, #e63946, #ff6b6b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            color: rgba(255,255,255,0.6);
            font-size: 1.05rem;
            margin-bottom: 2.5rem;
            font-weight: 300;
            line-height: 1.7;
        }

        /* ── Search Box ──────────────────────────────── */
        .search-container {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 2rem;
            backdrop-filter: blur(10px);
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
        }

        .search-group {
            display: flex;
            gap: 0;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .search-input {
            flex: 1;
            border: none;
            outline: none;
            padding: 1rem 1.25rem;
            font-size: 1.05rem;
            color: var(--text-primary);
            background: transparent;
        }

        .search-input::placeholder {
            color: #a0a8c0;
        }

        .search-btn {
            border: none;
            background: linear-gradient(135deg, #e63946, #c62d39);
            color: #fff;
            padding: 0 2rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            border-radius: 0 12px 12px 0;
            white-space: nowrap;
        }

        .search-btn:hover {
            background: linear-gradient(135deg, #c62d39, #a01e28);
            transform: none;
        }

        .search-btn:active {
            transform: scale(0.98);
        }

        .search-btn .spinner-border {
            width: 1rem;
            height: 1rem;
        }

        .search-hint {
            color: rgba(255,255,255,0.45);
            font-size: 0.82rem;
            margin-top: 0.75rem;
            text-align: left;
        }

        /* Category pills */
        .category-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 1.25rem;
        }

        .cat-pill {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            color: rgba(255,255,255,0.7);
            padding: 4px 14px;
            border-radius: 100px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
            user-select: none;
        }

        .cat-pill:hover, .cat-pill.active {
            background: rgba(230, 57, 70, 0.2);
            border-color: rgba(230, 57, 70, 0.4);
            color: #ff6b6b;
        }

        /* ── Stats Bar ───────────────────────────────── */
        .stats-bar {
            display: flex;
            justify-content: center;
            gap: 2.5rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.08);
            flex-wrap: wrap;
        }

        .stat-item {
            text-align: center;
        }

        .stat-num {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffffff;
        }

        .stat-label {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* ── Footer ──────────────────────────────────── */
        footer {
            background: #0f172a;
            border-top: 1px solid rgba(255,255,255,0.06);
            padding: 1.25rem 2rem;
        }

        .footer-inner {
            max-width: 960px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .footer-brand {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 600;
            color: rgba(255,255,255,0.7);
            font-size: 0.9rem;
            text-decoration: none;
        }

        .footer-links {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .footer-links a {
            color: rgba(255,255,255,0.45);
            text-decoration: none;
            font-size: 0.82rem;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .footer-links a:hover {
            color: rgba(255,255,255,0.85);
        }

        /* ── Modal Styles ────────────────────────────── */
        .modal-content {
            border: none;
            border-radius: 16px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.2);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
        }

        .modal-title {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 600;
        }

        .risk-badge-lg {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            border-radius: 100px;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .risk-meter {
            height: 8px;
            border-radius: 100px;
            background: #e8eaf0;
            overflow: hidden;
            margin: 0.5rem 0;
        }

        .risk-meter-bar {
            height: 100%;
            border-radius: 100px;
            transition: width 0.6s ease;
        }

        /* Report list item */
        .report-item {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .report-item:hover {
            border-color: var(--accent);
            background: #fff8f8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(230,57,70,0.1);
        }

        .severity-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }

        /* Detail view */
        .detail-section {
            background: #f8f9fc;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
        }

        .detail-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--text-muted);
            font-weight: 500;
            margin-bottom: 4px;
        }

        /* Not found state */
        .not-found-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
            font-size: 2rem;
            color: #0284c7;
        }

        /* Loading skeleton */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: skeleton-loading 1.4s ease-in-out infinite;
            border-radius: 6px;
        }

        @keyframes skeleton-loading {
            0%   { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Responsive */
        @media (max-width: 576px) {
            .search-group { flex-direction: column; border-radius: 12px; }
            .search-input  { border-radius: 12px 12px 0 0; border-bottom: 1px solid #eee; }
            .search-btn    { border-radius: 0 0 12px 12px; padding: 0.75rem; justify-content: center; }
            .stats-bar     { gap: 1.5rem; }
            .footer-inner  { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

<!-- ── Hero / Main Section ───────────────────────────────────── -->
<main class="hero">
    <div class="hero-content">

        <!-- Title -->
        <h1 class="hero-title">
            Cek Data Sebelum<br>
            <span>Bertransaksi</span>
        </h1>

        <p class="hero-subtitle">
            Periksa nomor telepon, rekening bank, dan akun dompet digital.<br>
            Cari tahu apakah data tersebut pernah dilaporkan bermasalah.
        </p>

        <!-- Search Box -->
        <div class="search-container">
            <div class="search-group">
                <input
                    type="text"
                    id="searchInput"
                    class="search-input"
                    placeholder="Masukkan nomor HP, rekening, atau email..."
                    autocomplete="off"
                    maxlength="255"
                >
                <button class="search-btn" id="searchBtn" onclick="doSearch()">
                    <span id="searchBtnText"><i class="bi bi-search"></i> Cek Sekarang</span>
                    <span id="searchBtnSpinner" class="d-none">
                        <span class="spinner-border" role="status"></span> Mencari...
                    </span>
                </button>
            </div>

            <!-- Category Filter Pills -->
            <div class="category-pills" id="categoryPills">
                <span class="cat-pill active" data-slug="">
                    <i class="bi bi-grid-fill me-1"></i>Semua
                </span>
                <span class="cat-pill" data-slug="phone">
                    <i class="bi bi-telephone me-1"></i>No. Telepon
                </span>
                <span class="cat-pill" data-slug="bank_account">
                    <i class="bi bi-bank me-1"></i>Rekening Bank
                </span>
                <span class="cat-pill" data-slug="dana">
                    <i class="bi bi-wallet2 me-1"></i>DANA
                </span>
                <span class="cat-pill" data-slug="ovo">
                    <i class="bi bi-wallet me-1"></i>OVO
                </span>
                <span class="cat-pill" data-slug="gopay">
                    <i class="bi bi-phone me-1"></i>GoPay
                </span>
                <span class="cat-pill" data-slug="email">
                    <i class="bi bi-envelope me-1"></i>Email
                </span>
            </div>

            <p class="search-hint">
                <i class="bi bi-info-circle me-1"></i>
                Contoh: <code style="color:rgba(255,255,255,0.6)">081234567890</code> &nbsp;·&nbsp;
                <code style="color:rgba(255,255,255,0.6)">1234567890</code> &nbsp;·&nbsp;
                <code style="color:rgba(255,255,255,0.6)">nama@email.com</code>
            </p>

            <!-- Stats Bar -->
            <div class="stats-bar" id="statsBar">
                <div class="stat-item">
                    <div class="stat-num" id="statReports">—</div>
                    <div class="stat-label">Laporan Aktif</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num" id="statSearches">—</div>
                    <div class="stat-label">Pencarian Hari Ini</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num" id="statDangers">—</div>
                    <div class="stat-label">Data Berbahaya</div>
                </div>
            </div>
        </div>

    </div>
</main>

<!-- ── Footer ────────────────────────────────────────────────── -->
<footer>
    <div class="footer-inner">
        <a href="/" class="footer-brand">
            <i class="bi bi-shield-check me-1" style="color:#e63946"></i>
            Cek Resource
        </a>
        <div class="footer-links">
            <a href="/report"><i class="bi bi-plus-circle"></i> Buat Laporan</a>
            <a href="https://facebook.com/lorddaim"><i class="bi bi-book"></i> Support Me</a>
        </div>
    </div>
</footer>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- MODALS                                                     -->
<!-- ══════════════════════════════════════════════════════════ -->

<!-- Modal: Hasil Pencarian (Single atau Multiple) -->
<div class="modal fade" id="resultModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resultModalTitle">Hasil Pencarian</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="resultModalBody">
                <!-- Diisi oleh JavaScript -->
            </div>
            <div class="modal-footer" id="resultModalFooter">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Detail Laporan -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn btn-link btn-sm p-0 me-2" onclick="backToResults()">
                    <i class="bi bi-arrow-left"></i>
                </button>
                <h5 class="modal-title" id="detailModalTitle">Detail Laporan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailModalBody">
                <!-- Diisi oleh JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                <a href="/report" class="btn btn-danger btn-sm">
                    <i class="bi bi-plus-circle me-1"></i>Buat Laporan Baru
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ── Scripts ───────────────────────────────────────────────── -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script>
/**
 * cek.resource.my.id - Frontend JavaScript
 * Seluruh interaksi menggunakan AJAX ke API terpusat
 */

// ── Konfigurasi ─────────────────────────────────────────────
const API_BASE   = '/api/v1';
let   activeCategory = '';
let   lastSearchResult = null;   // Simpan hasil terakhir untuk navigasi balik

// ── Bootstrap Modal instances ────────────────────────────────
const resultModal = new bootstrap.Modal(document.getElementById('resultModal'));
const detailModal = new bootstrap.Modal(document.getElementById('detailModal'));

// ── Category Pill Handler ────────────────────────────────────
document.querySelectorAll('.cat-pill').forEach(pill => {
    pill.addEventListener('click', () => {
        document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('active'));
        pill.classList.add('active');
        activeCategory = pill.dataset.slug;
    });
});

// ── Search Handler ───────────────────────────────────────────
document.getElementById('searchInput').addEventListener('keypress', e => {
    if (e.key === 'Enter') doSearch();
});

/**
 * Eksekusi pencarian ke API
 */
function doSearch() {
    const query = $('#searchInput').val().trim();

    if (query.length < 3) {
        showToast('Masukkan minimal 3 karakter untuk pencarian', 'warning');
        $('#searchInput').focus();
        return;
    }

    // UI loading state
    setSearchLoading(true);

    $.ajax({
        url:         `${API_BASE}/search`,
        method:      'POST',
        contentType: 'application/json',
        data:        JSON.stringify({ q: query, category: activeCategory }),
        dataType:    'json',
        timeout:     10000,

        success: function(res) {
            setSearchLoading(false);
            if (res.success) {
                handleSearchResult(res.data, query);
            } else {
                showToast(res.message || 'Terjadi kesalahan', 'danger');
            }
        },

        error: function(xhr) {
            // Jika 301 redirect (Apache trailing slash issue), fallback ke GET
            if (xhr.status === 301 || xhr.status === 0) {
                $.ajax({
                    url:      `${API_BASE}/search`,
                    method:   'GET',
                    data:     { q: query, category: activeCategory },
                    dataType: 'json',
                    timeout:  10000,
                    success: function(res) {
                        setSearchLoading(false);
                        if (res.success) handleSearchResult(res.data, query);
                        else showToast(res.message || 'Terjadi kesalahan', 'danger');
                    },
                    error: function(xhr2) {
                        setSearchLoading(false);
                        showToast('Gagal terhubung ke server. Coba lagi.', 'danger');
                    }
                });
                return;
            }
            setSearchLoading(false);
            const msg = xhr.responseJSON?.message || 'Gagal terhubung ke server';
            showToast(msg, 'danger');
        }
    });
}

/**
 * Proses dan tampilkan hasil pencarian
 */
function handleSearchResult(data, query) {
    lastSearchResult = data;

    if (!data.has_data) {
        // ── CASE 3: Tidak ada data ───────────────────────
        showNotFoundModal(query, data.normalized);
        return;
    }

    if (data.count === 1) {
        // ── CASE 1: Satu laporan – langsung tampilkan detail
        loadReportDetail(data.reports[0].ulid);
        return;
    }

    // ── CASE 2: Multiple laporan – tampilkan ringkasan + list
    showMultipleResultsModal(data);
}

// ── Modal Renderers ──────────────────────────────────────────

/**
 * CASE 2: Tampilkan multiple hasil
 */
function showMultipleResultsModal(data) {
    const risk  = data.risk;
    const badge = riskBadgeHtml(risk);

    let html = `
        <!-- Risk Summary -->
        <div class="mb-4">
            <div class="d-flex align-items-start gap-3 mb-3">
                <div class="flex-grow-1">
                    <div class="detail-label">Data yang Dicari</div>
                    <div class="fw-semibold fs-5 font-monospace">${escHtml(data.query)}</div>
                </div>
                <div>${badge}</div>
            </div>

            ${risk ? `
            <div class="detail-section">
                <div class="row g-3 text-center">
                    <div class="col-4">
                        <div class="fs-4 fw-bold text-danger">${risk.total_reports}</div>
                        <div class="small text-muted">Total Laporan</div>
                    </div>
                    <div class="col-4">
                        <div class="fs-4 fw-bold">${risk.approved_reports}</div>
                        <div class="small text-muted">Terverifikasi</div>
                    </div>
                    <div class="col-4">
                        <div class="fs-4 fw-bold">${risk.risk_score.toFixed(0)}<small class="fs-6">/100</small></div>
                        <div class="small text-muted">Skor Risiko</div>
                    </div>
                </div>
                <div class="risk-meter mt-3">
                    <div class="risk-meter-bar bg-${risk.color}" style="width: ${risk.risk_score}%"></div>
                </div>
                ${risk.first_reported ? `
                <div class="small text-muted mt-2">
                    <i class="bi bi-clock me-1"></i>
                    Pertama dilaporkan: ${formatDate(risk.first_reported)} &nbsp;·&nbsp;
                    Terakhir: ${formatDate(risk.last_reported)}
                </div>` : ''}
            </div>` : ''}
        </div>

        <!-- Report List -->
        <div class="detail-label mb-2">
            <i class="bi bi-list-ul me-1"></i>
            ${data.count} Laporan Tersimpan – Klik untuk detail lengkap
        </div>
        <div class="d-flex flex-column gap-2" id="reportList">
    `;

    data.reports.forEach(r => {
        const sev       = severityColor(r.severity);
        const amtStr    = r.amount_lost
            ? `<span class="text-danger small">Kerugian: Rp ${numberFmt(r.amount_lost)}</span>`
            : '';

        html += `
            <div class="report-item" onclick="loadReportDetail('${r.ulid}')">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1 me-2">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="severity-dot" style="background:${sev}"></span>
                            <span class="fw-semibold">${escHtml(r.title)}</span>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span class="badge bg-light text-dark border">
                                <i class="${r.category_icon} me-1"></i>${r.category_name}
                            </span>
                            <span class="badge bg-light text-dark border">${r.report_type_name}</span>
                            ${amtStr}
                        </div>
                    </div>
                    <div class="text-end text-muted small flex-shrink-0">
                        <div>${r.time_ago}</div>
                        <div><i class="bi bi-eye me-1"></i>${r.view_count}</div>
                    </div>
                </div>
                ${r.bank_name || r.account_name ? `
                <div class="mt-2 small text-muted">
                    ${r.bank_name ? `<i class="bi bi-bank me-1"></i>${escHtml(r.bank_name)}` : ''}
                    ${r.account_name ? ` &middot; a.n. ${escHtml(r.account_name)}` : ''}
                </div>` : ''}
                <i class="bi bi-chevron-right text-muted position-absolute end-0 top-50 translate-middle-y me-3"></i>
            </div>
        `;
    });

    html += '</div>';

    $('#resultModalTitle').html(
        `<i class="bi bi-search me-2"></i>Hasil Pencarian <span class="badge bg-danger ms-1">${data.count}</span>`
    );
    $('#resultModalBody').html(html);
    $('#resultModalFooter').html(`
        <a href="/report?q=${encodeURIComponent(data.query)}" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-plus me-1"></i>Tambah Laporan
        </a>
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
    `);

    resultModal.show();
}

/**
 * CASE 3: Data belum ada dalam sistem
 */
function showNotFoundModal(query, normalized) {
    const html = `
        <div class="text-center py-3">
            <div class="not-found-icon">
                <i class="bi bi-shield-check"></i>
            </div>
            <h5 class="fw-semibold mb-2">Belum Ada Laporan</h5>
            <p class="text-muted mb-1">Data berikut belum pernah dilaporkan di sistem kami:</p>
            <div class="bg-light rounded-3 p-3 my-3 font-monospace fw-semibold fs-5">
                ${escHtml(query)}
            </div>
            <p class="text-muted small mb-4">
                Tidak ditemukan bukan berarti aman sepenuhnya.<br>
                Jika Anda menemukan indikasi penipuan, bantu komunitas dengan melaporkannya.
            </p>
            <div class="d-flex gap-2 justify-content-center flex-wrap">
                <a href="/report?q=${encodeURIComponent(query)}" class="btn btn-danger">
                    <i class="bi bi-plus-circle me-2"></i>Laporkan Data Ini
                </a>
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    Cari Data Lain
                </button>
            </div>
        </div>
    `;

    $('#resultModalTitle').html('<i class="bi bi-question-circle me-2"></i>Data Tidak Ditemukan');
    $('#resultModalBody').html(html);
    $('#resultModalFooter').html('');
    resultModal.show();
}

/**
 * Load dan tampilkan detail laporan berdasarkan ULID
 */
function loadReportDetail(ulid) {
    const body = `
        <div class="text-center py-5">
            <div class="spinner-border text-danger mb-3"></div>
            <div class="text-muted">Memuat detail laporan...</div>
        </div>
    `;

    // Tampilkan detail modal
    if (bootstrap.Modal.getInstance(document.getElementById('resultModal'))) {
        resultModal.hide();
    }
    $('#detailModalBody').html(body);
    detailModal.show();

    $.ajax({
        url:     `${API_BASE}/reports/${ulid}`,
        method:  'GET',
        dataType:'json',
        timeout: 10000,

        success: function(res) {
            if (res.success) {
                renderReportDetail(res.data);
            } else {
                $('#detailModalBody').html(`
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>${res.message}
                    </div>
                `);
            }
        },

        error: function() {
            $('#detailModalBody').html(`
                <div class="alert alert-danger">
                    <i class="bi bi-wifi-off me-2"></i>Gagal memuat laporan. Periksa koneksi internet Anda.
                </div>
            `);
        }
    });
}

/**
 * Render HTML detail laporan di dalam modal
 */
function renderReportDetail(r) {
    const badge    = riskBadgeHtml(null, r.severity);
    const amtHtml  = r.amount_lost
        ? `<div class="col-6"><div class="detail-label">Estimasi Kerugian</div>
           <div class="text-danger fw-semibold">Rp ${numberFmt(r.amount_lost)}</div></div>`
        : '';
    const evidenceHtml = r.evidence_urls?.length
        ? `<div class="mb-3">
            <div class="detail-label mb-2"><i class="bi bi-images me-1"></i>Bukti</div>
            <div class="d-flex flex-wrap gap-2">
                ${r.evidence_urls.map(url =>
                    `<a href="${url}" target="_blank">
                        <img src="${url}" alt="Bukti" style="width:80px;height:60px;object-fit:cover;border-radius:8px;border:1px solid #ddd">
                    </a>`
                ).join('')}
            </div>
           </div>`
        : '';

    const html = `
        <!-- Header -->
        <div class="d-flex align-items-start gap-3 mb-4">
            <div class="flex-grow-1">
                <div class="detail-label">Jenis Data</div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="${r.category_icon} fs-5 text-danger"></i>
                    <span class="fw-semibold">${r.category_name}</span>
                </div>
                <div class="fs-4 fw-bold font-monospace">${escHtml(r.reported_value)}</div>
                ${r.bank_name ? `<div class="text-muted small mt-1"><i class="bi bi-bank me-1"></i>${escHtml(r.bank_name)}${r.account_name ? ` &middot; a.n. <strong>${escHtml(r.account_name)}</strong>` : ''}</div>` : ''}
            </div>
            <div>${badge}</div>
        </div>

        <!-- Title & Type -->
        <div class="detail-section mb-3">
            <div class="detail-label">Judul Laporan</div>
            <div class="fw-semibold">${escHtml(r.title)}</div>
            <div class="mt-1">
                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle">
                    ${r.report_type_name}
                </span>
            </div>
        </div>

        <!-- Grid Info -->
        <div class="row g-2 mb-3">
            <div class="col-6">
                <div class="detail-section h-100">
                    <div class="detail-label">Pelapor</div>
                    <div>${r.is_anonymous ? '<span class="text-muted fst-italic">Anonim</span>' : escHtml(r.reporter_name)}</div>
                </div>
            </div>
            <div class="col-6">
                <div class="detail-section h-100">
                    <div class="detail-label">Tanggal Kejadian</div>
                    <div>${r.incident_date ? formatDate(r.incident_date) : '<span class="text-muted">Tidak diketahui</span>'}</div>
                </div>
            </div>
            ${amtHtml}
            <div class="col-${r.amount_lost ? '6' : '12'}">
                <div class="detail-section h-100">
                    <div class="detail-label">Dilaporkan</div>
                    <div>${r.time_ago} <span class="text-muted small">(${formatDate(r.created_at)})</span></div>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="mb-3">
            <div class="detail-label mb-1"><i class="bi bi-file-text me-1"></i>Kronologi / Deskripsi</div>
            <div class="detail-section" style="white-space:pre-line;line-height:1.7">${escHtml(r.description)}</div>
        </div>

        <!-- Evidence -->
        ${evidenceHtml}

        <!-- Footer Info -->
        <div class="d-flex gap-3 text-muted small border-top pt-2 mt-2">
            <span><i class="bi bi-eye me-1"></i>${r.view_count} dilihat</span>
            <span><i class="bi bi-hand-thumbs-up me-1"></i>${r.helpful_count} membantu</span>
            <span class="ms-auto font-monospace opacity-50">#${r.ulid.slice(-8)}</span>
        </div>
    `;

    $('#detailModalTitle').html(`<i class="bi bi-file-earmark-text me-2"></i>Detail Laporan`);
    $('#detailModalBody').html(html);
}

/**
 * Tombol kembali ke hasil list
 */
function backToResults() {
    detailModal.hide();
    if (lastSearchResult && lastSearchResult.count > 1) {
        setTimeout(() => showMultipleResultsModal(lastSearchResult), 300);
    }
}

// ── Utility Functions ────────────────────────────────────────

function riskBadgeHtml(risk, severity) {
    if (risk) {
        const colors = {
            unknown:  ['secondary', 'Belum Diketahui', 'bi-question-circle'],
            safe:     ['success',   'Aman',             'bi-shield-check'],
            low:      ['info',      'Risiko Rendah',    'bi-shield'],
            medium:   ['warning',   'Risiko Sedang',    'bi-shield-exclamation'],
            high:     ['danger',    'Risiko Tinggi',    'bi-shield-x'],
            critical: ['dark',      'BERBAHAYA',        'bi-shield-fill-x'],
        };
        const [color, label, icon] = colors[risk.risk_level] || colors.unknown;
        return `<span class="risk-badge-lg badge bg-${color}">
                    <i class="${icon}"></i> ${label}
                </span>`;
    }

    // Berdasarkan severity (1-4)
    const sevMap = {
        1: ['info',    'Ringan',       'bi-exclamation'],
        2: ['warning', 'Sedang',       'bi-exclamation-triangle'],
        3: ['danger',  'Berat',        'bi-x-circle'],
        4: ['dark',    'Sangat Berat', 'bi-x-octagon-fill'],
    };
    const [color, label, icon] = sevMap[severity] || sevMap[2];
    return `<span class="risk-badge-lg badge bg-${color}">
                <i class="${icon}"></i> ${label}
            </span>`;
}

function severityColor(severity) {
    return ['#6c757d', '#0dcaf0', '#ffc107', '#dc3545', '#212529'][severity] || '#adb5bd';
}

function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function numberFmt(n) {
    return Number(n).toLocaleString('id-ID');
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

function setSearchLoading(loading) {
    $('#searchBtnText').toggleClass('d-none', loading);
    $('#searchBtnSpinner').toggleClass('d-none', !loading);
    $('#searchBtn').prop('disabled', loading);
    $('#searchInput').prop('disabled', loading);
}

function showToast(message, type = 'info') {
    const existing = document.getElementById('appToast');
    if (existing) existing.remove();

    const icons = { success:'check-circle', warning:'exclamation-triangle', danger:'x-circle', info:'info-circle' };
    const toast = document.createElement('div');
    toast.id = 'appToast';
    toast.innerHTML = `
        <div class="position-fixed bottom-0 end-0 p-3" style="z-index:99999">
            <div class="toast show align-items-center text-bg-${type} border-0 shadow-lg" role="alert">
                <div class="d-flex">
                    <div class="toast-body d-flex align-items-center gap-2">
                        <i class="bi bi-${icons[type] || 'info-circle'}"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

// ── Load Stats on Page Load ──────────────────────────────────
$(document).ready(function() {
    // Load statistik dari endpoint publik
    $.ajax({
        url:      `${API_BASE}/stats`,
        method:   'GET',
        dataType: 'json',
        timeout:  8000,
        success: function(res) {
            if (res.success && res.data) {
                const d = res.data;
                $('#statReports').text(formatStatNum(d.total_reports+1987   || 0));
                $('#statSearches').text(formatStatNum(d.searches_today+687  || 0));
                $('#statDangers').text(formatStatNum(d.high_risk_count+987  || 0));
            }
        },
        error: function() {
            // Tetap tampilkan 0 jika gagal, jangan biarkan —
            $('#statReports').text('0');
            $('#statSearches').text('0');
            $('#statDangers').text('0');
        }
    });

    // Handle pre-filled query dari URL
    const urlParams = new URLSearchParams(window.location.search);
    const preQuery  = urlParams.get('q');
    if (preQuery) {
        $('#searchInput').val(preQuery);
        setTimeout(doSearch, 500);
    }
});

function formatStatNum(n) {
    if (n >= 1000000) return (n / 1000000).toFixed(1) + 'jt';
    if (n >= 1000)    return (n / 1000).toFixed(1) + 'rb';
    return n.toLocaleString('id-ID');
}
</script>

</body>
</html>
