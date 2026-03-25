<?php
/**
 * modules/export-data/admin/export.php
 * UI for data export
 */
?>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card-dark h-100">
            <div class="card-header-dark">
                <span><i class="bi bi-download me-2 text-muted"></i>Ekspor Laporan</span>
            </div>
            <div class="p-4">
                <p class="text-muted small mb-4">Pilih format dan filter untuk mengekspor data laporan pengguna.</p>
                
                <form id="export-reports-form">
                    <div class="mb-3">
                        <label class="form-label">Status Laporan</label>
                        <select name="status" class="form-select">
                            <option value="">Semua Status</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Disetujui</option>
                            <option value="rejected">Ditolak</option>
                            <option value="flagged">Flagged</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Format Output</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="format" id="fmt-csv-1" value="csv" checked>
                                <label class="form-check-label" for="fmt-csv-1">CSV (.csv)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="format" id="fmt-xml-1" value="xml">
                                <label class="form-check-label" for="fmt-xml-1">XML (.xml)</label>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" onclick="doExport('reports')" class="btn btn-danger w-100 fw-semibold">
                        <i class="bi bi-file-earmark-arrow-down me-2"></i>Mulai Ekspor
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card-dark h-100">
            <div class="card-header-dark">
                <span><i class="bi bi-search me-2 text-muted"></i>Ekspor Log Pencarian</span>
            </div>
            <div class="p-4">
                <p class="text-muted small mb-4">Eskpor 5.000 data log pencarian terbaru untuk dianalisis lebih lanjut.</p>
                
                <form id="export-logs-form">
                    <div class="mb-4">
                        <label class="form-label">Format Output</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="format" id="fmt-csv-2" value="csv" checked>
                                <label class="form-check-label" for="fmt-csv-2">CSV (.csv)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="format" id="fmt-xml-2" value="xml">
                                <label class="form-check-label" for="fmt-xml-2">XML (.xml)</label>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" onclick="doExport('search_logs')" class="btn btn-outline-danger w-100 fw-semibold">
                        <i class="bi bi-search me-2"></i>Mulai Ekspor
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
async function doExport(type) {
    const formId = type === 'reports' ? 'export-reports-form' : 'export-logs-form';
    const form = document.getElementById(formId);
    const formData = new FormData(form);
    
    const params = new URLSearchParams();
    params.set('type', type);
    params.set('format', formData.get('format'));
    params.set('token', localStorage.getItem('admin_token'));
    
    if (type === 'reports' && formData.get('status')) {
        params.set('status', formData.get('status'));
    }

    const exportUrl = '/api/v1/plugins/export-data/generate?' + params.toString();
    
    // Redirect browser to trigger download
    window.location.href = exportUrl;
    
    // Optional: Show success toast
    if (typeof toast === 'function') {
        toast('Mengekspor data...', 'info');
    }
}
</script>
