<?php
/**
 * modules/csvimport/admin/import-csv.php
 * Admin page untuk CSV import
 * Auto-loaded by plugin system
 */

// Check authorization
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
    <div id="loading-container" class="text-center" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
        </div>
        <p class="mt-2">Processing CSV...</p>
    </div>

    <!-- Step 2: Preview -->
    <div id="step-preview" class="card mb-4" style="display: none;">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="fas fa-eye"></i> Step 2: Preview & Review</h5>
        </div>
        <div class="card-body">
            <!-- Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="alert alert-info mb-0">
                        <h6 class="mb-2">Total Records</h6>
                        <h4 id="stat-total" class="mb-0">0</h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="alert alert-success mb-0">
                        <h6 class="mb-2">Approved</h6>
                        <h4 id="stat-approved" class="mb-0">0</h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="alert alert-warning mb-0">
                        <h6 class="mb-2">Pending</h6>
                        <h4 id="stat-pending" class="mb-0">0</h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="alert alert-danger mb-0">
                        <h6 class="mb-2">Rejected</h6>
                        <h4 id="stat-rejected" class="mb-0">0</h4>
                    </div>
                </div>
            </div>

            <!-- Progress -->
            <div class="mb-4">
                <label class="form-label">Approval Progress</label>
                <div class="progress" style="height: 25px;">
                    <div id="progress-approved" class="progress-bar bg-success" role="progressbar" style="width: 0%;">
                        <span id="progress-text">0%</span>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions -->
            <div class="mb-4">
                <button type="button" class="btn btn-sm btn-success" id="btn-approve-valid">
                    <i class="fas fa-check"></i> Approve Valid
                </button>
                <button type="button" class="btn btn-sm btn-success" id="btn-approve-all">
                    <i class="fas fa-check-double"></i> Approve All
                </button>
                <button type="button" class="btn btn-sm btn-warning" id="btn-reject-all">
                    <i class="fas fa-times"></i> Reject All
                </button>
            </div>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th width="50">#</th>
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
const API_URL = '/api/v1/plugins/csvimport';

function getToken() {
    return localStorage.getItem('token') || sessionStorage.getItem('token') || '';
}

// Upload handler
document.getElementById('form-upload').addEventListener('submit', async (e) => {
    e.preventDefault();

    const file = document.getElementById('csv-file').files[0];
    if (!file) {
        alert('Please select a CSV file');
        return;
    }

    const formData = new FormData();
    formData.append('file', file);

    document.getElementById('loading-container').style.display = 'block';

    try {
        const response = await fetch(API_URL + '/upload', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${getToken()}`
            },
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            currentSessionId = data.data.session_id;
            await loadPreview(currentSessionId);
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Upload error: ' + error.message);
    } finally {
        document.getElementById('loading-container').style.display = 'none';
    }
});

// Load preview
async function loadPreview(sessionId) {
    try {
        const response = await fetch(`${API_URL}/preview/${sessionId}`, {
            headers: {
                'Authorization': `Bearer ${getToken()}`
            }
        });

        const data = await response.json();

        if (!data.success) {
            alert('Error: ' + data.message);
            return;
        }

        const summary = data.data.summary;
        document.getElementById('stat-total').textContent = summary.total_records;
        document.getElementById('stat-approved').textContent = summary.approved;
        document.getElementById('stat-pending').textContent = summary.pending;
        document.getElementById('stat-rejected').textContent = summary.rejected;

        const percentage = summary.total_records > 0 
            ? Math.round((summary.approved / summary.total_records) * 100) 
            : 0;
        document.getElementById('progress-approved').style.width = percentage + '%';
        document.getElementById('progress-text').textContent = percentage + '%';

        renderRecordsTable(data.data.records);

        document.getElementById('step-upload').style.display = 'none';
        document.getElementById('step-preview').style.display = 'block';
        document.getElementById('step-submit').style.display = 'block';

    } catch (error) {
        alert('Error: ' + error.message);
    }
}

// Render table
function renderRecordsTable(records) {
    const tbody = document.getElementById('records-tbody');
    tbody.innerHTML = '';

    records.forEach(record => {
        const phones = (record.parsed_data?.phones || []).join(', ') || '-';
        const modus = (record.parsed_data?.modus || []).join(', ') || '-';
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

    // Attach handlers
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
}

// Update status
async function updateStatus(sessionId, lineNo, status) {
    await fetch(`${API_URL}/record/${sessionId}/${lineNo}`, {
        method: 'PUT',
        headers: {
            'Authorization': `Bearer ${getToken()}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ status })
    });
}

// Bulk actions
document.getElementById('btn-approve-all').addEventListener('click', async () => {
    await bulkAction('approve_all');
});

document.getElementById('btn-approve-valid').addEventListener('click', async () => {
    await bulkAction('approve_valid');
});

document.getElementById('btn-reject-all').addEventListener('click', async () => {
    if (!confirm('Reject all records?')) return;
    await bulkAction('reject_all');
});

async function bulkAction(action) {
    const response = await fetch(`${API_URL}/bulk-action/${currentSessionId}`, {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${getToken()}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ action })
    });

    const data = await response.json();
    if (data.success) {
        await loadPreview(currentSessionId);
    }
}

// Submit
document.getElementById('btn-submit-import').addEventListener('click', async () => {
    if (!confirm('Submit import?')) return;

    document.getElementById('loading-container').style.display = 'block';

    try {
        const response = await fetch(`${API_URL}/submit/${currentSessionId}`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${getToken()}`,
                'Content-Type': 'application/json'
            }
        });

        const data = await response.json();

        if (data.success) {
            document.getElementById('result-content').innerHTML = `
                <div class="alert alert-success">
                    <h5>Import Successful!</h5>
                    <ul>
                        <li>Submitted: ${data.data.total_submitted}</li>
                        <li>Successful: <strong class="text-success">${data.data.successful}</strong></li>
                        <li>Failed: <strong class="text-danger">${data.data.failed}</strong></li>
                    </ul>
                </div>
            `;

            document.getElementById('step-preview').style.display = 'none';
            document.getElementById('step-submit').style.display = 'none';
            document.getElementById('result-section').style.display = 'block';
        } else {
            alert('Error: ' + data.message);
        }
    } finally {
        document.getElementById('loading-container').style.display = 'none';
    }
});

// Start over
document.getElementById('btn-start-over').addEventListener('click', () => {
    location.reload();
});
</script>