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

  $('#meta').html(`
    <p>Total laporan: <b>${res.reports.length}</b></p>
  `);

  let html = "";

//
res.reports.forEach(r => {

  // badge kategori
  let badgeClass = "badge";
  if(r.category === "penipuan") badgeClass += " badge-penipuan";
  if(r.category === "spam") badgeClass += " badge-spam";
  if(r.category === "safe") badgeClass += " badge-safe";

  // badge status
  let statusLabel = "Dalam pemeriksaan";
  let statusClass = "badge badge-status badge-pending";

  if(r.status === "approved"){
    statusLabel = "Terbukti";
    statusClass = "badge badge-status badge-valid";
  } else if(r.status === "rejected"){
    statusLabel = "Bukti lemah";
    statusClass = "badge badge-status badge-weak";
  }

  html += `
    <div class="card">
      <div class="badge-row">
        <div class="${badgeClass}">
          ${r.category.toUpperCase()}
        </div>
        <div class="${statusClass}">
          ${statusLabel}
        </div>
      </div>

      <p>${r.description}</p>
      <small>${r.created_at}</small>
    </div>
  `;
});

//

  $('#list').html(html);
});
</script>

</body>
</html>
