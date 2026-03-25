<?php
/**
 * modules/csvimport/admin/import-csv.php
 * Admin page untuk CSV import dengan debug
 */

$admin = isset($GLOBALS['admin']) ? $GLOBALS['admin'] : null;
if (!$admin || !in_array($admin['role'], ['superadmin', 'admin'])) {
    http_response_code(403);
    die('Access Denied');
}

$page_title = 'Import Laporan CSV';
$page_icon = 'fa-file-csv';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas <?php echo $page_icon; ?>"></i> <?php echo $page_title; ?></h2>
            <p class="text-muted">Upload dan manage import data laporan dari file CSV</p>
        </div>
    </div>

    <!-- Debug Console -->
    <div class="card mb-4 border-warning">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0"><i class="fas fa-bug"></i> Debug Console</h5>
        </div>
        <div class="card-body">
            <pre id="debug-console" style="max-height: 200px; overflow-y: auto; background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px;"></pre>
        </div>
    </div>

    <!-- Step 1: Upload -->
    <div id="step-upload" class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-upload"></i> Step 1: Upload File CSV</h5>
        </div>
        <div class="card-body">
            <form id="form-upload" enctype="multipart/form-data">
                <div class="form-group mb-3">
                    <label for="csv-file" class="form-label">Pilih File CSV</label>
                    <input type="file" class="form-control" id="csv-file" accept=".csv" required>
                    <small class="form-text text-muted d-block mt-2">
                        <strong>Format:</strong> Title, Phone, Rekening, Nama Pelaku, Links, Modus, Keywords, Description, URL, Image<br>
                        <strong>Maksimal:</strong> 5 MB | 1000 records per import
                    </small>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Upload & Preview
                </button>
            </form>
        </div>
    </div>

    <!-- Loading -->
    <div id="loading-container" class="card mb-4" style="display: none;">
        <div class="card-body text-center">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p>Sedang memproses...</p>
        </div>
    </div>

    <!-- Step 2: Preview -->
    <div id="step-preview" class="card mb-4" style="display: none;">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="fas fa-eye"></i> Step 2: Review Data</h5>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-3 text-center">
                    <h3 class="text-primary"><span id="stat-total">0</span></h3>
                    <p class="text-muted">Total Records</p>
                </div>
                <div class="col-md-3 text-center">
                    <h3 class="text-success"><span id="stat-approved">0</span></h3>
                    <p class="text-muted">Approved</p>
                </div>
                <div class="col-md-3 text-center">
                    <h3 class="text-danger"><span id="stat-rejected">0</span></h3>
                    <p class="text-muted">Rejected</p>
                </div>
                <div class="col-md-3 text-center">
                    <h3 class="text-warning"><span id="stat-pending">0</span></h3>
                    <p class="text-muted">Pending</p>
                </div>
            </div>

            <div class="mb-3">
                <button type="button" class="btn btn-success btn-sm" id="btn-approve-all">
                    <i class="fas fa-check-double"></i> Approve All
                </button>
                <button type="button" class="btn btn-warning btn-sm" id="btn-approve-valid">
                    <i class="fas fa-check"></i> Approve Valid Only
                </button>
                <button type="button" class="btn btn-danger btn-sm" id="btn-reject-all">
                    <i class="fas fa-times-circle"></i> Reject All
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Line</th>
                            <th>Title</th>
                            <th>Phones</th>
                            <th>Modus</th>
                            <th>Status</th>
                            <th width="100">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="records-tbody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Step 3: Submit -->
    <div id="step-submit" class="card" style="display: none;">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fas fa-check-circle"></i> Step 3: Submit Import</h5>
        </div>
        <div class="card-body">
            <p>Click button below to submit import to database</p>
            <button type="button" class="btn btn-lg btn-success" id="btn-submit-import">
                <i class="fas fa-database"></i> Submit Import
            </button>
            <button type="button" class="btn btn-lg btn-secondary" id="btn-start-over">
                Start Over
            </button>
        </div>
    </div>

    <!-- Result -->
    <div id="result-section" class="card" style="display: none;">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fas fa-check-circle"></i> Import Complete</h5>
        </div>
        <div class="card-body">
            <div id="result-content"></div>
        </div>
    </div>
