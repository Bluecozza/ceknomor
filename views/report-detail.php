<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Laporan — cek.resource.my.id</title>
    <!-- OG Tags akan di-inject oleh modul sharing jika aktif -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark:    #0f172a;
            --bg-card:    #1e293b;
            --accent-red: #e63946;
            --text-muted: #94a3b8;
            --border:     #334155;
        }
        body {
            background: var(--bg-dark);
            color: #e2e8f0;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }
        .navbar-brand {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: 1.2rem;
        }
        .navbar-brand span { color: var(--accent-red); }
        .card-dark {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
        }
        .risk-badge {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .5rem 1.2rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: .95rem;
        }
        /* Risk colors */
        .risk-unknown  { background:rgba(148,163,184,.15); color:#94a3b8; }
        .risk-safe     { background:rgba(74,222,128,.15);  color:#4ade80; }
        .risk-low      { background:rgba(163,230,53,.15);  color:#a3e635; }
        .risk-medium   { background:rgba(250,204,21,.15);  color:#facc15; }
        .risk-high     { background:rgba(251,146,60,.15);  color:#fb923c; }
        .risk-critical { background:rgba(248,113,113,.15); color:#f87171; }

        .reported-value-display {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: .05em;
            color: #f1f5f9;
        }
        .evidence-thumb {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid var(--border);
            cursor: pointer;
            transition: transform .15s;
        }
        .evidence-thumb:hover { transform: scale(1.05); }
        .separator {
            border-top: 1px solid var(--border);
            margin: 1.5rem 0;
        }
        .stat-item {
            text-align: center;
            padding: .75rem 1rem;
        }
        .stat-value {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: #f1f5f9;
        }
        .meta-item {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            padding: .6rem 0;
            border-bottom: 1px solid rgba(255,255,255,.05);
        }
        .meta-item:last-child { border-bottom: none; }
        .meta-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: rgba(255,255,255,.06);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: .95rem;
            color: var(--text-muted);
        }
        .share-btn {
            background: rgba(255,255,255,.07);
            border: 1px solid var(--border);
            color: #e2e8f0;
            border-radius: 8px;
            padding: .4rem .85rem;
            font-size: .8rem;
            cursor: pointer;
            text-decoration: none;
            transition: background .15s;
        }
        .share-btn:hover { background: rgba(255,255,255,.12); color: #fff; }
        #loadingState, #errorState { min-height: 50vh; }
        footer {
            border-top: 1px solid var(--border);
            color: var(--text-muted);
        }
    </style>
</head>
<body>

<!-- ─── NAVBAR ─── -->
<nav class="navbar navbar-dark" style="background:rgba(15,23,42,.95);border-bottom:1px solid var(--border)">
    <div class="container">
        <a class="navbar-brand" href="/"><span>cek</span>.resource.my.id</a>
        <a href="/" class="btn btn-sm" style="background:rgba(255,255,255,.07);border:1px solid var(--border);color:#e2e8f0">
            <i class="bi bi-search me-1"></i>Cek Data Lain
        </a>
    </div>
</nav>

<!-- ─── LOADING STATE ─── -->
<div id="loadingState" class="d-flex align-items-center justify-content-center">
    <div class="text-center">
        <div class="spinner-border text-secondary mb-3" role="status"></div>
        <p class="text-muted">Memuat detail laporan...</p>
    </div>
</div>

<!-- ─── ERROR STATE ─── -->
<div id="errorState" class="d-none d-flex align-items-center justify-content-center">
    <div class="text-center px-3">
        <div style="font-size:4rem">🔍</div>
        <h3 class="mt-3">Laporan Tidak Ditemukan</h3>
        <p class="text-muted">Laporan ini mungkin telah dihapus atau belum disetujui.</p>
        <a href="/" class="btn btn-danger mt-2">Kembali ke Beranda</a>
    </div>
</div>

<!-- ─── MAIN CONTENT ─── -->
<div id="mainContent" class="d-none">
<div class="container py-4" style="max-width:800px">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb" style="font-size:.85rem">
            <li class="breadcrumb-item"><a href="/" class="text-muted text-decoration-none">Beranda</a></li>
            <li class="breadcrumb-item text-muted active" id="breadCrumbCategory">Laporan</li>
            <li class="breadcrumb-item text-muted active" aria-current="page">Detail</li>
        </ol>
    </nav>

    <!-- HERO CARD: Data yang Dilaporkan + Risk -->
    <div class="card-dark p-4 mb-4 text-center">
        <!-- Kategori -->
        <div class="mb-2">
            <span id="categoryBadge" class="badge" style="background:rgba(255,255,255,.1);color:#94a3b8;font-size:.8rem"></span>
        </div>
        <!-- Nilai yang dilaporkan (disamarkan) -->
        <div class="reported-value-display mb-3" id="reportedValueDisplay">—</div>
        <!-- Risk badge -->
        <div id="riskBadgeContainer" class="mb-3"></div>
        <!-- Skor -->
        <div class="row g-0 justify-content-center">
            <div class="col-auto">
                <div class="stat-item px-4">
                    <div class="stat-value" id="totalReports">—</div>
                    <div class="text-muted small">Total Laporan</div>
                </div>
            </div>
            <div class="col-auto border-start border-secondary">
                <div class="stat-item px-4">
                    <div class="stat-value" id="riskScore">—</div>
                    <div class="text-muted small">Skor Risiko</div>
                </div>
            </div>
            <div class="col-auto border-start border-secondary">
                <div class="stat-item px-4">
                    <div class="stat-value" id="viewCount">—</div>
                    <div class="text-muted small">Dilihat</div>
                </div>
            </div>
        </div>
    </div>

    <!-- DETAIL LAPORAN -->
    <div class="card-dark p-4 mb-4">
        <h5 class="mb-3" id="reportTitle" style="font-family:'Space Grotesk',sans-serif;font-weight:600"></h5>

        <div class="separator"></div>

        <!-- Meta info -->
        <div id="metaContainer"></div>

        <div class="separator"></div>

        <!-- Deskripsi -->
        <h6 class="text-muted mb-2" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.06em">Kronologi / Keterangan</h6>
        <p id="reportDescription" class="mb-0" style="line-height:1.7;white-space:pre-wrap"></p>
    </div>

    <!-- BUKTI FOTO -->
    <div class="card-dark p-4 mb-4" id="evidenceSection" style="display:none!important">
        <h6 class="text-muted mb-3" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.06em">Bukti / Lampiran</h6>
        <div class="d-flex flex-wrap gap-2" id="evidenceContainer"></div>
    </div>

    <!-- AKSI: Share & Lapor -->
    <div class="card-dark p-4 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <div class="fw-semibold mb-1">Bagikan Laporan Ini</div>
                <div class="text-muted small">Peringatkan orang lain agar tidak menjadi korban</div>
            </div>
            <div id="shareButtonsContainer" class="d-flex flex-wrap gap-2">
                <!-- Share buttons dirender via JS -->
            </div>
        </div>
        <div class="separator"></div>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <div class="fw-semibold mb-1">Pernah Mengalami Hal Serupa?</div>
                <div class="text-muted small">Tambahkan laporan Anda untuk memperkuat indikator risiko</div>
            </div>
            <a href="/report" id="addReportBtn" class="btn btn-danger btn-sm">
                <i class="bi bi-plus-circle me-1"></i>Tambah Laporan
            </a>
        </div>
    </div>

    <!-- DISCLAIMER -->
    <div class="rounded p-3 mb-4" style="background:rgba(230,57,70,.07);border:1px solid rgba(230,57,70,.2);font-size:.82rem;color:#fca5a5">
        <i class="bi bi-shield-exclamation me-2"></i>
        <strong>Perhatian:</strong> Data pada platform ini bersifat informatif berdasarkan laporan pengguna. Selalu lakukan verifikasi lebih lanjut sebelum mengambil keputusan. cek.resource.my.id tidak bertanggung jawab atas tindakan yang diambil berdasarkan informasi di sini.
    </div>

</div>
</div>

<!-- ─── FOOTER ─── -->
<footer class="py-4 mt-2">
    <div class="container text-center small">
        <a href="/" class="text-muted text-decoration-none me-3">Beranda</a>
        <a href="/report" class="text-muted text-decoration-none me-3">Buat Laporan</a>
        <a href="/docs" class="text-muted text-decoration-none">Dokumentasi API</a>
        <div class="mt-2">© 2025 cek.resource.my.id</div>
    </div>
</footer>

<!-- Lightbox Modal untuk evidence -->
<div class="modal fade" id="lightboxModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="background:#000;border:1px solid var(--border)">
            <div class="modal-header border-secondary py-2">
                <span class="text-muted small">Bukti Laporan</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-2">
                <img id="lightboxImg" src="" alt="Bukti" class="img-fluid rounded">
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script>
$(function () {
    // Ambil ULID dari URL path: /laporan/{ulid}
    const pathParts = window.location.pathname.split('/').filter(Boolean);
    const ulid = pathParts[pathParts.length - 1];

    if (!ulid || ulid.length !== 26) {
        showError();
        return;
    }

    // Update link "Tambah Laporan" agar pre-fill data
    loadReport(ulid);
});

function loadReport(ulid) {
    $.ajax({
        url: '/api/v1/reports/' + ulid,
        method: 'GET',
        success: function (res) {
            if (res.success && res.data && res.data.report) {
                renderReport(res.data.report);
            } else {
                showError();
            }
        },
        error: function () {
            showError();
        }
    });
}

function renderReport(report) {
    // ── Kategori badge
    $('#categoryBadge').text((report.category && report.category.name) || 'Laporan');
    $('#breadCrumbCategory').text((report.category && report.category.name) || 'Laporan');

    // ── Nilai dilaporkan (masked)
    $('#reportedValueDisplay').text(report.reported_value_masked || report.reported_value || '—');

    // ── Risk badge
    const risk      = report.risk || {};
    const level     = risk.level || 'unknown';
    const riskLabels = {
        unknown:  { label: 'Belum Diketahui', icon: '❓' },
        safe:     { label: 'Aman',            icon: '✅' },
        low:      { label: 'Risiko Rendah',   icon: '🟡' },
        medium:   { label: 'Risiko Sedang',   icon: '🟠' },
        high:     { label: 'Risiko Tinggi',   icon: '🔴' },
        critical: { label: 'KRITIS',          icon: '🚨' },
    };
    const rl = riskLabels[level] || riskLabels.unknown;
    $('#riskBadgeContainer').html(
        `<span class="risk-badge risk-${level}">${rl.icon} ${rl.label}</span>`
    );

    // ── Statistik
    $('#totalReports').text(risk.approved_count || report.total_reports || 1);
    $('#riskScore').text(risk.score ? risk.score.toFixed(0) : '0');
    $('#viewCount').text(report.view_count || 1);

    // ── Judul
    $('#reportTitle').text(report.title || 'Laporan');

    // ── Meta
    renderMeta(report);

    // ── Deskripsi
    $('#reportDescription').text(report.description || '');

    // ── Bukti foto
    if (report.evidence_urls && report.evidence_urls.length > 0) {
        const container = $('#evidenceContainer');
        report.evidence_urls.forEach(function (url) {
            const thumb = $('<img>')
                .addClass('evidence-thumb')
                .attr({ src: url, alt: 'Bukti' })
                .on('click', function () {
                    $('#lightboxImg').attr('src', url);
                    new bootstrap.Modal('#lightboxModal').show();
                });
            container.append(thumb);
        });
        $('#evidenceSection').show();
    }

    // ── Share buttons
    renderShareButtons(report);

    // ── Update link tambah laporan dengan pre-fill
    const encoded = encodeURIComponent(report.reported_value || '');
    $('#addReportBtn').attr(
        'href',
        '/report?category=' + (report.category?.slug || '') + '&value=' + encoded
    );

    // ── Update document title
    const masked = report.reported_value_masked || report.reported_value || '';
    document.title = masked + ' — cek.resource.my.id';

    // ── Tampilkan konten
    $('#loadingState').addClass('d-none');
    $('#mainContent').removeClass('d-none');
}

function renderMeta(report) {
    const items = [];

    // Jenis laporan
    if (report.report_type) {
        const severityColors = ['', '#94a3b8', '#facc15', '#fb923c', '#f87171'];
        const severity = report.report_type.severity || 1;
        items.push({
            icon: 'bi-flag',
            label: 'Jenis Laporan',
            value: `<span>${report.report_type.name || '—'}</span>
                    <span class="ms-2 badge" style="background:rgba(255,255,255,.07);color:${severityColors[severity]};font-size:.7rem">
                        Severity ${severity}
                    </span>`,
        });
    }

    // Bank (jika ada)
    if (report.bank_name) {
        items.push({ icon: 'bi-bank', label: 'Bank', value: report.bank_name });
    }
    if (report.account_name) {
        items.push({ icon: 'bi-person', label: 'Nama Pemilik', value: report.account_name });
    }

    // Tanggal kejadian
    if (report.incident_date) {
        items.push({
            icon: 'bi-calendar-event',
            label: 'Tanggal Kejadian',
            value: formatDate(report.incident_date),
        });
    }

    // Kerugian
    if (report.amount_lost) {
        items.push({
            icon: 'bi-currency-exchange',
            label: 'Estimasi Kerugian',
            value: formatRupiah(report.amount_lost),
        });
    }

    // Pelapor
    const reporterName = report.reporter_name
        ? maskName(report.reporter_name)
        : 'Anonim';
    items.push({
        icon: 'bi-person-circle',
        label: 'Dilaporkan Oleh',
        value: reporterName,
    });

    // Tanggal laporan dibuat
    items.push({
        icon: 'bi-clock',
        label: 'Tanggal Laporan',
        value: report.created_at ? timeAgo(report.created_at) : '—',
    });

    // Status
    const statusMap = {
        approved: ['<span class="badge bg-success">Terverifikasi</span>', 'bi-patch-check'],
        pending:  ['<span class="badge bg-warning text-dark">Menunggu Review</span>', 'bi-hourglass'],
        rejected: ['<span class="badge bg-secondary">Ditolak</span>', 'bi-x-circle'],
        flagged:  ['<span class="badge bg-danger">Ditandai</span>', 'bi-flag'],
    };
    const [statusHtml, statusIcon] = statusMap[report.status] || statusMap.pending;
    items.push({ icon: statusIcon, label: 'Status', value: statusHtml });

    const html = items.map(item => `
        <div class="meta-item">
            <div class="meta-icon"><i class="bi ${item.icon}"></i></div>
            <div>
                <div class="text-muted" style="font-size:.75rem">${item.label}</div>
                <div style="font-size:.9rem">${item.value}</div>
            </div>
        </div>
    `).join('');

    $('#metaContainer').html(html);
}

function renderShareButtons(report) {
    const url  = window.location.href;
    const text = encodeURIComponent('Waspada! Cek laporan ini di cek.resource.my.id: ' + url);
    const encodedUrl = encodeURIComponent(url);

    const buttons = [
        {
            href: 'https://wa.me/?text=' + text,
            icon: 'bi-whatsapp',
            label: 'WhatsApp',
            cls: 'btn-success',
        },
        {
            href: 'https://t.me/share/url?url=' + encodedUrl + '&text=' + text,
            icon: 'bi-telegram',
            label: 'Telegram',
            cls: 'btn-info text-white',
        },
        {
            href: 'https://twitter.com/intent/tweet?text=' + text,
            icon: 'bi-twitter-x',
            label: 'Twitter',
            cls: 'btn-dark',
        },
    ];

    const container = $('#shareButtonsContainer');
    buttons.forEach(btn => {
        container.append(
            `<a href="${btn.href}" target="_blank" rel="noopener"
                class="btn btn-sm ${btn.cls}">
                <i class="bi ${btn.icon} me-1"></i>${btn.label}
             </a>`
        );
    });

    // Tombol salin
    container.append(
        `<button type="button" onclick="copyLink()" class="share-btn">
            <i class="bi bi-clipboard me-1"></i>Salin Link
         </button>`
    );
}

function copyLink() {
    navigator.clipboard.writeText(window.location.href).then(() => {
        showToast('Link berhasil disalin!', 'success');
    });
}

function showError() {
    $('#loadingState').addClass('d-none');
    $('#errorState').removeClass('d-none');
}

// ── Utilities ──────────────────────────────────────────
function maskName(name) {
    if (!name || name.length < 2) return name || 'Anonim';
    return name[0] + '***' + name[name.length - 1];
}

function formatDate(dateStr) {
    try {
        return new Date(dateStr).toLocaleDateString('id-ID', {
            day: 'numeric', month: 'long', year: 'numeric'
        });
    } catch (e) { return dateStr; }
}

function formatRupiah(amount) {
    return 'Rp ' + Number(amount).toLocaleString('id-ID');
}

function timeAgo(dateStr) {
    const date = new Date(dateStr);
    const now  = new Date();
    const diff = Math.floor((now - date) / 1000);

    if (diff < 60)   return 'Baru saja';
    if (diff < 3600) return Math.floor(diff / 60) + ' menit lalu';
    if (diff < 86400) return Math.floor(diff / 3600) + ' jam lalu';
    return Math.floor(diff / 86400) + ' hari lalu';
}

function showToast(message, type = 'info') {
    const colors = { success: '#4ade80', error: '#f87171', info: '#60a5fa' };
    const toast = $(`
        <div style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;
                    background:#1e293b;border:1px solid #334155;color:${colors[type]};
                    border-radius:8px;padding:.75rem 1.25rem;font-size:.875rem;
                    box-shadow:0 4px 20px rgba(0,0,0,.4)">
            ${message}
        </div>
    `).appendTo('body');
    setTimeout(() => toast.fadeOut(300, () => toast.remove()), 3000);
}
</script>
</body>
</html>
