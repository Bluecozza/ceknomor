/**
 * cek.resource.my.id — Global JavaScript Utilities
 * Fungsi-fungsi yang digunakan di seluruh halaman website
 */

/* ═══════════════════════════════════════════════════════════
   TOAST NOTIFICATIONS
═══════════════════════════════════════════════════════════ */

/**
 * Tampilkan toast notifikasi
 * @param {string} message  - Pesan yang ditampilkan
 * @param {'success'|'error'|'info'|'warning'} type - Jenis toast
 * @param {number} duration - Durasi tampil dalam ms (default: 3000)
 */
function showToast(message, type = 'info', duration = 3000) {
    const icons = {
        success: '✓',
        error:   '✕',
        info:    'ℹ',
        warning: '⚠',
    };

    const toast = document.createElement('div');
    toast.className = `app-toast toast-${type}`;
    toast.innerHTML = `<span style="font-size:1rem">${icons[type] || icons.info}</span> <span>${message}</span>`;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(8px)';
        toast.style.transition = 'all .25s ease';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

/* ═══════════════════════════════════════════════════════════
   CLIPBOARD
═══════════════════════════════════════════════════════════ */

/**
 * Salin teks ke clipboard
 * @param {string} text - Teks yang akan disalin
 * @param {string} successMsg - Pesan setelah berhasil
 */
function copyToClipboard(text, successMsg = 'Berhasil disalin!') {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text)
            .then(() => showToast(successMsg, 'success'))
            .catch(() => fallbackCopy(text, successMsg));
    } else {
        fallbackCopy(text, successMsg);
    }
}

function fallbackCopy(text, successMsg) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.cssText = 'position:fixed;top:-999px;left:-999px;opacity:0';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();
    try {
        document.execCommand('copy');
        showToast(successMsg, 'success');
    } catch {
        showToast('Gagal menyalin, salin manual', 'error');
    }
    document.body.removeChild(textarea);
}

/* ═══════════════════════════════════════════════════════════
   FORMAT UTILITIES
═══════════════════════════════════════════════════════════ */

/**
 * Format angka ke format Rupiah
 * @param {number} amount
 * @returns {string} e.g. "Rp 1.500.000"
 */
function formatRupiah(amount) {
    if (!amount && amount !== 0) return '—';
    return 'Rp ' + Number(amount).toLocaleString('id-ID');
}

/**
 * Format tanggal ke bahasa Indonesia
 * @param {string} dateStr - ISO date string
 * @param {boolean} withTime - Sertakan jam
 */
function formatDate(dateStr, withTime = false) {
    if (!dateStr) return '—';
    try {
        const opts = { day: 'numeric', month: 'long', year: 'numeric' };
        if (withTime) { opts.hour = '2-digit'; opts.minute = '2-digit'; }
        return new Date(dateStr).toLocaleDateString('id-ID', opts);
    } catch { return dateStr; }
}

/**
 * Waktu relatif (time ago)
 * @param {string} dateStr - ISO date string
 * @returns {string} e.g. "3 jam lalu"
 */
function timeAgo(dateStr) {
    if (!dateStr) return '—';
    const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
    if (diff < 60)    return 'Baru saja';
    if (diff < 3600)  return Math.floor(diff / 60) + ' menit lalu';
    if (diff < 86400) return Math.floor(diff / 3600) + ' jam lalu';
    if (diff < 2592000) return Math.floor(diff / 86400) + ' hari lalu';
    return Math.floor(diff / 2592000) + ' bulan lalu';
}

/**
 * Potong teks panjang
 * @param {string} text
 * @param {number} max
 */
function truncate(text, max = 100) {
    if (!text || text.length <= max) return text || '';
    return text.slice(0, max).trimEnd() + '…';
}

/* ═══════════════════════════════════════════════════════════
   RISK LEVEL HELPERS
═══════════════════════════════════════════════════════════ */

const RISK_CONFIG = {
    unknown:  { label: 'Belum Diketahui', emoji: '❓', color: '#94a3b8' },
    safe:     { label: 'Aman',            emoji: '✅', color: '#4ade80' },
    low:      { label: 'Risiko Rendah',   emoji: '🟡', color: '#a3e635' },
    medium:   { label: 'Risiko Sedang',   emoji: '🟠', color: '#facc15' },
    high:     { label: 'Risiko Tinggi',   emoji: '🔴', color: '#fb923c' },
    critical: { label: 'KRITIS',          emoji: '🚨', color: '#f87171' },
};

/**
 * Buat HTML risk badge
 * @param {string} level - Risk level slug
 * @param {boolean} large - Ukuran besar
 */
function renderRiskBadge(level, large = false) {
    const cfg   = RISK_CONFIG[level] || RISK_CONFIG.unknown;
    const style = large ? 'font-size:1rem;padding:.45rem 1.1rem' : '';
    return `<span class="risk-badge risk-${level}" style="${style}">${cfg.emoji} ${cfg.label}</span>`;
}

/**
 * Warna progress bar risk score (0-100)
 */
function riskScoreColor(score) {
    if (score === 0)   return '#94a3b8';
    if (score <= 15)   return '#4ade80';
    if (score <= 35)   return '#a3e635';
    if (score <= 60)   return '#facc15';
    if (score <= 80)   return '#fb923c';
    return '#f87171';
}

/* ═══════════════════════════════════════════════════════════
   AJAX HELPERS
═══════════════════════════════════════════════════════════ */

/**
 * Fetch wrapper dengan JSON support
 * @param {string} url
 * @param {object} options - fetch options
 * @returns {Promise<object>} response data
 */