</div>

<script>
let currentSessionId = null;
const API_URL = (typeof API !== 'undefined' ? API : '/api/v1') + '/plugins/csvimport';

// Debug logger
function debugLog(message, type = 'info', data = null) {
    const timestamp = new Date().toLocaleTimeString();
    const consoleElement = document.getElementById('debug-console');
    let logLine = `[${timestamp}] [${type.toUpperCase()}] ${message}`;
    if (data) {
        logLine += '\n' + JSON.stringify(data, null, 2);
    }
    if (consoleElement) {
        consoleElement.textContent += logLine + '\n\n';
        consoleElement.scrollTop = consoleElement.scrollHeight;
    }
    console.log(logLine);
}

function getToken() {
    return localStorage.getItem('admin_token') || localStorage.getItem('token') || sessionStorage.getItem('token') || '';
}

// Upload handler
document.getElementById('form-upload').addEventListener('submit', async (e) => {
    e.preventDefault();
    e.stopPropagation();
    debugLog('Form submitted', 'info');

    const file = document.getElementById('csv-file').files[0];
    if (!file) {
        debugLog('No file selected', 'error');
        alert('Please select a CSV file');
        return;
    }

    debugLog('File selected: ' + file.name + ' (' + file.size + ' bytes)', 'info');

    const formData = new FormData();
    formData.append('file', file);

    document.getElementById('loading-container').style.display = 'block';

    try {
        const token = getToken();
        debugLog('Token: ' + (token ? token.substring(0, 20) + '...' : 'NOT FOUND'), 'info');
        
        debugLog('Sending request to: ' + API_URL + '/upload', 'info');
        
        const response = await fetch(API_URL + '/upload', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`
            },
            body: formData
        });

        debugLog('Response status: ' + response.status, 'info');
        debugLog('Response headers: Content-Type=' + response.headers.get('content-type'), 'info');

        const data = await response.json();
        
        debugLog('Response data received', 'info', data);

        if (data.success) {
            currentSessionId = data.data.session_id;
            debugLog('Session created: ' + currentSessionId, 'success');
            await loadPreview(currentSessionId);
        } else {
            debugLog('API returned error: ' + data.message, 'error', data);
            alert('Error: ' + data.message);
        }
    } catch (error) {
        debugLog('Fetch error: ' + error.message, 'error');
        debugLog('Stack: ' + error.stack, 'error');
        alert('Upload error: ' + error.message);
    } finally {
        document.getElementById('loading-container').style.display = 'none';
    }
});

// Load preview
async function loadPreview(sessionId) {
    debugLog('Loading preview for session: ' + sessionId, 'info');
    
    try {
        const token = getToken();
        debugLog('Sending preview request', 'info');
        
        const response = await fetch(`${API_URL}/preview/${sessionId}`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });

        debugLog('Preview response status: ' + response.status, 'info');

        const data = await response.json();
        debugLog('Preview data received', 'info', data);

        if (!data.success) {
            debugLog('Preview error: ' + data.message, 'error', data);
            alert('Error: ' + data.message);
            return;
        }

        const summary = data.data.summary;
        const records = data.data.records || [];
        
        debugLog('Records loaded: ' + records.length, 'success');

        document.getElementById('stat-total').textContent = summary.total_records;
        const approved = records.filter(r => r.status === 'approved').length;
        const rejected = records.filter(r => r.status === 'rejected').length;
        const pending = records.filter(r => r.status === 'pending').length;
        
        document.getElementById('stat-approved').textContent = approved;
        document.getElementById('stat-rejected').textContent = rejected;
        document.getElementById('stat-pending').textContent = pending;

        const tbody = document.getElementById('records-tbody');
        tbody.innerHTML = '';

        records.forEach(record => {
            const phones = record.parsed_data?.phones?.join(', ') || 'N/A';
            const modus = record.parsed_data?.modus || 'N/A';
            const statusBadge = `<span class="badge bg-${record.status === 'approved' ? 'success' : record.status === 'rejected' ? 'danger' : 'warning'}">${record.status}</span>`;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${record.line_no}</td>
                <td><small>${record.parsed_data?.title || 'N/A'}</small></td>
                <td><small>${phones}</small></td>
                <td><small>${modus}</small></td>
                <td>${statusBadge}</td>
                <td>
                    <button class="btn btn-sm btn-success btn-approve" data-line="${record.line_no}">
                        <i class="fas fa-check"></i>
                    </button>
                    <button class="btn btn-sm btn-danger btn-reject" data-line="${record.line_no}">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        document.querySelectorAll('.btn-approve').forEach(btn => {
            btn.addEventListener('click', async () => {
                await updateStatus(currentSessionId, btn.dataset.line, 'approved');
                await loadPreview(currentSessionId);
            });
        });

        document.querySelectorAll('.btn-reject').forEach(btn => {
            btn.addEventListener('click', async () => {
                await updateStatus(currentSessionId, btn.dataset.line, 'rejected');
                await loadPreview(currentSessionId);
            });
        });

        document.getElementById('step-upload').style.display = 'none';
        document.getElementById('step-preview').style.display = 'block';
        document.getElementById('step-submit').style.display = 'block';

    } catch (error) {
        debugLog('Preview fetch error: ' + error.message, 'error');
        alert('Preview error: ' + error.message);
    }
}

async function updateStatus(sessionId, lineNo, status) {
    const token = getToken();
    await fetch(`${API_URL}/record/${sessionId}/${lineNo}`, {
        method: 'PUT',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ status })
    });
}

async function bulkAction(action) {
    const token = getToken();
    const response = await fetch(`${API_URL}/bulk-action/${currentSessionId}`, {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ action })
    });

    const data = await response.json();
    if (data.success) {
        await loadPreview(currentSessionId);
    }
}

document.getElementById('btn-approve-all')?.addEventListener('click', async () => {
    await bulkAction('approve_all');
});

document.getElementById('btn-approve-valid')?.addEventListener('click', async () => {
    await bulkAction('approve_valid');
});

document.getElementById('btn-reject-all')?.addEventListener('click', async () => {
    if (!confirm('Reject all records?')) return;
    await bulkAction('reject_all');
});

document.getElementById('btn-submit-import').addEventListener('click', async () => {
    if (!confirm('Submit import?')) return;

    document.getElementById('loading-container').style.display = 'block';
    debugLog('Submitting import...', 'info');

    try {
        const token = getToken();
        const response = await fetch(`${API_URL}/submit/${currentSessionId}`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });

        const data = await response.json();
        debugLog('Submit response received', 'info', data);

        if (data.success) {
            debugLog('Import submitted successfully', 'success');
            document.getElementById('result-content').innerHTML = `
                <div class="alert alert-success">
                    <h5>Import Successful!</h5>
                    <ul>
                        <li>Created: <strong class="text-success">${data.data.created || 0}</strong></li>
                        <li>Failed: <strong class="text-danger">${data.data.failed || 0}</strong></li>
                    </ul>
                </div>
            `;

            document.getElementById('step-preview').style.display = 'none';
            document.getElementById('step-submit').style.display = 'none';
            document.getElementById('result-section').style.display = 'block';
        } else {
            debugLog('Submit error: ' + data.message, 'error', data);
            alert('Error: ' + data.message);
        }
    } catch (error) {
        debugLog('Submit fetch error: ' + error.message, 'error');
        alert('Submit error: ' + error.message);
    } finally {
        document.getElementById('loading-container').style.display = 'none';
    }
});

document.getElementById('btn-start-over').addEventListener('click', () => {
    location.reload();
});

debugLog('Page loaded and ready', 'success');
</script>
