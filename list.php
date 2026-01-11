<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/assets/style.css">

<style>
.page {
  max-width: 520px;
  margin: 0 auto;
  padding: 24px 14px 60px;
}

.card {
  background:#fff;
  border-radius:14px;
  padding:12px 14px;
  margin-bottom:10px;
  border:1px solid #e5e5e5;
  text-align:left;
}

.badge {
  display:inline-block;
  padding:4px 8px;
  border-radius:8px;
  font-size:12px;
  font-weight:600;
  margin-bottom:6px;
}

.badge-penipuan { background:#ffe2e2 }
.badge-spam { background:#fff2c2 }
.badge-safe { background:#d7ffe7 }

.backlink {
  display:inline-block;
  margin-bottom:10px;
  font-size:14px;
}

.badge-row {
  display:flex;
  gap:6px;
  flex-wrap:wrap;
  margin-bottom:6px;
}

.badge-status {
  background:#f1f1f1;
  color:#333;
}

.badge-pending {
  background:#e8f0ff;
  color:#1d4ed8;
}

.badge-valid {
  background:#dcfce7;
  color:#166534;
}

.badge-weak {
  background:#fef3c7;
  color:#92400e;
}
#
.badge-risk {
  font-size:11px;
  font-weight:600;
  padding:4px 8px;
  border-radius:999px;
}

.risk-safe {
  background:#e7f8ee;
  color:#166534;
}

.risk-suspicious {
  background:#fff4d6;
  color:#92400e;
}

.risk-high {
  background:#ffe2e2;
  color:#991b1b;
}
.card-footer {
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-top:6px;
}

</style>
</head>

<body>

<div class="page">

  <a href="/" class="backlink">← Kembali ke pencarian</a>

  <h2>Daftar Laporan Nomor</h2>

  <div id="meta"></div>
  <div id="list"></div>

</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script>
const url = new URL(window.location.href);
const number = url.searchParams.get("number");

$('#list').html("Memuat data...");

$.get('/api.php', { action:'report.list', number:number }, function(res){

  if(res.status !== "ok"){
    $('#list').html("Data tidak ditemukan");
    return;
  }

  if(res.reports.length === 0){
    $('#list').html("Belum ada laporan untuk nomor ini");
    return;
  }

let riskLabel = "AMAN";
let riskClass = "badge-risk risk-safe";

if(res.risk === "suspicious"){
  riskLabel = "MENCURIGAKAN";
  riskClass = "badge-risk risk-suspicious";
} else if(res.risk === "high_risk"){
  riskLabel = "BERISIKO TINGGI";
  riskClass = "badge-risk risk-high";
}

$('#meta').html(`
  <p style="display:flex;align-items:center;gap:8px">
    Total laporan: <b>${res.reports.length}</b>
    <span class="${riskClass}">
      ${riskLabel} • ${res.confidence}%
    </span>
  </p>
`);


  let html = "";

//
res.reports.forEach(r => {

const category = r.category ?? "unknown";

let badgeClass = "badge";
if(category === "penipuan") badgeClass += " badge-penipuan";
else if(category === "spam") badgeClass += " badge-spam";
else badgeClass += " badge-safe";


  // badge status
  let statusLabel = "Dalam pemeriksaan";
  let statusClass = "badge badge-status badge-pending";

  if(r.status === "approved"){
    statusLabel = "Terbukti";
    statusClass = "badge badge-status badge-valid";
  } else if(r.status === "rejected"){
    statusLabel = "Tidak terbukti / Laporan Palsu";
    statusClass = "badge badge-status badge-weak";
  }

  html += `
    <div class="card">
      <div class="badge-row">
<div class="${badgeClass}">
  ${category.toUpperCase()}
</div>

        <div class="${statusClass}">
          ${statusLabel}
        </div>
      </div>

      <p>${r.description}</p>
	      <div class="card-footer">
      <small>${r.created_at}</small>

    </div>
    </div>
  `;
});

//

  $('#list').html(html);
});
</script>

</body>
</html>