async function apiFetch(url, options = {}) {
    const defaults = {
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    };

    const token = localStorage.getItem('admin_token');
    if (token) defaults.headers['Authorization'] = 'Bearer ' + token;

    const config = { ...defaults, ...options };
    if (config.body && typeof config.body === 'object' && !(config.body instanceof FormData)) {
        config.body = JSON.stringify(config.body);
    }
    if (config.body instanceof FormData) {
        delete config.headers['Content-Type']; // biarkan browser set boundary
    }

    const res  = await fetch(url, config);
    const data = await res.json().catch(() => ({ success: false, message: 'Response tidak valid' }));

    if (!res.ok && !data.success) {
        const err = new Error(data.message || 'Request gagal');
        err.status = res.status;
        err.errors = data.errors || {};
        throw err;
    }

    return data;
}

/* ═══════════════════════════════════════════════════════════
   FORM VALIDATION DISPLAY
═══════════════════════════════════════════════════════════ */

/**
 * Tampilkan error dari API di form
 * @param {object} errors - { fieldName: 'pesan error', ... }
 * @param {string} formSelector - CSS selector form container
 */
function displayFormErrors(errors, formSelector = 'form') {
    // Hapus error lama
    clearFormErrors(formSelector);

    Object.entries(errors).forEach(([field, message]) => {
        const input = document.querySelector(`${formSelector} [name="${field}"]`);
        if (!input) return;

        input.classList.add('is-invalid');
        const feedback = document.createElement('div');
        feedback.className = 'text-danger small mt-1 form-field-error';
        feedback.textContent = message;
        input.parentNode.appendChild(feedback);
    });
}

function clearFormErrors(formSelector = 'form') {
    document.querySelectorAll(`${formSelector} .is-invalid`).forEach(el => el.classList.remove('is-invalid'));
    document.querySelectorAll(`${formSelector} .form-field-error`).forEach(el => el.remove());
}

/* ═══════════════════════════════════════════════════════════
   CATEGORY ICONS
═══════════════════════════════════════════════════════════ */

const CATEGORY_ICONS = {
    phone:       'bi-telephone',
    bank_account:'bi-bank',
    dana:        'bi-wallet2',
    ovo:         'bi-wallet',
    gopay:       'bi-cash-stack',
    shopeepay:   'bi-bag',
    linkaja:     'bi-link-45deg',
    email:       'bi-envelope',
    social:      'bi-person-badge',
    other:       'bi-question-circle',
};

function getCategoryIcon(slug) {
    return CATEGORY_ICONS[slug] || CATEGORY_ICONS.other;
}

/* ═══════════════════════════════════════════════════════════
   CATEGORY LABELS (ID)
═══════════════════════════════════════════════════════════ */

const CATEGORY_LABELS = {
    phone:       'Nomor Telepon',
    bank_account:'Rekening Bank',
    dana:        'DANA',
    ovo:         'OVO',
    gopay:       'GoPay',
    shopeepay:   'ShopeePay',
    linkaja:     'LinkAja',
    email:       'Email',
    social:      'Media Sosial',
    other:       'Lainnya',
};

/* ═══════════════════════════════════════════════════════════
   SEARCH QUERY HELPERS
═══════════════════════════════════════════════════════════ */

/**
 * Deteksi kemungkinan kategori dari format query
 * @param {string} query
 * @returns {string} slug kategori yang paling mungkin
 */
function detectCategory(query) {
    if (!query) return '';
    query = query.trim();
    if (/^(\+62|62|08)\d{7,13}$/.test(query.replace(/\s|-/g, ''))) return 'phone';
    if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(query)) return 'email';
    if (/^\d{8,20}$/.test(query)) return 'bank_account';
    return '';
}

/**
 * Mask/samarkan nilai sensitif untuk tampilan
 * @param {string} value
 * @param {string} category
 */
function maskValue(value, category) {
    if (!value) return '—';
    if (category === 'phone' || !category) {
        if (value.length < 6) return value;
        return value.slice(0, 4) + '*'.repeat(value.length - 6) + value.slice(-2);
    }
    if (category === 'email') {
        const [user, domain] = value.split('@');
        if (!domain) return value[0] + '***';
        return user[0] + '***@' + domain;
    }
    if (category === 'bank_account') {
        if (value.length < 6) return value;
        return value.slice(0, 3) + '*'.repeat(value.length - 5) + value.slice(-2);
    }
    // default
    if (value.length <= 4) return value;
    return value.slice(0, 2) + '*'.repeat(value.length - 4) + value.slice(-2);
}

/* ═══════════════════════════════════════════════════════════
   URL / HISTORY HELPERS
═══════════════════════════════════════════════════════════ */

/**
 * Update query string di URL tanpa reload
 * @param {object} params - { key: value }
 */
function updateUrlParams(params) {
    const url = new URL(window.location.href);
    Object.entries(params).forEach(([k, v]) => {
        if (v === null || v === '' || v === undefined) url.searchParams.delete(k);
        else url.searchParams.set(k, v);
    });
    history.replaceState({}, '', url.toString());
}

/**
 * Ambil semua query params sebagai object
 */
function getUrlParams() {
    return Object.fromEntries(new URL(window.location.href).searchParams.entries());
}

/* ═══════════════════════════════════════════════════════════
   INIT — Jalankan saat DOM siap
═══════════════════════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', function () {
    // Aktifkan semua Bootstrap tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el, { placement: el.dataset.bsPlacement || 'top' });
    });

    // Auto-dismiss alert setelah 5 detik
    document.querySelectorAll('.alert[data-auto-dismiss]').forEach(el => {
        setTimeout(() => el.closest('.alert')?.remove(), 5000);
    });
});
