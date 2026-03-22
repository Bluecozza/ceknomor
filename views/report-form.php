<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Laporan Baru – Cek Resource</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a1f36;
            --accent: #e63946;
        }
        body { font-family: 'Inter', sans-serif; background: #f5f6fa; color: var(--primary); }
        .navbar-brand { font-family: 'Space Grotesk', sans-serif; font-weight: 700; }
        .page-header { background: linear-gradient(135deg, #0f172a, #1e293b); color: white; padding: 2.5rem 0 2rem; }
        .page-header h1 { font-family: 'Space Grotesk', sans-serif; font-weight: 700; }
        .form-card { background: white; border-radius: 16px; padding: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
        .form-section-title { font-family: 'Space Grotesk', sans-serif; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--accent); border-bottom: 2px solid #fde8e9; padding-bottom: 0.5rem; margin-bottom: 1.25rem; }
        .required-star { color: var(--accent); }
        .step-badge { width: 32px; height: 32px; border-radius: 50%; background: var(--accent); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; flex-shrink: 0; }
        .preview-value { font-family: monospace; font-size: 1.1rem; font-weight: 600; }
        .upload-area { border: 2px dashed #dee2e6; border-radius: 12px; padding: 1.5rem; text-align: center; cursor: pointer; transition: all 0.2s; }
        .upload-area:hover { border-color: var(--accent); background: #fff8f8; }
        .preview-thumb { width: 70px; height: 55px; object-fit: cover; border-radius: 8px; border: 1px solid #dee2e6; }
        footer { background: #0f172a; color: rgba(255,255,255,0.5); font-size: 0.82rem; padding: 1.25rem; text-align: center; margin-top: 3rem; }
        footer a { color: rgba(255,255,255,0.6); }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-dark" style="background:#0f172a">
    <div class="container">
        <a class="navbar-brand" href="/">
            <i class="bi bi-shield-check me-2" style="color:#e63946"></i>Cek Resource
        </a>
        <a href="/" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-search me-1"></i>Cari Data
        </a>
    </div>
</nav>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <div class="d-flex align-items-center gap-3">
            <i class="bi bi-plus-circle fs-2 text-danger"></i>
            <div>
                <h1 class="mb-1">Buat Laporan Baru</h1>
                <p class="mb-0 opacity-75">Bantu komunitas dengan melaporkan data yang berindikasi penipuan</p>
            </div>
        </div>
    </div>
</div>

<div class="container py-4">
    <div class="row g-4">

        <!-- Form Column -->
        <div class="col-lg-8">

            <!-- Alert Peringatan -->
            <div class="alert alert-warning d-flex gap-2 mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill mt-1 flex-shrink-0"></i>
                <div>
                    <strong>Penting:</strong> Laporan akan melalui proses moderasi sebelum dipublikasikan.
                    Pastikan informasi yang Anda berikan akurat dan dapat dipertanggungjawabkan.
                    <strong>Laporan palsu adalah pelanggaran hukum.</strong>
                </div>
            </div>

            <form id="reportForm" novalidate>
                <!-- ── STEP 1: Data yang Dilaporkan ──────────── -->
                <div class="form-card mb-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="step-badge">1</div>
                        <div class="form-section-title mb-0">Data yang Dilaporkan</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Jenis Data <span class="required-star">*</span></label>
                            <select class="form-select" id="categoryId" name="category_id" required>
                                <option value="">Pilih jenis data...</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" id="valueLabel">Nilai Data <span class="required-star">*</span></label>
                            <input type="text" class="form-control font-monospace" id="reportedValue" name="reported_value"
                                placeholder="Masukkan nilai..." maxlength="255" required>
                            <div class="invalid-feedback"></div>
                            <div class="form-text" id="valueHint">Contoh: nomor HP, rekening, email</div>
                        </div>

                        <div class="col-md-6" id="bankNameField" style="display:none">
                            <label class="form-label">Nama Bank</label>
                            <input type="text" class="form-control" id="bankName" name="bank_name"
                                placeholder="BCA, Mandiri, BRI, BNI...">
                        </div>

                        <div class="col-md-6" id="accountNameField" style="display:none">
                            <label class="form-label">Nama Pemilik Rekening/Akun</label>
                            <input type="text" class="form-control" id="accountName" name="account_name"
                                placeholder="Nama pemilik...">
                        </div>
                    </div>
                </div>

                <!-- ── STEP 2: Informasi Laporan ──────────────── -->
                <div class="form-card mb-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="step-badge">2</div>
                        <div class="form-section-title mb-0">Informasi Laporan</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Jenis Kasus <span class="required-star">*</span></label>
                            <select class="form-select" id="reportTypeId" name="report_type_id" required>
                                <option value="">Pilih jenis kasus...</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Judul Laporan <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="reportTitle" name="title"
                                placeholder="Ringkasan singkat kejadian..." maxlength="255" minlength="10" required>
                            <div class="form-text">
                                <span id="titleCount">0</span>/255 karakter (minimal 10)
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Kronologi Kejadian <span class="required-star">*</span></label>
                            <textarea class="form-control" id="reportDesc" name="description" rows="5"
                                placeholder="Ceritakan secara detail bagaimana kejadian berlangsung, kapan, berapa kerugian, dll..."
                                minlength="20" required></textarea>
                            <div class="form-text">
                                <span id="descCount">0</span> karakter (minimal 20)
                            </div>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Tanggal Kejadian</label>
                            <input type="date" class="form-control" id="incidentDate" name="incident_date"
                                max="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Estimasi Kerugian (Rp)</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control" id="amountLost" name="amount_lost"
                                    placeholder="0" min="0">
                            </div>
                            <div class="form-text">Opsional – isi jika ada kerugian finansial</div>
                        </div>
                    </div>
                </div>

                <!-- ── STEP 3: Bukti / Lampiran ───────────────── -->
                <div class="form-card mb-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="step-badge">3</div>
                        <div class="form-section-title mb-0">Bukti / Lampiran <span class="text-muted fw-normal">(Opsional)</span></div>
                    </div>

                    <div class="upload-area" onclick="$('#evidenceFiles').click()">
                        <i class="bi bi-cloud-upload fs-2 text-muted mb-2"></i>
                        <p class="mb-1 text-muted">Klik atau drag & drop gambar bukti di sini</p>
                        <p class="small text-muted mb-0">JPG, PNG, WEBP – Maks. 5MB per file, maks. 5 file</p>
                    </div>
                    <input type="file" id="evidenceFiles" name="evidence[]" multiple accept="image/*"
                        class="d-none" onchange="previewFiles(this)">
                    <div id="previewContainer" class="d-flex flex-wrap gap-2 mt-2"></div>
                </div>

                <!-- ── STEP 4: Data Pelapor ───────────────────── -->
                <div class="form-card mb-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="step-badge">4</div>
                        <div class="form-section-title mb-0">Identitas Pelapor</div>
                    </div>

                    <p class="text-muted small mb-3">
                        <i class="bi bi-lock me-1"></i>
                        Data Anda bersifat konfidensial dan hanya digunakan untuk verifikasi laporan.
                        Tidak akan ditampilkan kepada publik jika Anda memilih opsi anonim.
                    </p>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama Lengkap <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="reporterName" name="reporter_name"
                                placeholder="Nama Anda..." minlength="2" required>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email atau No. HP <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="reporterContact" name="reporter_contact"
                                placeholder="email@domain.com atau 081xxx" required>
                            <div class="form-text">Untuk konfirmasi laporan jika diperlukan</div>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="isAnonymous" name="is_anonymous" value="1">
                                <label class="form-check-label" for="isAnonymous">
                                    <strong>Sembunyikan nama saya dari laporan</strong>
                                    <div class="text-muted small">Nama Anda akan ditampilkan sebagai "Anonim"</div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="d-flex gap-3 flex-wrap">
                    <button type="submit" class="btn btn-danger btn-lg px-4" id="submitBtn">
                        <i class="bi bi-send me-2"></i>Kirim Laporan
                    </button>
                    <a href="/" class="btn btn-outline-secondary btn-lg">
                        <i class="bi bi-x me-1"></i>Batal
                    </a>
                </div>
            </form>
        </div>

        <!-- Sidebar Info -->
        <div class="col-lg-4">
            <div class="form-card mb-3 sticky-top" style="top:1rem">
                <h6 class="fw-semibold mb-3"><i class="bi bi-info-circle-fill text-danger me-2"></i>Panduan Pelaporan</h6>
                <ul class="list-unstyled small text-muted">
                    <li class="mb-2"><i class="bi bi-check2-circle text-success me-2"></i>Pastikan data yang dilaporkan akurat</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-success me-2"></i>Sertakan kronologi yang jelas</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-success me-2"></i>Lampirkan bukti screenshot jika ada</li>
                    <li class="mb-2"><i class="bi bi-check2-circle text-success me-2"></i>Laporan akan dimoderasi sebelum tayang</li>
                    <li class="mb-2"><i class="bi bi-x-circle text-danger me-2"></i>Jangan laporkan data tanpa bukti</li>
                    <li class="mb-2"><i class="bi bi-x-circle text-danger me-2"></i>Laporan palsu dapat dituntut hukum</li>
                </ul>

                <hr>

                <div class="small">
                    <div class="fw-semibold mb-2">Preview Nilai yang Dilaporkan</div>
                    <div class="bg-light rounded p-2 font-monospace preview-value" id="previewValue" style="color:#6c757d;font-size:1rem">
                        —
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <div style="width:80px;height:80px;background:#d1fae5;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto;font-size:2rem;color:#059669">
                        <i class="bi bi-check-lg"></i>
                    </div>
                </div>
                <h4 class="fw-bold mb-2">Laporan Terkirim!</h4>
                <p class="text-muted mb-3" id="successMessage">Laporan Anda sedang dalam proses moderasi.</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button class="btn btn-danger" onclick="location.href='/'">
                        <i class="bi bi-search me-1"></i>Kembali Cari Data
                    </button>
                    <button class="btn btn-outline-secondary" onclick="location.reload()">
                        Buat Laporan Lain
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<footer>
    <a href="/">&larr; Cek Resource</a> &nbsp;·&nbsp;
    Database Laporan Penipuan Indonesia
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
const API_BASE = '/api/v1';

// Tampilkan bank field jika rekening
$('#categoryId').on('change', function() {
    const slug = $(this).find(':selected').data('slug') || '';
    const bankFields   = ['bank_account','dana','ovo','gopay','shopeepay','linkaja'];
    const showBankInfo = bankFields.includes(slug);

    $('#bankNameField, #accountNameField').toggle(showBankInfo);

    // Update placeholder
    const hints = {
        phone:       ['Nomor Telepon', '08xxxxxxxxxx'],
        bank_account:['Nomor Rekening', '1234567890'],
        dana:        ['Nomor DANA',    '08xxxxxxxxxx'],
        ovo:         ['Nomor OVO',     '08xxxxxxxxxx'],
        gopay:       ['Nomor GoPay',   '08xxxxxxxxxx'],
        email:       ['Email',         'nama@email.com'],
    };
    const [label, ph] = hints[slug] || ['Nilai Data', 'Masukkan nilai...'];
    $('#valueLabel').html(`${label} <span class="required-star">*</span>`);
    $('#reportedValue').attr('placeholder', ph);
});

// Load categories & report types
$(document).ready(function() {
    $.get(`${API_BASE}/categories`).done(res => {
        if (res.success) {
            res.data.forEach(c => {
                $('#categoryId').append(`<option value="${c.id}" data-slug="${c.slug}">${c.name}</option>`);
            });
        }
    });

    $.get(`${API_BASE}/categories/report-types`).done(res => {
        if (res.success) {
            res.data.forEach(t => {
                $('#reportTypeId').append(`<option value="${t.id}">${t.name}</option>`);
            });
        }
    });

    // Pre-fill dari URL
    const q = new URLSearchParams(location.search).get('q');
    if (q) $('#reportedValue').val(q);
});

// Character counters
$('#reportTitle').on('input', function() {
    $('#titleCount').text($(this).val().length);
    $('#previewValue').text($('#reportedValue').val() || '—');
});
$('#reportDesc').on('input', function() {
    $('#descCount').text($(this).val().length);
});
$('#reportedValue').on('input', function() {
    $('#previewValue').text($(this).val() || '—').css('color', $(this).val() ? '#1a1f36' : '#6c757d');
});

// File preview
function previewFiles(input) {
    const container = document.getElementById('previewContainer');
    container.innerHTML = '';
    const files = Array.from(input.files).slice(0, 5);
    files.forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'preview-thumb';
            container.appendChild(img);
        };
        reader.readAsDataURL(file);
    });
}

// Form submit
$('#reportForm').on('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const btn      = $('#submitBtn');

    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Mengirim...');

    $.ajax({
        url:         `${API_BASE}/reports`,
        method:      'POST',
        data:        formData,
        processData: false,
        contentType: false,
        dataType:    'json',

        success: function(res) {
            if (res.success) {
                $('#successMessage').text(res.message);
                new bootstrap.Modal(document.getElementById('successModal')).show();
            } else {
                showErrors(res.errors || {});
            }
        },

        error: function(xhr) {
            const res = xhr.responseJSON || {};
            if (res.errors) {
                showErrors(res.errors);
            } else {
                alert('Terjadi kesalahan: ' + (res.message || 'Coba lagi'));
            }
        },

        complete: function() {
            btn.prop('disabled', false).html('<i class="bi bi-send me-2"></i>Kirim Laporan');
        }
    });
});

function showErrors(errors) {
    // Reset semua error
    $('.is-invalid').removeClass('is-invalid');
    $('.invalid-feedback').text('');

    Object.entries(errors).forEach(([field, msg]) => {
        const input = $(`[name="${field}"]`);
        input.addClass('is-invalid');
        input.siblings('.invalid-feedback').text(msg);
    });

    // Scroll ke error pertama
    const first = $('.is-invalid').first();
    if (first.length) {
        $('html,body').animate({ scrollTop: first.offset().top - 100 }, 300);
    }
}
</script>
</body>
</html>
